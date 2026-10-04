<?php

namespace App\Http\Controllers;

use App\Enums\CaseFileType;
use App\Jobs\UploadCaseFileToDrive;
use App\Models\CaseFile;
use App\Models\DriveConnection;
use App\Models\MedicalCase;
use App\Models\UploadSession;
use App\Support\ActivityLogger;
use App\Support\UploadLimits;
use Illuminate\Http\File;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Receives files in sequential chunks (so uploads are not bound by PHP's
 * upload_max_filesize), then assembles, verifies and attaches them to a case.
 */
class CaseUploadController extends Controller
{
    private const READ_BUFFER = 1024 * 1024;

    public function __invoke(Request $request, MedicalCase $case): JsonResponse
    {
        Gate::authorize('uploadFiles', $case);

        $data = $request->validate([
            'type' => ['required', Rule::enum(CaseFileType::class)],
            'upload_id' => ['required', 'string', 'regex:/^[A-Za-z0-9-]{8,64}$/'],
            'chunk_index' => ['required', 'integer', 'min:0'],
            'total_chunks' => ['required', 'integer', 'min:1', 'max:20000'],
            'file_name' => ['required', 'string', 'max:255'],
            'file_size' => ['required', 'integer', 'min:1'],
            'chunk' => ['required', 'file'],
        ]);

        $type = CaseFileType::from($data['type']);
        if ($request->user()->isDoctor()) {
            abort_unless(in_array($type, [CaseFileType::Report, CaseFileType::Image], true), 403);
        }
        if (config('radiology.upload_provider') === 'drive' && ! DriveConnection::exists()) {
            $this->fail('Google Drive غير متصل. يربط المدير حساب المركز من إعدادات الملفات أولًا.');
        }
        $extension = strtolower(pathinfo($data['file_name'], PATHINFO_EXTENSION));

        if (! in_array($extension, $type->extensions(), true)) {
            $this->fail('امتداد الملف غير مسموح لهذا النوع. المسموح: '.implode('، ', $type->extensions()));
        }

        if ($data['file_size'] > $type->maxBytes()) {
            $this->fail('حجم الملف أكبر من الحد المسموح ('.(int) config("radiology.max_upload_mb.{$type->value}").' ميجابايت).');
        }

        if ($data['chunk_index'] >= $data['total_chunks']) {
            $this->fail('ترتيب الجزء غير صحيح.');
        }

        $key = 'case-upload-'.$request->user()->id.'-'.$data['upload_id'];
        $received = 0;
        $file = Cache::lock($key, 300)->block(10, function () use ($request, $case, $data, $type, $extension, &$received) {
            $session = UploadSession::firstOrCreate(['user_id' => $request->user()->id, 'upload_id' => $data['upload_id']], [
                'medical_case_id' => $case->id, 'type' => $type->value, 'file_name' => $data['file_name'], 'file_size' => $data['file_size'], 'total_chunks' => $data['total_chunks'],
            ]);
            foreach (['medical_case_id' => $case->id, 'type' => $type->value, 'file_name' => $data['file_name'], 'file_size' => (int) $data['file_size'], 'total_chunks' => (int) $data['total_chunks']] as $key => $value) {
                if ((string) $session->{$key} !== (string) $value) {
                    $this->fail('جلسة الرفع مرتبطة بملف أو حالة أخرى.');
                }
            }
            if ($session->case_file_id) {
                return $case->files()->findOrFail($session->case_file_id);
            }
            $chunks = Storage::disk('local');
            $directory = 'chunks/'.$request->user()->id.'-'.$data['upload_id'];
            $chunk = $request->file('chunk');
            if ($chunk->getSize() > UploadLimits::chunkSize()) {
                $this->fail('حجم الجزء أكبر من الحد المسموح.');
            }
            $chunk->storeAs($directory, sprintf('%06d', $data['chunk_index']), 'local');
            $session->touch();
            $received = count(array_filter($chunks->files($directory), fn ($path) => preg_match('/\\/\d{6}$/', $path) === 1));
            if ($received < $data['total_chunks']) {
                return null;
            }
            try {
                return DB::transaction(function () use ($case, $type, $directory, $data, $extension, $request, $session) {
                    $file = $this->assemble($case, $type, $directory, $data['total_chunks'], $data['file_name'], (int) $data['file_size'], $extension, $request->user()->id);
                    $session->update(['case_file_id' => $file->id, 'status' => 'assembled']);
                    ActivityLogger::log('file.uploaded', $file, ['name' => $file->original_name, 'type' => $type->value, 'size' => $file->size]);

                    return $file;
                });
            } finally {
                $chunks->deleteDirectory($directory);
            }
        });
        if (! $file) {
            return response()->json(['done' => false, 'received' => $received]);
        }

        return response()->json([
            'done' => true,
            'file' => ['id' => $file->id, 'name' => $file->original_name, 'size' => $file->humanSize(), 'status' => $file->storage_status],
        ]);
    }

    private function assemble(
        MedicalCase $case,
        CaseFileType $type,
        string $directory,
        int $totalChunks,
        string $originalName,
        int $expectedSize,
        string $extension,
        int $userId,
    ): CaseFile {
        $chunks = Storage::disk('local');
        $assembledPath = $chunks->path($directory.'/assembled.tmp');
        $output = fopen($assembledPath, 'wb');
        $hash = hash_init('sha256');

        if ($output === false) {
            $this->fail('تعذر تجهيز الملف على الخادم، حاول مرة أخرى.');
        }

        try {
            for ($index = 0; $index < $totalChunks; $index++) {
                $chunkPath = $chunks->path($directory.'/'.sprintf('%06d', $index));

                if (! is_file($chunkPath)) {
                    $this->fail('بعض أجزاء الملف مفقودة، أعد المحاولة.');
                }

                $input = fopen($chunkPath, 'rb');

                if ($input === false) {
                    $this->fail('تعذر قراءة أجزاء الملف، أعد المحاولة.');
                }

                while (! feof($input)) {
                    $buffer = (string) fread($input, self::READ_BUFFER);
                    hash_update($hash, $buffer);
                    fwrite($output, $buffer);
                }

                fclose($input);
            }
        } finally {
            fclose($output);
        }

        if (filesize($assembledPath) !== $expectedSize) {
            $this->fail('حجم الملف بعد الرفع لا يطابق الأصل، أعد المحاولة.');
        }

        $mime = (new \finfo(FILEINFO_MIME_TYPE))->file($assembledPath) ?: 'application/octet-stream';

        if ($type->mimeTypes() !== null && ! in_array($mime, $type->mimeTypes(), true)) {
            $this->fail('محتوى الملف لا يطابق نوعه. ارفع صورة (JPG/PNG) أو ملف PDF.');
        }

        $provider = config('radiology.upload_provider');
        $disk = $provider === 'drive' ? 'local' : config('radiology.disk');
        $directory = $provider === 'drive' ? 'drive-staging' : "cases/{$case->id}/{$type->value}";
        $storedPath = Storage::disk($disk)->putFileAs($directory, new File($assembledPath), Str::uuid().'.'.$extension);
        if ($storedPath === false) {
            $this->fail('تعذر حفظ الملف المؤقت، حاول مرة أخرى.');
        }

        try {
            $file = $case->files()->create([
                'type' => $type,
                'original_name' => Str::limit($originalName, 250, ''),
                'path' => $storedPath,
                'mime' => $mime,
                'size' => $expectedSize,
                'sha256' => hash_final($hash),
                'uploaded_by' => $userId,
                'disk' => $provider === 'drive' ? 'drive' : $disk,
                'storage_status' => $provider === 'drive' ? 'pending' : 'ready',
                'staging_path' => $provider === 'drive' ? $storedPath : null,
            ]);
        } catch (\Throwable $error) {
            Storage::disk($disk)->delete($storedPath);
            throw $error;
        }
        if ($provider === 'drive') {
            UploadCaseFileToDrive::dispatch($file->id)->afterCommit();
        }

        return $file;
    }

    private function fail(string $message): never
    {
        throw ValidationException::withMessages(['chunk' => $message]);
    }
}
