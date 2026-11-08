<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Document storage
    |--------------------------------------------------------------------------
    |
    | Generated PDF files are stored on a private disk. They are never exposed
    | through the public web root; downloads always go through an authorised
    | controller action or a temporary signed URL.
    |
    */

    'disk' => env('PDF_DISK', 'local'),
    'document_path' => trim((string) env('PDF_DOCUMENT_PATH', 'documents'), '/'),
    'signed_url_ttl' => (int) env('PDF_SIGNED_URL_TTL', 30),
    'retention_days' => (int) env('PDF_DOCUMENT_RETENTION_DAYS', 30),

    /*
    |--------------------------------------------------------------------------
    | Limits
    |--------------------------------------------------------------------------
    */

    'max_payload_kb' => (int) env('PDF_MAX_PAYLOAD_KB', 1024),
    'max_template_bytes' => (int) env('PDF_MAX_TEMPLATE_BYTES', 2 * 1024 * 1024),
    'max_upload_kb' => (int) env('PDF_MAX_UPLOAD_KB', 5120),

    /*
    |--------------------------------------------------------------------------
    | Default render options
    |--------------------------------------------------------------------------
    */

    'default_format' => env('PDF_DEFAULT_FORMAT', 'A4'),
    'default_orientation' => env('PDF_DEFAULT_ORIENTATION', 'portrait'),

    'default_margins' => [
        'top' => 12,
        'right' => 12,
        'bottom' => 12,
        'left' => 12,
    ],

    /*
    |--------------------------------------------------------------------------
    | Gotenberg (Chromium) engine
    |--------------------------------------------------------------------------
    |
    | Gotenberg runs on loopback only. Credentials are not required for the
    | internal service and must never be exposed to API consumers.
    |
    */

    'gotenberg' => [
        'url' => rtrim((string) env('GOTENBERG_URL', 'http://127.0.0.1:3000'), '/'),
        'connect_timeout' => (int) env('GOTENBERG_CONNECT_TIMEOUT', 10),
        'timeout' => (int) env('GOTENBERG_TIMEOUT', 90),
        'retry_times' => (int) env('GOTENBERG_RETRY_TIMES', 1),
        'retry_sleep_ms' => (int) env('GOTENBERG_RETRY_SLEEP_MS', 500),
        'convert_path' => '/forms/chromium/convert/html',
        'health_path' => '/health',
    ],

    /*
    |--------------------------------------------------------------------------
    | Queues
    |--------------------------------------------------------------------------
    */

    'queue' => [
        'connection' => env('PDF_QUEUE_CONNECTION') ?: null,
        'name' => env('PDF_QUEUE_NAME', 'documents'),
        'tries' => (int) env('PDF_QUEUE_TRIES', 3),
        'timeout' => (int) env('PDF_QUEUE_TIMEOUT', 180),
        'backoff' => (int) env('PDF_QUEUE_BACKOFF', 15),
    ],

    /*
    |--------------------------------------------------------------------------
    | Security
    |--------------------------------------------------------------------------
    |
    | Templates may only reference local assets (asset://handle) or inline
    | data URIs. External network addresses are blocked unless explicitly
    | allowed for image variables, and even then private/reserved IPs are
    | rejected.
    |
    */

    'security' => [
        'allow_remote_image_variables' => (bool) env('PDF_ALLOW_REMOTE_IMAGES', true),
        'max_remote_image_bytes' => (int) env('PDF_MAX_REMOTE_IMAGE_BYTES', 5 * 1024 * 1024),
        'remote_image_timeout' => (int) env('PDF_REMOTE_IMAGE_TIMEOUT', 10),
        'allowed_image_mimes' => [
            'image/png',
            'image/jpeg',
            'image/webp',
            'image/gif',
        ],
        'allowed_font_mimes' => [
            'font/ttf',
            'font/otf',
            'font/woff',
            'font/woff2',
            'application/font-woff',
            'application/x-font-ttf',
            'application/vnd.ms-opentype',
        ],
        'allowed_css_mimes' => [
            'text/css',
            'text/plain',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Page formats (CSS sizes, in millimetres)
    |--------------------------------------------------------------------------
    */

    'page_formats' => [
        'A4' => ['width' => 210, 'height' => 297],
        'A3' => ['width' => 297, 'height' => 420],
        'A5' => ['width' => 148, 'height' => 210],
        'Letter' => ['width' => 215.9, 'height' => 279.4],
        'Legal' => ['width' => 215.9, 'height' => 355.6],
    ],

];
