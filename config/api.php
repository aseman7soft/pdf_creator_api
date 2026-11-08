<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | API version and prefix
    |--------------------------------------------------------------------------
    */

    'version' => 'v1',
    'prefix' => 'api/v1',

    /*
    |--------------------------------------------------------------------------
    | Authentication
    |--------------------------------------------------------------------------
    |
    | API keys are stored as keyed hashes (HMAC-SHA256 with APP_KEY). The plain
    | key is shown exactly once, at creation time.
    |
    */

    'key_prefix' => env('API_KEY_PREFIX', 'pdfsvc'),
    'key_bytes' => 32,

    /*
    |--------------------------------------------------------------------------
    | Key hashing salt
    |--------------------------------------------------------------------------
    |
    | API keys are hashed with HMAC-SHA256. Keep this salt stable across
    | deployments; rotating it invalidates every existing API key.
    |
    */

    'hash_salt' => env('API_KEY_HASH_SALT') ?: env('APP_KEY'),

    /*
    |--------------------------------------------------------------------------
    | Rate limiting
    |--------------------------------------------------------------------------
    */

    'rate_limit_per_minute' => (int) env('API_RATE_LIMIT_PER_MINUTE', 60),

    /*
    |--------------------------------------------------------------------------
    | Idempotency
    |--------------------------------------------------------------------------
    */

    'idempotency_ttl_hours' => (int) env('API_IDEMPOTENCY_TTL_HOURS', 24),

    /*
    |--------------------------------------------------------------------------
    | Request limits
    |--------------------------------------------------------------------------
    */

    'max_payload_kb' => (int) env('PDF_MAX_PAYLOAD_KB', 1024),

];
