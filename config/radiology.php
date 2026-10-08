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

    /*
    |--------------------------------------------------------------------------
    | Interface fonts
    |--------------------------------------------------------------------------
    |
    | Fonts each user can pick under Settings > Appearance. The key is stored
    | on the user; "stack" becomes the sans/display font stack. Every family
    | here must also be registered in vite.config.js.
    |
    */

    /*
    | Days a deleted doctor stays restorable in the recycle bin. Afterwards the
    | doctor is purged (with the login) if nothing references them; doctors with
    | cases or visits stay archived so case history keeps the name.
    */
    'doctor_restore_days' => 30,

    'default_font' => 'tajawal',

    'fonts' => [
        'tajawal' => ['label' => 'Tajawal', 'note' => 'خط الهوية البصرية', 'stack' => "'Montserrat', 'Tajawal'"],
        'cairo' => ['label' => 'Cairo', 'note' => 'عريض وواضح', 'stack' => "'Cairo', 'Tajawal'"],
        'plex' => ['label' => 'IBM Plex Sans Arabic', 'note' => 'رسمي ومريح للقراءة الطويلة', 'stack' => "'IBM Plex Sans Arabic', 'Tajawal'"],
        'almarai' => ['label' => 'Almarai', 'note' => 'بسيط ومستدير', 'stack' => "'Almarai', 'Tajawal'"],
        'readex' => ['label' => 'Readex Pro', 'note' => 'حديث ومتباعد الحروف', 'stack' => "'Readex Pro', 'Tajawal'"],
    ],

    'defaults' => [
        'center_name' => 'Scan Panorama',
        'center_tagline' => 'مركز الأشعة السنية والفكية',
        'contact_phone' => '01000000000',
        'support_whatsapp' => '01000000000',
        'tutorial_url' => '',
        'share_link_days' => 30,
    ],

];
