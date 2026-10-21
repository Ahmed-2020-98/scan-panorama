<?php

namespace App\Console\Commands;

use App\Enums\Role;
use App\Enums\WorkflowStatus;
use App\Models\ActivityLog;
use App\Models\Branch;
use App\Models\CaseFile;
use App\Models\Doctor;
use App\Models\ExamType;
use App\Models\MedicalCase;
use App\Models\Patient;
use App\Models\Payment;
use App\Models\Setting;
use App\Models\UploadSession;
use App\Models\User;
use App\Support\ActivityLogger;
use Carbon\CarbonImmutable;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

#[Signature('cases:import-sheet {csv : CSV export of a cases tab} {--branch= : Branch name the rows belong to} {--code-prefix= : Generate PREFIX-00001 codes instead of the sheet codes} {--wipe : Delete every case, patient, payment and file record first} {--dry-run : Parse and report without writing}')]
#[Description('Import cases from the center\'s Google Sheet export')]
class ImportSheetCases extends Command
{
    /** Keyword (lowercase, checked in order) => exam type name. */
    private const EXAMS = [
        'pano' => '2D - panorama', 'panprama' => '2D - panorama', 'peri' => 'Periapical', 'ortho' => 'Ortho file',
        'maxilla' => '3D CBCT - Maxilla', 'mandible' => '3D CBCT - Mandible', 'both' => '3D CBCT - Both Arches',
        'endo' => '3D Endo Mode - Endo mode', 'ceph' => '2D - cephalometric', 'tmj' => 'T.M.J OPEN_CLOSED', 'photo' => 'Photos',
        'quadrant' => '3D CBCT - Quadrant', ' ll' => '3D CBCT - Quadrant', ' lr' => '3D CBCT - Quadrant', ' ul' => '3D CBCT - Quadrant', ' ur' => '3D CBCT - Quadrant',
    ];

    /** @var array<string, int> */
    private array $stats = [];

    /** @var array<string, int> */
    private array $unmatchedDoctors = [];

    /** @var array<string, int> */
    private array $unmappedExams = [];

    public function handle(): int
    {
        $path = (string) $this->argument('csv');
        $branch = Branch::get()->first(fn (Branch $b) => self::normalize($b->name) === self::normalize((string) $this->option('branch')));
        if (! is_file($path) || ! $branch) {
            $this->error(! is_file($path) ? 'CSV not found.' : 'Branch not found.');

            return self::FAILURE;
        }
        $admin = User::where('role', Role::Admin)->orderBy('id')->firstOrFail();
        $rows = $this->parse($path);
        $dry = (bool) $this->option('dry-run');
        $paid = array_sum(array_column($rows, 'paid'));

        if (! $dry) {
            if ($this->option('wipe')) {
                $this->wipe();
            }
            $prefix = $this->option('code-prefix');
            $sequence = 0;
            $patients = [];
            foreach (array_chunk($rows, 200) as $chunk) {
                DB::transaction(function () use ($chunk, $branch, $admin, $prefix, &$sequence, &$patients) {
                    foreach ($chunk as $row) {
                        $code = $prefix ? $prefix.'-'.str_pad((string) ++$sequence, 5, '0', STR_PAD_LEFT) : $row['code'];
                        if ($code && MedicalCase::withoutGlobalScope('access')->withTrashed()->where('case_code', $code)->exists()) {
                            $code = null;
                        }
                        $patients[$row['patient_key']] ??= Patient::withoutGlobalScope('access')->create([
                            'name' => $row['name'], 'phone' => $row['phone'], 'birth_date' => $row['birth_date'],
                            'birth_year_only' => $row['birth_date'] !== null, 'created_by' => $admin->id,
                        ])->id;
                        $case = MedicalCase::withoutGlobalScope('access')->create([
                            'case_code' => $code, 'patient_id' => $patients[$row['patient_key']], 'doctor_id' => $row['doctor_id'],
                            'branch_id' => $branch->id, 'exam_type_id' => $row['exam_type_id'], 'exam_date' => $row['date'],
                            'notes_internal' => $row['notes'] ?: null, 'created_by' => $admin->id, 'workflow_status' => $row['status'],
                            'base_price_minor' => $row['price'], 'final_price_minor' => $row['price'], 'currency' => $row['price'] === null ? null : $row['currency'],
                        ]);
                        if ($row['paid'] > 0) {
                            Payment::create([
                                'medical_case_id' => $case->id, 'branch_id' => $branch->id, 'amount_minor' => $row['paid'], 'currency' => $row['currency'],
                                'method' => 'cash', 'collected_by' => $admin->id, 'received_at' => $row['date'], 'request_id' => (string) Str::uuid(),
                            ]);
                        }
                    }
                });
            }
            ActivityLogger::log('cases.imported', null, ['branch' => $branch->name, 'cases' => count($rows), 'file' => basename($path)]);
        }

        $this->info(($dry ? '[dry run] ' : '').'Branch '.$branch->name.': '.count($rows).' cases, paid total '.number_format($paid / 100, 2));
        foreach ($this->stats as $key => $count) {
            $this->line("  {$key}: {$count}");
        }
        arsort($this->unmatchedDoctors);
        arsort($this->unmappedExams);
        $this->line('Unmatched doctors ('.count($this->unmatchedDoctors).'): '.collect($this->unmatchedDoctors)->take(40)->map(fn ($n, $name) => "{$name} ({$n})")->implode('، '));
        $this->line('Unmapped exams: '.collect($this->unmappedExams)->map(fn ($n, $name) => "{$name} ({$n})")->implode(', '));

        return self::SUCCESS;
    }

    /** @return list<array<string, mixed>> */
    private function parse(string $path): array
    {
        $handle = fopen($path, 'rb');
        if (! $handle) {
            return [];
        }
        $doctors = Doctor::with('user')->get()->mapWithKeys(fn (Doctor $d) => [self::normalize($d->user->name) => $d->id])->filter(fn ($id, $name) => $name !== '');
        $exams = ExamType::pluck('id', 'name')->mapWithKeys(fn ($id, $name) => [trim((string) $name) => $id]);
        $currency = Setting::get('currency');
        $header = null;
        $rows = [];
        while (($cells = fgetcsv($handle, escape: '')) !== false) {
            $cells = array_map(fn ($c) => trim((string) $c), $cells);
            if ($header === null) {
                if (($cells[1] ?? '') === 'اسم الحاله') {
                    $header = $cells;
                }

                continue;
            }
            $name = preg_replace('/\s+/u', ' ', $cells[1] ?? '') ?? '';
            if ($name === '' || mb_strlen($name) < 3) {
                $this->count($name === '' ? 'skipped: empty' : 'skipped: junk name');

                continue;
            }
            $date = self::date($cells[3] ?? '');
            if (! $date) {
                $this->count('skipped: bad date');

                continue;
            }
            $notes = [];
            $doctorName = preg_replace('/\s+/u', ' ', $cells[6] ?? '') ?? '';
            $doctorId = $this->doctor($doctorName, $doctors->all());
            if ($doctorName !== '' && ! $doctorId) {
                $notes[] = 'الطبيب: '.$doctorName;
                $this->unmatchedDoctors[$doctorName] = ($this->unmatchedDoctors[$doctorName] ?? 0) + 1;
            }
            $this->count($doctorId ? 'doctor matched' : ($doctorName === '' ? 'no doctor' : 'doctor unmatched'));

            $examText = $cells[7] ?? '';
            $examId = $this->exam($examText, $exams->all());
            if ($examText !== '') {
                $notes[] = 'الفحص: '.$examText;
            }
            $this->count($examId ? 'exam mapped' : ($examText === '' ? 'no exam' : 'exam unmapped'));

            $priceText = str_replace(',', '', $cells[8] ?? '');
            $price = $currency && preg_match('/^\d{1,9}(\.\d{1,2})?$/', $priceText) ? (int) round((float) $priceText * 100) : null;
            if ($price === null && $priceText !== '') {
                $notes[] = 'السعر: '.$priceText;
            }
            $remaining = preg_match('/^\d{1,9}(\.\d{1,2})?$/', $cells[9] ?? '') ? (int) round((float) $cells[9] * 100) : 0;
            if ($remaining > 0) {
                $notes[] = 'المتبقي: '.$cells[9];
            }
            foreach ($header as $i => $title) {
                if (in_array($title, ['Documents', 'المصروفات', 'اسم المصروف'], true) && ($cells[$i] ?? '') !== '') {
                    $notes[] = $title.': '.$cells[$i];
                }
            }

            $phone = preg_replace('/\D+/', '', $cells[4] ?? '') ?? '';
            if (str_starts_with($phone, '1') && (strlen($phone) === 9 || strlen($phone) === 10)) {
                $phone = '0'.$phone;
            }
            $age = $cells[2] ?? '';
            $birth = ctype_digit($age) && (int) $age <= 120 ? $date->subYears((int) $age)->startOfYear()->toDateString() : null;
            $code = preg_match('/^PG-\d+$/i', $cells[0]) ? strtoupper($cells[0]) : null;

            $rows[] = [
                'code' => $code, 'name' => $name, 'phone' => $phone !== '' ? Str::limit($phone, 30, '') : null, 'birth_date' => $birth,
                'patient_key' => self::normalize($name).'|'.($phone !== '' ? $phone : $age),
                'date' => $date->toDateString(), 'doctor_id' => $doctorId, 'exam_type_id' => $examId, 'notes' => implode("\n", $notes),
                'price' => $price, 'paid' => $price === null ? 0 : max($price - $remaining, 0), 'currency' => $currency,
                'status' => ($cells[10] ?? '') === 'لم يتم' ? WorkflowStatus::New : WorkflowStatus::Completed,
            ];
        }
        fclose($handle);

        return $rows;
    }

    private function wipe(): void
    {
        $files = CaseFile::withTrashed()->get(['disk', 'path', 'staging_path']);
        DB::transaction(function () {
            Payment::query()->update(['adjustment_of_id' => null]);
            Payment::query()->delete();
            UploadSession::query()->delete();
            ActivityLog::whereNotNull('medical_case_id')->delete();
            CaseFile::withTrashed()->forceDelete();
            MedicalCase::withoutGlobalScope('access')->withTrashed()->forceDelete();
            DB::table('branch_patient')->delete();
            Patient::withoutGlobalScope('access')->withTrashed()->forceDelete();
        });
        foreach ($files as $file) {
            if ($file->disk !== 'drive' && $file->path) {
                Storage::disk($file->disk)->delete($file->path);
            }
            if ($file->staging_path) {
                Storage::disk('local')->delete($file->staging_path);
            }
        }
        $this->warn('Wiped '.$files->count().' file records and all cases, patients and payments.');
    }

    /** @param array<string, int> $doctors */
    private function doctor(string $name, array $doctors): ?int
    {
        $key = self::normalize($name);
        if ($key === '') {
            return null;
        }
        if (isset($doctors[$key])) {
            return $doctors[$key];
        }
        $compact = fn (string $v) => str_replace(' ', '', $v);
        $words = explode(' ', $key);
        $rules = [
            fn (string $full) => $compact($full) === $compact($key),
            fn (string $full) => str_starts_with($full.' ', $key.' ') || str_starts_with($key.' ', $full.' '),
            fn (string $full) => count($words) >= 2 && str_starts_with($full, $words[0].' ') && str_ends_with($full, ' '.end($words)),
            fn (string $full) => mb_strlen($compact($key)) >= 8 && levenshtein($compact($full), $compact($key)) <= 2,
        ];
        foreach ($rules as $rule) {
            $matches = array_values(array_unique(array_filter($doctors, fn ($id, $full) => $rule((string) $full), ARRAY_FILTER_USE_BOTH)));
            if (count($matches) === 1) {
                return $matches[0];
            }
            if (count($matches) > 1) {
                return null;
            }
        }

        return null;
    }

    /** @param array<string, int> $exams */
    private function exam(string $text, array $exams): ?int
    {
        $haystack = ' '.Str::lower($text);
        foreach (self::EXAMS as $keyword => $name) {
            if (str_contains($haystack, $keyword) && isset($exams[$name])) {
                return $exams[$name];
            }
        }
        if ($text !== '') {
            $this->unmappedExams[$text] = ($this->unmappedExams[$text] ?? 0) + 1;
        }

        return null;
    }

    private static function date(string $value): ?CarbonImmutable
    {
        $value = preg_replace('/\s+/', '', str_replace('\\', '/', $value)) ?? '';
        // Typos like 0226-06-01 or 0206-05-21 in the sheet mean 2026.
        $value = preg_replace('/^02\d{2}(-\d{2}-\d{2})$/', '2026$1', $value) ?? $value;
        foreach (['!Y-m-d', '!d/m/Y', '!d-m-Y'] as $format) {
            try {
                $date = CarbonImmutable::createFromFormat($format, $value);
            } catch (\Throwable) {
                continue;
            }
            $errors = \DateTimeImmutable::getLastErrors();
            if ($date && $date->year >= 2020 && $date->year <= 2030 && ! ($errors['warning_count'] ?? 0)) {
                return $date;
            }
        }

        return null;
    }

    public static function normalize(string $value): string
    {
        $value = Str::of($value)->trim()->replaceMatches('/\s+/u', ' ')->replace(['أ', 'إ', 'آ', 'ة', 'ى'], ['ا', 'ا', 'ا', 'ه', 'ي'])->replaceMatches('/^(د\s*[\/.]\s*|د\s+|دكتور\s+|دكتوره\s+)/u', '')->trim();

        return (string) $value;
    }

    private function count(string $key): void
    {
        $this->stats[$key] = ($this->stats[$key] ?? 0) + 1;
    }
}
