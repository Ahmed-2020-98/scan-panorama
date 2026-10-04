<?php

namespace App\Services\Drive;

use App\Enums\CaseFileType;
use App\Models\DriveConnection;
use App\Models\DriveFolder;
use App\Models\MedicalCase;
use Illuminate\Support\Facades\Cache;

class DriveFolderResolver
{
    public function __construct(private DriveClient $client) {}

    public function folder(string $key, string $name, ?string $parent): string
    {
        return Cache::lock('drive-folder-'.hash('sha256', $key), 180)->block(15, function () use ($key, $name, $parent) {
            $folder = DriveFolder::where('logical_key', $key)->first();
            if (! $folder) {
                $folder = DriveFolder::create(['logical_key' => $key, 'provider_id' => $this->client->newId()]);
            }
            $metadata = ['id' => $folder->provider_id, 'name' => $name, 'mimeType' => 'application/vnd.google-apps.folder'];
            if ($parent) {
                $metadata['parents'] = [$parent];
            }
            $this->client->create($metadata);

            return $folder->provider_id;
        });
    }

    public function caseFolder(MedicalCase $case, CaseFileType $type): string
    {
        $connection = DriveConnection::firstOrFail();
        $prefix = 'connection-'.$connection->id;
        $root = $connection->root_folder_id ?? $this->folder($prefix.'/root', 'Radiology Center', $connection->shared_drive_id);
        if (! $connection->root_folder_id) {
            $connection->update(['root_folder_id' => $root]);
        }
        $branch = $this->folder($prefix.'/branch-'.$case->branch_id, $case->branch->name.' - '.$case->branch_id, $root);
        $year = $this->folder($prefix.'/branch-'.$case->branch_id.'/year-'.$case->exam_date->year, (string) $case->exam_date->year, $branch);
        $patient = $this->folder($prefix.'/branch-'.$case->branch_id.'/year-'.$case->exam_date->year.'/patient-'.$case->patient_id, $case->patient->name.' - '.$case->patient->file_number, $year);
        $folder = $this->folder($prefix.'/branch-'.$case->branch_id.'/year-'.$case->exam_date->year.'/patient-'.$case->patient_id.'/case-'.$case->id, $case->case_code, $patient);
        $name = match ($type) {
            CaseFileType::Image => 'Images',CaseFileType::Report => 'Reports',CaseFileType::Dicom => 'DICOM',CaseFileType::Video => 'Videos',CaseFileType::Referral => 'Requests'
        };

        return $this->folder($prefix.'/branch-'.$case->branch_id.'/year-'.$case->exam_date->year.'/patient-'.$case->patient_id.'/case-'.$case->id.'/'.$name, $name, $folder);
    }
}
