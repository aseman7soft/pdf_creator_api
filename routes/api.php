<?php

declare(strict_types=1);

use App\Http\Controllers\Api\DocsController;
use App\Http\Controllers\Api\DocumentController;
use App\Http\Controllers\Api\HealthController;
use App\Http\Controllers\Api\TemplateController;
use App\Http\Middleware\ApiRateLimiter;
use App\Http\Middleware\AuthenticateApiKey;
use App\Http\Middleware\EnsureApiPayloadLimit;
use App\Http\Middleware\ForceJsonResponse;
use App\Http\Middleware\LogApiRequest;
use App\Http\Middleware\ValidateApiSignature;
use App\Services\Api\ApiResponseService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API v1 routes
|--------------------------------------------------------------------------
|
| The /api prefix is applied by the framework. Every route below is therefore
| reachable under /api/v1. Authentication, rate limiting, payload limits and
| request logging are registered here explicitly (no bootstrap changes).
|
| Note: Laravel's routing pipeline renders exceptions at the innermost slice,
| so controllers convert unexpected throwables into App\Exceptions\ApiException
| (which renders the canonical error envelope) instead of relying on a route
| middleware catch-all.
|
*/

Route::prefix('v1')
    ->name('api.')
    ->middleware([
        LogApiRequest::class,
        ForceJsonResponse::class,
    ])
    ->group(function (): void {
        // Public endpoints (no API key required).
        Route::get('/health', [HealthController::class, 'show'])->name('health');
        Route::get('/docs', [DocsController::class, 'docs'])->name('docs');
        Route::get('/openapi.yaml', [DocsController::class, 'openapi'])->name('openapi');

        // Temporary signed download: the signature is the credential, so this
        // route is registered before the Bearer protected group and must not be
        // shadowed by any /documents/{ulid} wildcard route.
        Route::get('/documents/{ulid}/download/signed', [DocumentController::class, 'signedDownload'])
            ->middleware(ValidateApiSignature::class)
            ->name('documents.download.signed');

        Route::middleware([
            AuthenticateApiKey::class,
            ApiRateLimiter::class,
            EnsureApiPayloadLimit::class,
        ])->group(function (): void {
            Route::get('/templates', [TemplateController::class, 'index'])->name('templates.index');
            Route::get('/templates/{slug}', [TemplateController::class, 'show'])
                ->where('slug', '[A-Za-z0-9\-_]+')
                ->name('templates.show');

            Route::post('/documents/generate', [DocumentController::class, 'generate'])->name('documents.generate');
            Route::post('/documents/queue', [DocumentController::class, 'queue'])->name('documents.queue');

            Route::get('/documents/{ulid}/status', [DocumentController::class, 'status'])->name('documents.status');
            Route::get('/documents/{ulid}/download', [DocumentController::class, 'download'])->name('documents.download');
        });

        // Unknown /api/v1/* paths still receive the standard JSON envelope.
        Route::fallback(static function (): JsonResponse {
            return ApiResponseService::error('NOT_FOUND', 'مسیر درخواستی یافت نشد.', [], 404);
        })->name('fallback');
    });
