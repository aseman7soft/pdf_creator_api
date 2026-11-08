<?php

declare(strict_types=1);
use App\Models\ApiKey;
use App\Models\ApiProject;
use App\Services\Api\ApiKeyService;
use Illuminate\Contracts\Console\Kernel;

/**
 * Development helper: issue an API key for manual/E2E testing.
 *
 * Usage:
 *   php tools/dev/create-test-key.php [project-code] [key-name]
 *
 * Prints ONLY the plain key on stdout so it can be captured by scripts. This
 * script is a local development utility and is not part of the application.
 */

require __DIR__.'/../../vendor/autoload.php';

$app = require __DIR__.'/../../bootstrap/app.php';

/** @var Kernel $kernel */
$kernel = $app->make(Kernel::class);
$kernel->bootstrap();

$projectCode = $argv[1] ?? 'demo-project';
$keyName = $argv[2] ?? 'dev-key';

$project = ApiProject::query()->where('code', $projectCode)->first();

if ($project === null) {
    fwrite(STDERR, "Project '{$projectCode}' not found.\n");
    exit(1);
}

/** @var array{key: ApiKey, plain: string} $result */
$result = app(ApiKeyService::class)->create($project, $keyName);

echo $result['plain'].PHP_EOL;
