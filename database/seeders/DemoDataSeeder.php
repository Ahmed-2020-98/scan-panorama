<?php

namespace Database\Seeders;

use App\Enums\CaseFileType;
use App\Enums\Role;
use App\Models\Branch;
use App\Models\Doctor;
use App\Models\ExamType;
use App\Models\MedicalCase;
use App\Models\Patient;
use App\Models\User;
use Carbon\CarbonImmutable;
use Database\Seeders\Support\SampleFiles;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Demo accounts, patients and cases with sample files.
 * All names are generated — never real patient data.
 */
class DemoDataSeeder extends Seeder
{
    /** @var array<string, string> */
    private array $radiographs = [];

    public function run(): void
    {
        $maadi = Branch::where('name', 'المعادي')->firstOrFail();
        $shubra = Branch::where('name', 'شبرا')->firstOrFail();

        User::factory()->admin()->create([
            'name' => 'مدير النظام',
            'email' => 'admin@scan4dent.test',
            'phone' => '01000000001',
        ]);

        $reception = User::factory()->reception()->create([
            'name' => 'منة الله - الاستقبال',
            'email' => 'reception@scan4dent.test',
            'phone' => '01000000002',
            'branch_id' => $maadi->id,
        ]);

        User::factory()->create(['role' => Role::Technician, 'branch_id' => $maadi->id, 'name' => 'فني الأشعة التجريبي', 'phone' => '01000000006', 'email' => 'technician@scan4dent.test']);

        $doctor = $this->doctor('أحمد سامي عبد الله', 'أ.د.', 'ma002', 'doctor@scan4dent.test', '01000000003', 'زراعة الأسنان', [$maadi, $shubra]);
        $doctorTwo = $this->doctor('منى عادل رشاد', 'د.', 'mo015', 'doctor2@scan4dent.test', '01000000004', 'علاج الجذور', [$maadi]);
        $this->doctor('كريم فؤاد حسن', 'د.', 'kf021', 'doctor3@scan4dent.test', '01000000005', 'تقويم الأسنان', [$shubra]);

        $patients = Patient::factory()->count(52)->create(['created_by' => $reception->id]);

        $this->cases($doctor, $patients->slice(0, 38)->values(), 42, $maadi, $shubra, $reception);
        $this->cases($doctorTwo, $patients->slice(34)->values(), 16, $maadi, $maadi, $reception);
    }

    /**
     * @param  list<Branch>  $branches
     */
    private function doctor(string $name, string $title, string $code, string $email, string $phone, string $specialty, array $branches): Doctor
    {
        $user = User::factory()->doctor()->create(['name' => $name, 'email' => $email, 'phone' => $phone]);

        $doctor = Doctor::create([
            'user_id' => $user->id,
            'code' => $code,
            'title' => $title,
            'specialty' => $specialty,
        ]);

        $doctor->branches()->sync(collect($branches)->pluck('id'));

        return $doctor;
    }

    /**
     * @param  Collection<int, Patient>  $patients
     */
    private function cases(Doctor $doctor, $patients, int $count, Branch $main, Branch $secondary, User $reception): void
    {
        $examTypes = ExamType::all()->keyBy('name');
        $weights = [
            '2D - panorama' => 50,
            '3D Endo Mode - Endo mode' => 15,
            '3D CBCT - Quadrant' => 12,
            '2D - cephalometric' => 6,
            '3D CBCT - Both Arches' => 5,
            '3D CBCT - Maxilla' => 4,
            '3D CBCT - Mandible' => 4,
            '3D CBCT - Segment' => 4,
        ];

        $dates = collect(range(1, $count))
            ->map(fn () => CarbonImmutable::instance(fake()->dateTimeBetween('-23 months', '-1 day')))
            ->sort()
            ->values();

        foreach ($dates as $index => $date) {
            $examType = $examTypes[$this->weighted($weights)];
            $recent = $index >= $count - 3;

            $case = MedicalCase::create([
                'patient_id' => $patients[$index % $patients->count()]->id,
                'doctor_id' => $doctor->id,
                'branch_id' => fake()->boolean(88) ? $main->id : $secondary->id,
                'exam_type_id' => $examType->id,
                'exam_date' => $date,
                'notes_for_doctor' => fake()->boolean(25) ? 'تم التصوير بدقة عالية، يرجى مراجعة منطقة الضرس الأخير.' : null,
                'created_by' => $reception->id,
            ]);

            $case->forceFill(['created_at' => $date, 'updated_at' => $date])->saveQuietly();

            // The most recent cases are still in progress (no files yet)
            if ($recent && $index === $count - 1) {
                continue;
            }

            $this->attachFiles($case, $examType, $reception, withDicom: ! $recent);

            if (! $recent) {
                $opened = fake()->boolean(80);
                $case->forceFill([
                    'shared_at' => $date->addHours(2),
                    'first_opened_at' => $opened ? $date->addHours(fake()->numberBetween(3, 48)) : null,
                    'last_opened_at' => $opened ? $date->addDays(fake()->numberBetween(2, 20)) : null,
                    'open_count' => $opened ? fake()->numberBetween(1, 6) : 0,
                ])->saveQuietly();
            }
        }
    }

    private function attachFiles(MedicalCase $case, ExamType $examType, User $uploader, bool $withDicom): void
    {
        $threeD = $examType->category === '3D';
        $label = $examType->name;

        $this->radiographs[$label] ??= SampleFiles::radiograph($label, $threeD);

        $this->store($case, CaseFileType::Report, 'image.png', $this->radiographs[$label], 'image/png', $uploader);

        if ($threeD || fake()->boolean(40)) {
            $pdf = SampleFiles::reportPdf([
                'Radiology Report (Sample)',
                'Case: '.$case->case_code,
                'Exam: '.$label,
                'Date: '.$case->exam_date->format('Y-m-d'),
                'Findings: demo text only, not a medical report.',
            ]);
            $this->store($case, CaseFileType::Report, 'report.pdf', $pdf, 'application/pdf', $uploader);
        }

        $this->store($case, CaseFileType::Referral, 'referral.png', SampleFiles::referral($label, $case->case_code), 'image/png', $uploader);

        if ($withDicom) {
            $this->store($case, CaseFileType::Dicom, $case->case_code.'-dicom.zip', SampleFiles::dicomZip($case->case_code), 'application/zip', $uploader);
        }
    }

    private function store(MedicalCase $case, CaseFileType $type, string $name, string $contents, string $mime, User $uploader): void
    {
        $extension = pathinfo($name, PATHINFO_EXTENSION);
        $path = "cases/{$case->id}/{$type->value}/".Str::uuid().'.'.$extension;

        Storage::disk(config('radiology.disk'))->put($path, $contents);

        $case->files()->create([
            'type' => $type,
            'original_name' => $name,
            'path' => $path,
            'mime' => $mime,
            'size' => strlen($contents),
            'sha256' => hash('sha256', $contents),
            'uploaded_by' => $uploader->id,
        ]);
    }

    /**
     * @param  array<string, int>  $weights
     */
    private function weighted(array $weights): string
    {
        $roll = random_int(1, array_sum($weights));

        foreach ($weights as $value => $weight) {
            if (($roll -= $weight) <= 0) {
                return $value;
            }
        }

        return array_key_first($weights);
    }
}
