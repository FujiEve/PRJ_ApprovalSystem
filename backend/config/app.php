<?php

return [
    'name' => env('APP_NAME', 'ApprovalSystem'),
    'env' => env('APP_ENV', 'local'),
    'debug' => (bool) env('APP_DEBUG', true),
    'url' => env('APP_URL', 'http://localhost:8080'),
    'timezone' => 'Asia/Tokyo',
    'locale' => 'ja',
    'fallback_locale' => 'en',
    'faker_locale' => 'ja_JP',
    'cipher' => 'AES-256-CBC',
    'key' => env('APP_KEY', 'base64:u4q9V0G5K9q8W1z3X2y7P6m4L5n8B0v1C2x3Z4a5S6d='),
    'previous_keys' => [],
    'maintenance' => [
        'driver' => 'file',
    ],
];

