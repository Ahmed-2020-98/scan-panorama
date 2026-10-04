<?php

namespace App\Services\Drive;

use App\Models\CaseFile;
use App\Models\MedicalCase;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\HeaderUtils;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DriveCaseStorage
{
    public function __construct(private DriveClient $client, private DriveFolderResolver $folders) {}

    public function store(CaseFile $file): void
    {
        $case = MedicalCase::withoutGlobalScope('access')->findOrFail($file->medical_case_id);
        $case->load('patient', 'branch');
        if (! $case->patient || $case->patient->trashed()) {
            throw new \RuntimeException('Patient no longer active.');
        }
        $path = Storage::disk('local')->path($file->staging_path);
        if (! is_file($path) || filesize($path) !== $file->size) {
            throw new \RuntimeException('Staged file is missing or incomplete.');
        }
        if (! $file->provider_id) {
            $file->update(['provider_id' => $this->client->newId()]);
        }
        $this->client->create(['id' => $file->provider_id, 'name' => $file->original_name, 'parents' => [$this->folders->caseFolder($case, $file->type)]]);
        $response = $this->client->request()->withHeaders(['X-Upload-Content-Type' => $file->mime, 'X-Upload-Content-Length' => (string) $file->size])->patch('https://www.googleapis.com/upload/drive/v3/files/'.rawurlencode($file->provider_id).'?uploadType=resumable&supportsAllDrives=true', (object) []);
        $response->throw();
        $location = $response->header('Location');
        if (parse_url($location, PHP_URL_SCHEME) !== 'https' || parse_url($location, PHP_URL_HOST) !== 'www.googleapis.com') {
            throw new \RuntimeException('Invalid Drive upload session.');
        }
        $handle = fopen($path, 'rb');
        if (! $handle) {
            throw new \RuntimeException('Cannot read staged file.');
        }
        try {
            $offset = 0;
            $completed = false;
            while ($offset < $file->size) {
                $body = fread($handle, max(256 * 1024, (int) config('drive.chunk_bytes')));
                if ($body === false || $body === '') {
                    throw new \RuntimeException('Cannot read staged file.');
                }
                $end = $offset + strlen($body) - 1;
                $part = $this->client->request()->withHeaders(['Content-Range' => "bytes {$offset}-{$end}/{$file->size}"])->withBody($body, $file->mime ?: 'application/octet-stream')->put($location);
                if ($part->status() !== 308) {
                    $part->throw();
                }
                if ($part->successful()) {
                    $completed = true;
                    break;
                }
                $range = $part->header('Range');
                if (! preg_match('/^bytes=0-(\d+)$/', (string) $range, $match) || (int) $match[1] !== $end) {
                    throw new \RuntimeException('Drive acknowledgement does not match uploaded bytes.');
                }
                $offset = $end + 1;
            }
            if (! $completed) {
                throw new \RuntimeException('Drive transfer was not completed.');
            }
        } finally {
            fclose($handle);
        }
        $metadata = $this->client->request()->get('https://www.googleapis.com/drive/v3/files/'.rawurlencode($file->provider_id), ['fields' => 'id,size,md5Checksum', 'supportsAllDrives' => 'true'])->throw()->json();
        if ((int) ($metadata['size'] ?? -1) !== $file->size || ($metadata['md5Checksum'] ?? '') !== md5_file($path)) {
            throw new \RuntimeException('Drive file checksum does not match.');
        }
        $staging = $file->staging_path;
        $file->update(['storage_status' => 'ready', 'storage_error' => null, 'staging_path' => null]);
        Storage::disk('local')->delete($staging);
    }

    public function respond(CaseFile $file, string $mode): StreamedResponse
    {
        $request = $this->client->request()->withOptions(['stream' => true]);
        if ($range = request()->header('Range')) {
            if (! preg_match('/^bytes=\d*-\d*$/', $range)) {
                abort(416);
            } $request = $request->withHeaders(['Range' => $range]);
        }
        $response = $request->get('https://www.googleapis.com/drive/v3/files/'.rawurlencode($file->provider_id), ['alt' => 'media', 'supportsAllDrives' => 'true']);
        abort_unless(in_array($response->status(), [200, 206], true), $response->status() === 416 ? 416 : 502, 'تعذر فتح ملف Drive. تحقق من اتصال المركز.');
        $headers = ['Content-Type' => $file->mime ?: 'application/octet-stream', 'X-Content-Type-Options' => 'nosniff', 'Cache-Control' => 'private, no-store', 'Content-Disposition' => HeaderUtils::makeDisposition($mode === 'view' && $file->isViewable() ? 'inline' : 'attachment', $file->original_name, 'file.'.$file->extension())];
        foreach (['Content-Length', 'Content-Range', 'Accept-Ranges'] as $header) {
            if ($response->header($header)) {
                $headers[$header] = $response->header($header);
            }
        }
        $stream = $response->toPsrResponse()->getBody();

        return response()->stream(function () use ($stream) {
            try {
                while (! $stream->eof()) {
                    echo $stream->read(1024 * 1024);
                }
            } finally {
                $stream->close();
            }
        }, $response->status(), $headers);
    }
}
