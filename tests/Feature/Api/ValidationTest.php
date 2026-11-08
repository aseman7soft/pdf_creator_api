<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

class ValidationTest extends ApiTestCase
{
    public function test_template_field_is_required(): void
    {
        $context = $this->makeProjectWithKey();

        $response = $this->postJson('/api/v1/documents/generate', [
            'data' => ['guest_name' => 'Ali'],
        ], $this->bearer($context['token']));

        $response->assertStatus(422);
        $response->assertJsonPath('error.code', 'VALIDATION_ERROR');
        $this->assertArrayHasKey('template', $response->json('error.details'));
    }

    public function test_data_field_is_required(): void
    {
        $context = $this->makeProjectWithKey();

        $response = $this->postJson('/api/v1/documents/generate', [
            'template' => 'anything',
        ], $this->bearer($context['token']));

        $response->assertStatus(422);
        $response->assertJsonPath('error.code', 'VALIDATION_ERROR');
        $this->assertArrayHasKey('data', $response->json('error.details'));
    }

    public function test_data_must_be_an_object(): void
    {
        $context = $this->makeProjectWithKey();

        $response = $this->postJson('/api/v1/documents/generate', [
            'template' => 'anything',
            'data' => ['first', 'second'],
        ], $this->bearer($context['token']));

        $response->assertStatus(422);
        $response->assertJsonPath('error.code', 'VALIDATION_ERROR');
        $this->assertArrayHasKey('data', $response->json('error.details'));
    }

    public function test_invalid_page_format_is_rejected(): void
    {
        $context = $this->makeProjectWithKey();

        $response = $this->postJson('/api/v1/documents/generate', [
            'template' => 'anything',
            'data' => [],
            'options' => ['format' => 'B5'],
        ], $this->bearer($context['token']));

        $response->assertStatus(422);
        $response->assertJsonPath('error.code', 'VALIDATION_ERROR');
        $this->assertArrayHasKey('options.format', $response->json('error.details'));
    }

    public function test_invalid_orientation_is_rejected(): void
    {
        $context = $this->makeProjectWithKey();

        $response = $this->postJson('/api/v1/documents/generate', [
            'template' => 'anything',
            'data' => [],
            'options' => ['orientation' => 'diagonal'],
        ], $this->bearer($context['token']));

        $response->assertStatus(422);
        $this->assertArrayHasKey('options.orientation', $response->json('error.details'));
    }

    public function test_reference_longer_than_120_characters_is_rejected(): void
    {
        $context = $this->makeProjectWithKey();

        $response = $this->postJson('/api/v1/documents/generate', [
            'template' => 'anything',
            'reference' => str_repeat('r', 121),
            'data' => [],
        ], $this->bearer($context['token']));

        $response->assertStatus(422);
        $this->assertArrayHasKey('reference', $response->json('error.details'));
    }

    public function test_template_schema_validation_errors_are_keyed_by_data_variable(): void
    {
        $this->fakeGotenbergPdf();

        $context = $this->makeProjectWithKey();
        $template = $this->makeTemplate([], $this->guestNameVariables());

        $response = $this->postJson('/api/v1/documents/generate', [
            'template' => $template->slug,
            'data' => [],
        ], $this->bearer($context['token']));

        $response->assertStatus(422);
        $response->assertJsonPath('error.code', 'VALIDATION_ERROR');

        $details = $response->json('error.details');
        $this->assertIsArray($details);
        $this->assertArrayHasKey('data.guest_name', $details);
    }
}
