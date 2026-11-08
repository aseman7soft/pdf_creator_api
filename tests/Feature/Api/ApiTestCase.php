<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\ApiKey;
use App\Models\ApiProject;
use App\Models\Template;
use App\Services\Api\ApiKeyService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * Shared helpers for the public API feature tests.
 */
abstract class ApiTestCase extends TestCase
{
    use RefreshDatabase;

    protected const PDF_BYTES = "%PDF-1.4\n% fake gotenberg pdf\n1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n2 0 obj\n<< /Type /Pages /Kids [] /Count 0 >>\nendobj\ntrailer\n<< /Root 1 0 R >>\n%%EOF\n";

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'api.rate_limit_per_minute' => 1000,
            'api.idempotency_ttl_hours' => 24,
            'pdf.max_payload_kb' => 1024,
            'pdf.signed_url_ttl' => 30,
        ]);

        Storage::fake((string) config('pdf.disk', 'local'));
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    protected function makeProject(array $attributes = []): ApiProject
    {
        return ApiProject::factory()->create($attributes);
    }

    /**
     * @param  array<string, mixed>  $projectAttributes
     * @return array{project: ApiProject, key: ApiKey, token: string}
     */
    protected function makeProjectWithKey(array $projectAttributes = []): array
    {
        $project = $this->makeProject($projectAttributes);

        /** @var ApiKeyService $service */
        $service = app(ApiKeyService::class);
        $result = $service->create($project, 'کلید تست');

        return [
            'project' => $project,
            'key' => $result['key'],
            'token' => $result['plain'],
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $variables
     * @param  array<string, mixed>  $attributes
     */
    protected function makeTemplate(array $attributes = [], array $variables = []): Template
    {
        $template = Template::factory()->create(array_merge([
            'slug' => 'test-'.Str::lower(Str::random(10)),
            'html' => '<!DOCTYPE html><html lang="fa" dir="rtl"><body><h1>{{ guest_name }}</h1></body></html>',
        ], $attributes));

        foreach ($variables as $index => $variable) {
            $template->variables()->create(array_merge([
                'name' => 'guest_name',
                'label' => 'نام مهمان',
                'type' => 'string',
                'is_required' => true,
                'sort_order' => $index,
            ], $variable));
        }

        return $template->refresh()->load('variables');
    }

    /**
     * @return list<array<string, mixed>>
     */
    protected function guestNameVariables(): array
    {
        return [
            [
                'name' => 'guest_name',
                'label' => 'نام مهمان',
                'type' => 'string',
                'is_required' => true,
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function bearer(string $token): array
    {
        return [
            'Authorization' => 'Bearer '.$token,
            'Accept' => 'application/json',
        ];
    }

    protected function fakeGotenbergPdf(): void
    {
        Http::fake([
            '*' => Http::response(self::PDF_BYTES, 200, ['Content-Type' => 'application/pdf']),
        ]);
    }

    protected function fakeGotenbergFailure(): void
    {
        Http::fake([
            '*' => static function (): never {
                throw new ConnectionException('Connection refused (fake)');
            },
        ]);
    }

    protected function assertPdfResponse(TestResponse $response): void
    {
        $response->assertOk();

        $this->assertStringStartsWith(
            'application/pdf',
            (string) $response->headers->get('Content-Type'),
        );

        $this->assertStringContainsString(
            'attachment',
            (string) $response->headers->get('Content-Disposition'),
        );
    }

    /**
     * @return array<string, mixed>
     */
    protected function generatePayload(Template $template, array $data = ['guest_name' => 'علی رضایی']): array
    {
        return [
            'template' => $template->slug,
            'reference' => 'REF-'.Str::upper(Str::random(6)),
            'data' => $data,
            'options' => ['format' => 'A4', 'orientation' => 'portrait'],
        ];
    }
}
