<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Case Files Storage
    |--------------------------------------------------------------------------
    |
    | Medical files are always stored on a private disk and streamed through
    | authorized controllers. Switch to an S3-compatible disk in production
    | by pointing CASE_FILES_DISK at it.
    |
    */

    'disk' => env('CASE_FILES_DISK', 'local'),
    'upload_provider' => env('CASE_UPLOAD_PROVIDER', 'drive'),

    /*
    |--------------------------------------------------------------------------
    | Upload Limits
    |--------------------------------------------------------------------------
    |
    | Maximum size per file type in megabytes. Files are uploaded in chunks,
    | so these limits are independent from PHP's upload_max_filesize.
    |
    */

    'max_upload_mb' => [
        'report' => (int) env('MAX_DOCUMENT_UPLOAD_MB', 50),
        'image' => (int) env('MAX_DOCUMENT_UPLOAD_MB', 50),
        'video' => (int) env('MAX_VIDEO_UPLOAD_MB', 2048),
        'referral' => (int) env('MAX_DOCUMENT_UPLOAD_MB', 50),
        'dicom' => (int) env('MAX_DICOM_UPLOAD_MB', 2048),
    ],

    'max_chunk_mb' => 8,

    'stale_chunks_hours' => 24,

    /*
    |--------------------------------------------------------------------------
    | Center Defaults
    |--------------------------------------------------------------------------
    |
    | Initial values for the editable center settings (Settings page).
    |
    */

    'defaults' => [
        'center_name' => 'Scan4Dent',
        'center_tagline' => 'مركز أشعة الأسنان والوجه والفكين',
        'contact_phone' => '01000000000',
        'support_whatsapp' => '01000000000',
        'tutorial_url' => '',
        'share_link_days' => 30,
    ],

];
