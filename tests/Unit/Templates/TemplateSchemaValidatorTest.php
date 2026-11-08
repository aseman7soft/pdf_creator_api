<?php

declare(strict_types=1);

namespace Tests\Unit\Templates;

use App\Models\Template;
use App\Services\Templates\TemplateSchemaValidator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class TemplateSchemaValidatorTest extends TestCase
{
    use RefreshDatabase;

    private TemplateSchemaValidator $validator;

    protected function setUp(): void
    {
        parent::setUp();

        $this->validator = $this->app->make(TemplateSchemaValidator::class);
    }

    public function test_rules_are_keyed_under_data_prefix(): void
    {
        $template = $this->templateWithVariables([
            ['name' => 'guest_name', 'type' => 'string', 'is_required' => true, 'validation_rules' => 'max:120'],
            ['name' => 'room_count', 'type' => 'integer', 'is_required' => false, 'default_value' => '1'],
        ]);

        $rules = $this->validator->rules($template);

        $this->assertArrayHasKey('data.guest_name', $rules);
        $this->assertArrayHasKey('data.room_count', $rules);
        $this->assertContains('required', $rules['data.guest_name']);
        $this->assertContains('max:120', $rules['data.guest_name']);
        $this->assertContains('nullable', $rules['data.room_count']);
        $this->assertContains('integer', $rules['data.room_count']);
    }

    public function test_defaults_are_applied_and_cast(): void
    {
        $template = $this->templateWithVariables([
            ['name' => 'room_count', 'type' => 'integer', 'default_value' => '2'],
            ['name' => 'total_price', 'type' => 'decimal', 'default_value' => '1500.50'],
            ['name' => 'is_vip', 'type' => 'boolean', 'default_value' => '1'],
            ['name' => 'currency', 'type' => 'string', 'default_value' => 'IRR'],
        ]);

        $result = $this->validator->validate($template, []);

        $this->assertSame(2, $result['room_count']);
        $this->assertSame(1500.5, $result['total_price']);
        $this->assertTrue($result['is_vip']);
        $this->assertSame('IRR', $result['currency']);
    }

    public function test_input_values_are_cast_to_declared_types(): void
    {
        $template = $this->templateWithVariables([
            ['name' => 'room_count', 'type' => 'integer'],
            ['name' => 'total_price', 'type' => 'decimal'],
            ['name' => 'is_vip', 'type' => 'boolean'],
            ['name' => 'guests', 'type' => 'array'],
            ['name' => 'metadata', 'type' => 'object'],
        ]);

        $result = $this->validator->validate($template, [
            'room_count' => '3',
            'total_price' => '99.9',
            'is_vip' => 'true',
            'guests' => '["a","b"]',
            'metadata' => '{"x":1}',
        ]);

        $this->assertSame(3, $result['room_count']);
        $this->assertSame(99.9, $result['total_price']);
        $this->assertTrue($result['is_vip']);
        $this->assertSame(['a', 'b'], $result['guests']);
        $this->assertSame(['x' => 1], $result['metadata']);
    }

    public function test_missing_required_variable_throws_validation_exception_with_data_keys(): void
    {
        $template = $this->templateWithVariables([
            ['name' => 'guest_name', 'type' => 'string', 'is_required' => true],
            ['name' => 'hotel_name', 'type' => 'string', 'is_required' => true],
        ]);

        try {
            $this->validator->validate($template, ['guest_name' => 'Ali']);
            $this->fail('Expected a ValidationException.');
        } catch (ValidationException $exception) {
            $errors = $exception->errors();

            $this->assertArrayHasKey('data.hotel_name', $errors);
            $this->assertArrayNotHasKey('data.guest_name', $errors);
        }
    }

    public function test_required_variable_with_default_can_be_omitted(): void
    {
        $template = $this->templateWithVariables([
            ['name' => 'currency', 'type' => 'string', 'is_required' => true, 'default_value' => 'USD'],
        ]);

        $result = $this->validator->validate($template, []);

        $this->assertSame('USD', $result['currency']);
    }

    public function test_empty_string_fails_required_validation(): void
    {
        $template = $this->templateWithVariables([
            ['name' => 'guest_name', 'type' => 'string', 'is_required' => true],
        ]);

        $this->expectException(ValidationException::class);

        $this->validator->validate($template, ['guest_name' => '']);
    }

    public function test_min_and_max_value_rules_are_enforced(): void
    {
        $template = $this->templateWithVariables([
            ['name' => 'room_count', 'type' => 'integer', 'is_required' => true, 'min_value' => 1, 'max_value' => 20],
        ]);

        $this->assertSame(['room_count' => 5], $this->validator->validate($template, ['room_count' => 5]));

        $this->expectException(ValidationException::class);
        $this->validator->validate($template, ['room_count' => 40]);
    }

    public function test_min_and_max_length_rules_are_enforced(): void
    {
        $template = $this->templateWithVariables([
            ['name' => 'code', 'type' => 'string', 'is_required' => true, 'min_length' => 3, 'max_length' => 6],
        ]);

        $this->assertSame(['code' => 'ABC'], $this->validator->validate($template, ['code' => 'ABC']));

        $this->expectException(ValidationException::class);
        $this->validator->validate($template, ['code' => 'A']);
    }

    public function test_custom_validation_rules_are_applied(): void
    {
        $template = $this->templateWithVariables([
            ['name' => 'customer_email', 'type' => 'string', 'validation_rules' => 'email'],
        ]);

        $this->assertSame(
            ['customer_email' => 'a@b.com'],
            $this->validator->validate($template, ['customer_email' => 'a@b.com']),
        );

        $this->expectException(ValidationException::class);
        $this->validator->validate($template, ['customer_email' => 'not-an-email']);
    }

    public function test_invalid_type_is_rejected(): void
    {
        $template = $this->templateWithVariables([
            ['name' => 'room_count', 'type' => 'integer'],
        ]);

        $this->expectException(ValidationException::class);

        $this->validator->validate($template, ['room_count' => 'not-a-number']);
    }

    public function test_invalid_date_is_rejected(): void
    {
        $template = $this->templateWithVariables([
            ['name' => 'check_in', 'type' => 'date', 'is_required' => true],
        ]);

        $this->expectException(ValidationException::class);

        $this->validator->validate($template, ['check_in' => 'not-a-date']);
    }

    public function test_unknown_keys_are_preserved_for_templates(): void
    {
        $template = $this->templateWithVariables([
            ['name' => 'guest_name', 'type' => 'string'],
        ]);

        $result = $this->validator->validate($template, [
            'guest_name' => 'Ali',
            'custom_note' => 'extra data',
            'nested' => ['a' => 1],
        ]);

        $this->assertSame('extra data', $result['custom_note']);
        $this->assertSame(['a' => 1], $result['nested']);
    }

    public function test_optional_variables_are_not_added_when_missing(): void
    {
        $template = $this->templateWithVariables([
            ['name' => 'notes', 'type' => 'text', 'is_required' => false],
        ]);

        $result = $this->validator->validate($template, []);

        $this->assertArrayNotHasKey('notes', $result);
    }

    public function test_null_optional_variable_is_accepted(): void
    {
        $template = $this->templateWithVariables([
            ['name' => 'notes', 'type' => 'text', 'default_value' => 'پیش‌فرض'],
        ]);

        $result = $this->validator->validate($template, ['notes' => null]);

        $this->assertSame('پیش‌فرض', $result['notes']);
    }

    /**
     * @param  list<array<string, mixed>>  $variables
     */
    private function templateWithVariables(array $variables): Template
    {
        $template = Template::factory()->create();
        $template->load('variables');

        foreach ($variables as $index => $variable) {
            $template->variables()->create([
                'name' => $variable['name'],
                'label' => $variable['label'] ?? $variable['name'],
                'type' => $variable['type'] ?? 'string',
                'is_required' => $variable['is_required'] ?? false,
                'default_value' => $variable['default_value'] ?? null,
                'description' => null,
                'validation_rules' => $variable['validation_rules'] ?? null,
                'min_length' => $variable['min_length'] ?? null,
                'max_length' => $variable['max_length'] ?? null,
                'min_value' => $variable['min_value'] ?? null,
                'max_value' => $variable['max_value'] ?? null,
                'sort_order' => $index,
            ]);
        }

        return $template->refresh()->load('variables');
    }
}
