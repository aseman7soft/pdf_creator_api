<?php

declare(strict_types=1);

use App\Http\Controllers\Admin\ApiKeyController;
use App\Http\Controllers\Admin\ApiProjectController;
use App\Http\Controllers\Admin\AssetController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\DocumentController;
use App\Http\Controllers\Admin\LogController;
use App\Http\Controllers\Admin\QueueController;
use App\Http\Controllers\Admin\SettingController;
use App\Http\Controllers\Admin\TemplateController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Auth\LoginController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web routes (admin panel)
|--------------------------------------------------------------------------
|
| Every panel route lives under the /admin prefix and uses the `admin.*`
| route name namespace. Guests are redirected to /admin/login by the
| `redirectGuestsTo` callback configured in bootstrap/app.php.
|
*/

Route::get('/', fn () => redirect('/admin'));

Route::get('/health', fn () => response()->json([
    'status' => 'ok',
    'service' => 'pdf-service',
    'time' => now()->toIso8601String(),
]));

Route::prefix('admin')->name('admin.')->group(function (): void {
    // -------------------------------------------------------------- auth
    Route::get('login', [LoginController::class, 'showLoginForm'])->name('login');
    Route::post('login', [LoginController::class, 'login'])
        ->middleware('throttle:5,1')
        ->name('login.attempt');
    Route::post('logout', [LoginController::class, 'logout'])
        ->middleware('auth')
        ->name('logout');

    Route::middleware(['auth', 'active.user'])->group(function (): void {
        Route::get('/', DashboardController::class)->name('dashboard');

        // ------------------------------------------------------- templates
        Route::get('templates', [TemplateController::class, 'index'])->name('templates.index');
        Route::get('templates/create', [TemplateController::class, 'create'])->name('templates.create');
        Route::post('templates', [TemplateController::class, 'store'])->name('templates.store');

        Route::get('templates/{template}/edit', [TemplateController::class, 'edit'])->name('templates.edit');
        Route::put('templates/{template}', [TemplateController::class, 'update'])->name('templates.update');
        Route::delete('templates/{template}', [TemplateController::class, 'destroy'])->name('templates.destroy');
        Route::post('templates/{template}/toggle', [TemplateController::class, 'toggle'])->name('templates.toggle');
        Route::post('templates/{template}/copy', [TemplateController::class, 'copy'])->name('templates.copy');

        Route::get('templates/{template}/versions', [TemplateController::class, 'versions'])->name('templates.versions');
        Route::get('templates/{template}/versions/{version}', [TemplateController::class, 'showVersion'])
            ->name('templates.versions.show');
        Route::post('templates/{template}/versions/{version}/revert', [TemplateController::class, 'revertVersion'])
            ->name('templates.versions.revert');

        Route::get('templates/{template}/preview', [TemplateController::class, 'preview'])->name('templates.preview');
        Route::post('templates/{template}/preview/html', [TemplateController::class, 'previewHtml'])
            ->name('templates.preview.html');
        Route::post('templates/{template}/preview/pdf', [TemplateController::class, 'previewPdf'])
            ->name('templates.preview.pdf');

        // -------------------------------------------------------- projects
        Route::get('projects', [ApiProjectController::class, 'index'])->name('projects.index');
        Route::get('projects/create', [ApiProjectController::class, 'create'])->name('projects.create');
        Route::post('projects', [ApiProjectController::class, 'store'])->name('projects.store');

        Route::get('projects/{project}', [ApiProjectController::class, 'show'])->name('projects.show');
        Route::get('projects/{project}/edit', [ApiProjectController::class, 'edit'])->name('projects.edit');
        Route::put('projects/{project}', [ApiProjectController::class, 'update'])->name('projects.update');
        Route::delete('projects/{project}', [ApiProjectController::class, 'destroy'])->name('projects.destroy');
        Route::post('projects/{project}/toggle', [ApiProjectController::class, 'toggle'])->name('projects.toggle');

        Route::post('projects/{project}/keys', [ApiKeyController::class, 'store'])->name('projects.keys.store');
        Route::post('projects/{project}/keys/{apiKey}/rotate', [ApiKeyController::class, 'rotate'])
            ->name('projects.keys.rotate');
        Route::post('projects/{project}/keys/{apiKey}/revoke', [ApiKeyController::class, 'revoke'])
            ->name('projects.keys.revoke');
        Route::delete('projects/{project}/keys/{apiKey}', [ApiKeyController::class, 'destroy'])
            ->name('projects.keys.destroy');

        // ------------------------------------------------------- documents
        Route::get('documents', [DocumentController::class, 'index'])->name('documents.index');
        Route::get('documents/{document}', [DocumentController::class, 'show'])->name('documents.show');
        Route::get('documents/{document}/download', [DocumentController::class, 'download'])
            ->name('documents.download');
        Route::get('documents/{document}/download/signed', [DocumentController::class, 'downloadSigned'])
            ->middleware('signed')
            ->name('documents.download.signed');
        Route::post('documents/{document}/retry', [DocumentController::class, 'retry'])->name('documents.retry');
        Route::delete('documents/{document}', [DocumentController::class, 'destroy'])->name('documents.destroy');

        // ----------------------------------------------------------- queue
        Route::get('queue', [QueueController::class, 'index'])->name('queue.index');
        Route::post('queue/jobs/{job}/retry', [QueueController::class, 'retry'])->name('queue.retry');
        Route::delete('queue/jobs/{job}', [QueueController::class, 'destroy'])->name('queue.destroy');

        Route::post('queue/failed/flush', [QueueController::class, 'flushFailed'])->name('queue.failed.flush');
        Route::post('queue/failed/{uuid}/retry', [QueueController::class, 'retryFailed'])->name('queue.failed.retry');
        Route::delete('queue/failed/{uuid}', [QueueController::class, 'forgetFailed'])->name('queue.failed.forget');

        // ------------------------------------------------------------ logs
        Route::get('logs', [LogController::class, 'index'])->name('logs.index');
        Route::get('logs/app', [LogController::class, 'appLog'])->name('logs.app');
        Route::delete('logs/purge', [LogController::class, 'purge'])->name('logs.purge');
        Route::get('logs/{log}', [LogController::class, 'show'])->name('logs.show');

        // ---------------------------------------------------------- assets
        Route::get('assets', [AssetController::class, 'index'])->name('assets.index');
        Route::post('assets', [AssetController::class, 'store'])->name('assets.store');
        Route::get('assets/{asset}/file', [AssetController::class, 'file'])->name('assets.file');
        Route::get('assets/{asset}/download', [AssetController::class, 'download'])->name('assets.download');
        Route::delete('assets/{asset}', [AssetController::class, 'destroy'])->name('assets.destroy');

        // -------------------------------------------------------- settings
        Route::get('settings', [SettingController::class, 'index'])->name('settings.index');
        Route::put('settings', [SettingController::class, 'update'])->name('settings.update');
        Route::post('settings/test-gotenberg', [SettingController::class, 'testGotenberg'])
            ->name('settings.test-gotenberg');

        // ----------------------------------------------------------- users
        Route::get('users', [UserController::class, 'index'])->name('users.index');
        Route::get('users/create', [UserController::class, 'create'])->name('users.create');
        Route::post('users', [UserController::class, 'store'])->name('users.store');
        Route::get('users/{user}/edit', [UserController::class, 'edit'])->name('users.edit');
        Route::put('users/{user}', [UserController::class, 'update'])->name('users.update');
        Route::post('users/{user}/toggle', [UserController::class, 'toggle'])->name('users.toggle');
        Route::post('users/{user}/password', [UserController::class, 'resetPassword'])->name('users.password');
        Route::delete('users/{user}', [UserController::class, 'destroy'])->name('users.destroy');
    });
});
