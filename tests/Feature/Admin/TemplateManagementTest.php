<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Models\Template;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TemplateManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_template_index_renders_with_filters(): void
    {
        Template::factory()->create(['name' => 'واچر هتل', 'slug' => 'hotel-voucher', 'category' => 'hotel']);
        Template::factory()->inactive()->create(['name' => 'فاکتور', 'slug' => 'invoice', 'category' => 'finance']);

        $user = User::factory()->manager()->create();

        $this->actingAs($user)
            ->get('/admin/templates')
            ->assertOk()
            ->assertSee('واچر هتل')
            ->assertSee('فاکتور');

        $this->actingAs($user)
            ->get('/admin/templates?search=hotel-voucher')
            ->assertOk()
            ->assertSee('واچر هتل')
            ->assertDontSee('فاکتور');

        $this->actingAs($user)
            ->get('/admin/templates?status=inactive')
            ->assertOk()
            ->assertSee('فاکتور')
            ->assertDontSee('واچر هتل');

        $this->actingAs($user)
            ->get('/admin/templates?category=finance')
            ->assertOk()
            ->assertSee('فاکتور')
            ->assertDontSee('واچر هتل');
    }

    public function test_viewer_cannot_create_or_edit_templates(): void
    {
        $template = Template::factory()->create();
        $viewer = User::factory()->create();

        $this->actingAs($viewer)->get('/admin/templates/create')->assertForbidden();
        $this->actingAs($viewer)->post('/admin/templates', $this->payload())->assertForbidden();
        $this->actingAs($viewer)->get(route('admin.templates.edit', $template))->assertForbidden();
        $this->actingAs($viewer)->put(route('admin.templates.update', $template), $this->payload())->assertForbidden();

        $this->assertDatabaseMissing('templates', ['slug' => 'test-template']);
    }

    public function test_manager_can_create_template_with_variables_and_initial_version(): void
    {
        $manager = User::factory()->manager()->create();

        $response = $this->actingAs($manager)->post('/admin/templates', $this->payload());

        $template = Template::query()->where('slug', 'test-template')->first();
        $this->assertNotNull($template);

        $response->assertRedirect(route('admin.templates.edit', $template));

        $this->assertSame(1, $template->version);
        $this->assertSame($manager->id, $template->created_by);
        $this->assertDatabaseHas('template_variables', [
            'template_id' => $template->id,
            'name' => 'guest_name',
            'is_required' => true,
        ]);
        $this->assertDatabaseHas('template_versions', [
            'template_id' => $template->id,
            'version' => 1,
        ]);

        $this->actingAs($manager)
            ->get(route('admin.templates.edit', $template))
            ->assertOk()
            ->assertSee('test-template')
            ->assertSee('نام مهمان');
    }

    public function test_update_without_content_changes_does_not_create_a_version(): void
    {
        $manager = User::factory()->manager()->create();
        $template = $this->createTemplate($manager);

        $this->actingAs($manager)
            ->put(route('admin.templates.update', $template), $this->payload())
            ->assertRedirect(route('admin.templates.edit', $template));

        $this->assertSame(1, $template->fresh()->version);
        $this->assertSame(1, $template->versions()->count());
    }

    public function test_update_with_content_changes_creates_a_new_version(): void
    {
        $manager = User::factory()->manager()->create();
        $template = $this->createTemplate($manager);

        $payload = $this->payload([
            'html' => '<!DOCTYPE html><html><body><h1>{{ guest_name }}</h1><p>نسخه دوم</p></body></html>',
            'version_note' => 'افزودن پاراگراف',
        ]);

        $this->actingAs($manager)
            ->put(route('admin.templates.update', $template), $payload)
            ->assertRedirect(route('admin.templates.edit', $template));

        $template->refresh();

        $this->assertSame(2, $template->version);
        $this->assertSame(2, $template->versions()->count());
        $this->assertSame($manager->id, $template->updated_by);
        $this->assertDatabaseHas('template_versions', [
            'template_id' => $template->id,
            'version' => 2,
            'note' => 'افزودن پاراگراف',
        ]);
    }

    public function test_variables_are_synced_on_update(): void
    {
        $manager = User::factory()->manager()->create();
        $template = $this->createTemplate($manager);

        $payload = $this->payload([
            'variables' => [
                [
                    'name' => 'guest_name',
                    'label' => 'نام مهمان (ویرایش‌شده)',
                    'type' => 'string',
                    'is_required' => '1',
                    'default_value' => 'مهمان نمونه',
                    'description' => 'توضیح',
                    'validation_rules' => 'max:100',
                    'min_length' => '2',
                    'max_length' => '100',
                    'min_value' => '',
                    'max_value' => '',
                    'sort_order' => '1',
                ],
                [
                    'name' => 'total_price',
                    'label' => 'مبلغ',
                    'type' => 'decimal',
                    'is_required' => '0',
                    'default_value' => '0',
                    'description' => '',
                    'validation_rules' => '',
                    'min_length' => '',
                    'max_length' => '',
                    'min_value' => '0',
                    'max_value' => '1000000',
                    'sort_order' => '2',
                ],
            ],
        ]);

        $this->actingAs($manager)
            ->put(route('admin.templates.update', $template), $payload)
            ->assertRedirect();

        $this->assertDatabaseHas('template_variables', [
            'template_id' => $template->id,
            'name' => 'guest_name',
            'label' => 'نام مهمان (ویرایش‌شده)',
        ]);
        $this->assertDatabaseHas('template_variables', [
            'template_id' => $template->id,
            'name' => 'total_price',
            'type' => 'decimal',
        ]);

        // Removing the row must delete the variable.
        $single = $this->payload([
            'variables' => [
                [
                    'name' => 'guest_name',
                    'label' => 'نام مهمان',
                    'type' => 'string',
                    'is_required' => '0',
                    'sort_order' => '1',
                ],
            ],
        ]);

        $this->actingAs($manager)
            ->put(route('admin.templates.update', $template), $single)
            ->assertRedirect();

        $this->assertDatabaseMissing('template_variables', [
            'template_id' => $template->id,
            'name' => 'total_price',
        ]);
    }

    public function test_template_markup_with_php_is_rejected(): void
    {
        $manager = User::factory()->manager()->create();

        $payload = $this->payload([
            'html' => '<html><body><?php echo "evil"; ?></body></html>',
        ]);

        $this->actingAs($manager)
            ->post('/admin/templates', $payload)
            ->assertSessionHasErrors('html');

        $this->assertDatabaseMissing('templates', ['slug' => 'test-template']);

        $bladePayload = $this->payload([
            'html' => '<html><body>@php echo 1; @endphp</body></html>',
        ]);

        $this->actingAs($manager)
            ->post('/admin/templates', $bladePayload)
            ->assertSessionHasErrors('html');
    }

    public function test_template_can_be_copied(): void
    {
        $manager = User::factory()->manager()->create();
        $template = $this->createTemplate($manager);

        $this->actingAs($manager)
            ->post(route('admin.templates.copy', $template), [
                'name' => 'کپی قالب',
                'slug' => 'template-copy',
            ])
            ->assertRedirect();

        $copy = Template::query()->where('slug', 'template-copy')->first();

        $this->assertNotNull($copy);
        $this->assertSame('کپی قالب', $copy->name);
        $this->assertNotSame($template->id, $copy->id);
        $this->assertSame($template->variables()->count(), $copy->variables()->count());
    }

    public function test_template_can_be_toggled_and_deleted(): void
    {
        $manager = User::factory()->manager()->create();
        $template = $this->createTemplate($manager);

        $this->actingAs($manager)
            ->post(route('admin.templates.toggle', $template))
            ->assertRedirect();

        $this->assertFalse($template->fresh()->is_active);

        $this->actingAs($manager)
            ->delete(route('admin.templates.destroy', $template))
            ->assertRedirect(route('admin.templates.index'));

        $this->assertSoftDeleted('templates', ['id' => $template->id]);
    }

    public function test_versions_page_and_revert(): void
    {
        $manager = User::factory()->manager()->create();
        $template = $this->createTemplate($manager);

        $firstHtml = (string) $template->html;

        $this->actingAs($manager)->put(
            route('admin.templates.update', $template),
            $this->payload(['html' => '<html><body><p>ویرایش دوم</p></body></html>']),
        )->assertRedirect();

        $template->refresh();
        $this->assertSame(2, $template->version);

        $versions = $template->versions()->reorder('version')->get();
        $this->assertCount(2, $versions);

        $this->actingAs($manager)
            ->get(route('admin.templates.versions', $template))
            ->assertOk()
            ->assertSee('نسخه‌ها');

        $this->actingAs($manager)
            ->get(route('admin.templates.versions.show', [$template, $versions[0]]))
            ->assertOk();

        $this->actingAs($manager)
            ->post(route('admin.templates.versions.revert', [$template, $versions[0]]))
            ->assertRedirect(route('admin.templates.edit', $template));

        $template->refresh();

        $this->assertSame($firstHtml, (string) $template->html);
        $this->assertSame(3, $template->version);
    }

    private function createTemplate(User $manager): Template
    {
        $this->actingAs($manager)->post('/admin/templates', $this->payload());

        $template = Template::query()->where('slug', 'test-template')->firstOrFail();
        $template->load('variables');

        return $template;
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'قالب تست',
            'slug' => 'test-template',
            'category' => 'general',
            'description' => 'قالب تستی برای بررسی پنل',
            'locale' => 'fa',
            'is_active' => '1',
            'html' => '<!DOCTYPE html><html lang="fa" dir="rtl"><body><h1>{{ guest_name }}</h1></body></html>',
            'css' => 'body { font-family: Vazirmatn, sans-serif; }',
            'header_html' => '',
            'footer_html' => '',
            'page_size' => 'A4',
            'orientation' => 'portrait',
            'margins' => ['top' => '10', 'right' => '10', 'bottom' => '10', 'left' => '10'],
            'pdf_options' => [
                'print_background' => '1',
                'prefer_css_page_size' => '0',
                'display_header_footer' => '0',
                'scale' => '1',
                'custom_width' => '',
                'custom_height' => '',
            ],
            'version_note' => '',
            'variables' => [
                [
                    'name' => 'guest_name',
                    'label' => 'نام مهمان',
                    'type' => 'string',
                    'is_required' => '1',
                    'default_value' => 'مهمان نمونه',
                    'description' => 'نام کامل مهمان',
                    'validation_rules' => 'max:120',
                    'min_length' => '',
                    'max_length' => '120',
                    'min_value' => '',
                    'max_value' => '',
                    'sort_order' => '1',
                ],
            ],
        ], $overrides);
    }
}
