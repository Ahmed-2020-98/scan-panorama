<?php

return [
    'client_id' => env('GOOGLE_DRIVE_CLIENT_ID'),
    'client_secret' => env('GOOGLE_DRIVE_CLIENT_SECRET'),
    'redirect_uri' => env('GOOGLE_DRIVE_REDIRECT_URI'),
    'staging_days' => 7,
    'chunk_bytes' => 4 * 1024 * 1024,
];
