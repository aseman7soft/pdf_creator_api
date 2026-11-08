<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\Template;

class TemplatesTest extends ApiTestCase
{
    public function test_unrestricted_project_sees_only_active_templates(): void
    {
        $context = $this->makeProjectWithKey();

        $active = $this->makeTemplate([
            'name' => 'قالب فعال',
            'slug' => 'active-template',
            'html' => '<html><body>{{ guest_name }}</body></html>',
        ], $this->guestNameVariables());

        $inactive = $this->makeTemplate([
            'name' => 'قالب غیرفعال',
            'slug' => 'inactive-template',
            'is_active' => false,
        ]);

        $response = $this->getJson('/api/v1/templates', $this->bearer($context['token']));

        $response->assertOk();
        $response->assertJsonPath('success', true);

        $slugs = array_column($response->json('data'), 'slug');

        $this->assertContains($active->slug, $slugs);
        $this->assertNotContains($inactive->slug, $slugs);
        $this->assertSame(1, $response->json('meta.total'));
    }

    public function test_restricted_project_only_sees_allowed_templates(): void
    {
        $context = $this->makeProjectWithKey();

        $allowed = $this->makeTemplate(['slug' => 'allowed-template']);
        $other = $this->makeTemplate(['slug' => 'other-template']);

        $context['project']->templates()->attach($allowed->getKey());

        $response = $this->getJson('/api/v1/templates', $this->bearer($context['token']));

        $response->assertOk();
        $slugs = array_column($response->json('data'), 'slug');

        $this->assertContains($allowed->slug, $slugs);
        $this->assertNotContains($other->slug, $slugs);
    }

    public function test_templates_can_be_filtered_by_search_and_category(): void
    {
        $context = $this->makeProjectWithKey();

        $this->makeTemplate(['name' => 'واچر هتل', 'slug' => 'hotel-voucher', 'category' => 'hotel']);
        $this->makeTemplate(['name' => 'فاکتور فروش', 'slug' => 'sales-invoice', 'category' => 'finance']);

        $search = $this->getJson('/api/v1/templates?search=هتل', $this->bearer($context['token']));
        $search->assertOk();
        $this->assertSame(['hotel-voucher'], array_column($search->json('data'), 'slug'));

        $category = $this->getJson('/api/v1/templates?category=finance', $this->bearer($context['token']));
        $category->assertOk();
        $this->assertSame(['sales-invoice'], array_column($category->json('data'), 'slug'));
    }

    public function test_template_show_returns_schema_and_sample_data(): void
    {
        $context = $this->makeProjectWithKey();

        $template = $this->makeTemplate(
            ['slug' => 'hotel-voucher'],
            [
                [
                    'name' => 'guest_name',
                    'label' => 'نام مهمان',
                    'type' => 'string',
                    'is_required' => true,
                ],
                [
                    'name' => 'room_count',
                    'label' => 'تعداد اتاق',
                    'type' => 'integer',
                    'is_required' => true,
                    'default_value' => '1',
                ],
            ],
        );

        $response = $this->getJson('/api/v1/templates/'.$template->slug, $this->bearer($context['token']));

        $response->assertOk();
        $response->assertJsonPath('data.slug', 'hotel-voucher');
        $response->assertJsonPath('data.sample_data.room_count', 1);
        $response->assertJsonPath('data.variables.0.name', 'guest_name');
        $response->assertJsonPath('data.variables.1.default', 1);
        $response->assertJsonStructure(['data' => ['slug', 'name', 'category', 'locale', 'version', 'page_size', 'orientation', 'variables', 'sample_data', 'updated_at']]);
    }

    public function test_unknown_template_returns_404(): void
    {
        $context = $this->makeProjectWithKey();

        $response = $this->getJson('/api/v1/templates/does-not-exist', $this->bearer($context['token']));

        $response->assertNotFound();
        $response->assertJsonPath('success', false);
        $response->assertJsonPath('error.code', 'TEMPLATE_NOT_FOUND');
    }

    public function test_inactive_template_returns_422(): void
    {
        $context = $this->makeProjectWithKey();
        $template = $this->makeTemplate(['slug' => 'inactive-template', 'is_active' => false]);

        $response = $this->getJson('/api/v1/templates/'.$template->slug, $this->bearer($context['token']));

        $response->assertStatus(422);
        $response->assertJsonPath('error.code', 'TEMPLATE_INACTIVE');
    }

    public function test_template_restriction_returns_403(): void
    {
        $context = $this->makeProjectWithKey();
        $allowed = $this->makeTemplate(['slug' => 'allowed-template']);
        $forbidden = $this->makeTemplate(['slug' => 'forbidden-template']);
        $context['project']->templates()->attach($allowed->getKey());

        $response = $this->getJson('/api/v1/templates/'.$forbidden->slug, $this->bearer($context['token']));

        $response->assertForbidden();
        $response->assertJsonPath('error.code', 'TEMPLATE_NOT_ALLOWED');
    }

    public function test_template_index_is_paginated(): void
    {
        $context = $this->makeProjectWithKey();

        foreach (range(1, 3) as $index) {
            $this->makeTemplate(['slug' => 'template-'.$index, 'name' => 'قالب '.$index]);
        }

        $response = $this->getJson('/api/v1/templates?per_page=2&page=1', $this->bearer($context['token']));

        $response->assertOk();
        $this->assertCount(2, $response->json('data'));
        $response->assertJsonPath('meta.per_page', 2);
        $response->assertJsonPath('meta.total', 3);
        $response->assertJsonPath('meta.last_page', 2);
    }

    public function test_template_route_can_be_discovered_by_ulid_like_slug(): void
    {
        // Guards against the signed-download route accidentally shadowing template routes.
        $context = $this->makeProjectWithKey();
        $template = $this->makeTemplate(['slug' => 'abc-123']);

        $this->getJson('/api/v1/templates/'.$template->slug, $this->bearer($context['token']))
            ->assertOk();
    }
}
