<?php

declare(strict_types=1);

namespace Tests\Unit\Templates;

use App\Exceptions\TemplateRenderException;
use App\Services\Templates\SafeTemplateRenderer;
use App\Support\Jalali;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class SafeTemplateRendererTest extends TestCase
{
    private SafeTemplateRenderer $renderer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->renderer = $this->app->make(SafeTemplateRenderer::class);
    }

    public function test_renders_plain_text_and_escapes_variable_output(): void
    {
        $html = $this->renderer->render(
            '<p>سلام {{ guest_name }}</p>',
            ['guest_name' => '<b>علی</b> <script>alert(1)</script>'],
        );

        $this->assertStringContainsString('سلام', $html);
        $this->assertStringContainsString('&lt;b&gt;علی&lt;/b&gt;', $html);
        $this->assertStringNotContainsString('<b>', $html);
        $this->assertStringNotContainsString('<script>', $html);
    }

    public function test_bare_and_dollar_prefixed_variables_are_equivalent(): void
    {
        $html = $this->renderer->render('{{ name }}|{{ $name }}', ['name' => 'مهمان']);

        $this->assertSame('مهمان|مهمان', $html);
    }

    public function test_each_output_escapes_quotes_and_ampersands(): void
    {
        $html = $this->renderer->render('{{ value }}', ['value' => '"quoted" & \'single\'']);

        $this->assertSame('&quot;quoted&quot; &amp; &#039;single&#039;', $html);
    }

    public function test_dotted_indexed_and_nested_access(): void
    {
        $html = $this->renderer->render(
            '{{ guest.name }}|{{ guests[0].passport }}|{{ items[1]["title"] }}',
            [
                'guest' => ['name' => 'Ali'],
                'guests' => [['passport' => 'P-1'], ['passport' => 'P-2']],
                'items' => [['title' => 'first'], ['title' => 'second']],
            ],
        );

        $this->assertSame('Ali|P-1|second', $html);
    }

    public function test_unknown_variables_and_deep_paths_resolve_to_null(): void
    {
        $html = $this->renderer->render('[{{ missing }}][{{ missing.deep[0].chain }}]', []);

        $this->assertSame('[][]', $html);
    }

    public function test_null_coalescing_defaults_and_ternary(): void
    {
        $html = $this->renderer->render(
            "{{ status ?? 'UNPAID' }}|{{ null_value ?? 'x' }}|{{ 1 > 2 ? 'yes' : 'no' }}|{{ '' ?: 'empty' }}",
            ['status' => null],
        );

        $this->assertSame('UNPAID|x|no|empty', $html);
    }

    public function test_arithmetic_comparison_and_logical_operators(): void
    {
        $html = $this->renderer->render(
            '{{ 2 + 3 * 4 }}|{{ (2 + 3) * 4 }}|{{ 10 / 4 }}|{{ 10 % 3 }}|{{ 1 < 2 && 3 >= 3 }}|{{ false || true }}',
            [],
        );

        $this->assertSame('14|20|2.5|1|1|1', $html);
    }

    public function test_string_concatenation_operator(): void
    {
        $html = $this->renderer->render("{{ 'a' . 'b' . 1 }}", []);

        $this->assertSame('ab1', $html);
    }

    public function test_number_filter_uses_thousands_separator(): void
    {
        $html = $this->renderer->render(
            '{{ total | number }}|{{ decimal | number }}|{{ empty_value | number }}',
            ['total' => 1500000, 'decimal' => 1234.5, 'empty_value' => null],
        );

        $this->assertSame('1,500,000|1,234.50|', $html);
    }

    public function test_jdate_and_jdatetime_filters(): void
    {
        $date = Carbon::parse('2026-11-08 14:30:00');

        $html = $this->renderer->render(
            '{{ date | jdate }}|{{ date | jdate("j F Y") }}|{{ date | jdatetime }}',
            ['date' => $date],
        );

        $expected = Jalali::format($date, 'Y/m/d')
            .'|'.Jalali::format($date, 'j F Y')
            .'|'.Jalali::format($date, 'Y/m/d H:i');

        $this->assertSame($expected, $html);
    }

    public function test_string_case_trim_and_truncate_filters(): void
    {
        $long = str_repeat('ا', 90);

        $html = $this->renderer->render(
            '{{ text | upper }}|{{ text | lower }}|{{ padded | trim }}|{{ long | truncate(80) }}',
            ['text' => 'MiXeD', 'padded' => '  hello  ', 'long' => $long],
        );

        $this->assertStringStartsWith('MIXED|mixed|hello|', $html);
        $this->assertStringEndsWith('…', $html);
        $this->assertSame(81, mb_strlen(explode('|', $html)[3], 'UTF-8'));
    }

    public function test_nl2br_escapes_then_inserts_breaks(): void
    {
        $html = $this->renderer->render('{{ text | nl2br }}', ['text' => "خط اول\n<script>x</script>"]);

        $this->assertStringContainsString('<br', $html);
        $this->assertStringNotContainsString('<script>', $html);
        $this->assertStringContainsString('&lt;script&gt;', $html);
    }

    public function test_default_and_json_filters(): void
    {
        $html = $this->renderer->render(
            "{{ missing | default('—') }}|{{ empty_string | default('fallback') }}|{{ items | json }}",
            ['empty_string' => '', 'items' => [['name' => 'علی', 'count' => 2]]],
        );

        $parts = explode('|', $html);

        $this->assertSame('—', $parts[0]);
        $this->assertSame('fallback', $parts[1]);
        $this->assertStringContainsString('count', $parts[2]);
        $this->assertStringContainsString('علی', $parts[2]);
    }

    public function test_if_elseif_else_and_unless(): void
    {
        $template = '@if($score > 90)عالی@elseif($score > 50)متوسط@elseضعیف@endif';
        $this->assertSame('عالی', $this->renderer->render($template, ['score' => 95]));
        $this->assertSame('متوسط', $this->renderer->render($template, ['score' => 60]));
        $this->assertSame('ضعیف', $this->renderer->render($template, ['score' => 10]));

        $unless = '@unless($hidden)نمایش داده می‌شود@elseمخفی@endunless';
        $this->assertSame('نمایش داده می‌شود', $this->renderer->render($unless, ['hidden' => false]));
        $this->assertSame('مخفی', $this->renderer->render($unless, ['hidden' => true]));
    }

    public function test_foreach_with_loop_metadata(): void
    {
        $template = <<<'TPL'
@foreach($guests as $guest)
{{ loop.index }}:{{ loop.iteration }}:{{ guest.name }}:{{ loop.first ? 'F' : '' }}{{ loop.last ? 'L' : '' }}:{{ loop.count }}:{{ loop.remaining }}
@endforeach
TPL;

        $html = $this->renderer->render($template, [
            'guests' => [['name' => 'a'], ['name' => 'b'], ['name' => 'c']],
        ]);

        $lines = array_values(array_filter(explode("\n", $html), fn (string $line): bool => trim($line) !== ''));

        $this->assertSame('0:1:a:F:3:2', $lines[0]);
        $this->assertSame('1:2:b::3:1', $lines[1]);
        $this->assertSame('2:3:c:L:3:0', $lines[2]);
    }

    public function test_foreach_with_key_value_and_empty_iterable(): void
    {
        $template = '@foreach($data as $key => $value){{ $key }}={{ $value }};@endforeach';

        $this->assertSame('a=1;b=2;', $this->renderer->render($template, ['data' => ['a' => 1, 'b' => 2]]));
        $this->assertSame('', $this->renderer->render($template, ['data' => null]));
    }

    public function test_foreach_coalescing_empty_array_literal(): void
    {
        $template = '<ul>@foreach($includes ?? [] as $item)<li>{{ item }}</li>@endforeach</ul>';

        $this->assertSame('<ul><li>صبحانه</li></ul>', $this->renderer->render($template, ['includes' => ['صبحانه']]));
        $this->assertSame('<ul></ul>', $this->renderer->render($template, []));
    }

    public function test_nested_loops_restore_outer_loop_variable(): void
    {
        $template = '@foreach($groups as $group)[{{ loop.index }}:@foreach($group as $item){{ loop.index }}-{{ item }};@endforeach:{{ loop.index }}]@endforeach';

        $html = $this->renderer->render($template, ['groups' => [['x', 'y'], ['z']]]);

        $this->assertSame('[0:0-x;1-y;:0][1:0-z;:1]', $html);
    }

    public function test_isset_and_empty_blocks(): void
    {
        $template = '@isset($notes)<p>{{ notes }}</p>@endisset@empty($notes)<span>بدون توضیح</span>@endempty';

        $this->assertSame('<p>توضیح</p>', $this->renderer->render($template, ['notes' => 'توضیح']));
        // @isset follows PHP semantics: an empty string is set, so the block renders.
        $this->assertSame('<p></p><span>بدون توضیح</span>', $this->renderer->render($template, ['notes' => '']));
        $this->assertSame('<span>بدون توضیح</span>', $this->renderer->render($template, []));
    }

    public function test_inline_date_and_number_directives(): void
    {
        $date = Carbon::parse('2026-11-08');

        $html = $this->renderer->render(
            '@date($d, "Y-m-d")|@jdate($d)|@number($n)',
            ['d' => $date, 'n' => 2500000],
        );

        $expected = '2026-11-08|'.Jalali::format($date, 'Y/m/d').'|2,500,000';

        $this->assertSame($expected, $html);
    }

    public function test_jdate_now_is_supported(): void
    {
        $html = $this->renderer->render('تاریخ صدور: @jdate(now())', []);

        $this->assertStringContainsString(Jalali::now(), $html);
    }

    public function test_comments_are_removed_completely(): void
    {
        $html = $this->renderer->render('before{{-- hidden {{ x }} @if(true) --}}after', []);

        $this->assertSame('beforeafter', $html);
    }

    public function test_nested_if_inside_foreach(): void
    {
        $template = '@foreach($items as $item)@if($item["active"])<b>{{ $item["name"] }}</b>@else<i>{{ $item["name"] }}</i>@endif@endforeach';

        $html = $this->renderer->render($template, [
            'items' => [
                ['name' => 'A', 'active' => true],
                ['name' => 'B', 'active' => false],
            ],
        ]);

        $this->assertSame('<b>A</b><i>B</i>', $html);
    }

    public function test_allowlisted_functions_are_available(): void
    {
        $html = $this->renderer->render(
            '{{ count($items) }}|{{ strlen("abc") }}|{{ mb_strlen("سلام") }}|{{ round(1.6) }}|{{ abs(-4) }}|{{ max(1, 5, 3) }}|{{ min(1, 5, 3) }}|{{ intval("7") }}|{{ floatval("1.5") }}',
            ['items' => [1, 2, 3]],
        );

        $this->assertSame('3|3|4|2|4|5|1|7|1.5', $html);
    }

    public function test_style_blocks_with_css_at_rules_are_preserved(): void
    {
        $template = <<<'HTML'
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
<style>
@font-face { font-family: 'Vazirmatn'; src: url('asset://vazirmatn-regular') format('woff2'); }
@media print { .page { page-break-after: always; } }
</style>
</head>
<body><h1>{{ title }}</h1></body>
</html>
HTML;

        $html = $this->renderer->render($template, ['title' => 'واچر هتل']);

        $this->assertStringContainsString('@font-face', $html);
        $this->assertStringContainsString('@media print', $html);
        $this->assertStringContainsString("url('asset://vazirmatn-regular')", $html);
        $this->assertStringContainsString('واچر هتل', $html);
    }

    public function test_email_addresses_in_text_are_not_parsed_as_directives(): void
    {
        $html = $this->renderer->render('Contact: support@example.com', []);

        $this->assertSame('Contact: support@example.com', $html);
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function forbiddenTemplateProvider(): array
    {
        return [
            'php tag' => ['<?php echo "x"; ?>'],
            'php short echo' => ['<?= $x ?>'],
            'blade php' => ['@php echo 1; @endphp'],
            'raw output' => ['{!! $x !!}'],
            'include' => ["@include('partial')"],
            'extends' => ["@extends('layout')"],
            'inject' => ['@inject("service")'],
            'use' => ['@use("Foo")'],
        ];
    }

    #[DataProvider('forbiddenTemplateProvider')]
    public function test_forbidden_constructs_are_rejected(string $template): void
    {
        try {
            $this->renderer->render($template, ['x' => 'value']);
            $this->fail('Expected a TemplateRenderException for forbidden template.');
        } catch (TemplateRenderException $exception) {
            $this->assertNotSame('', $exception->getMessage());
            $this->assertStringNotContainsString('<?php', $exception->getMessage());
            $this->assertStringNotContainsString('<?=', $exception->getMessage());
        }
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function forbiddenExpressionProvider(): array
    {
        return [
            'system call' => ["{{ system('ls') }}"],
            'method call' => ['{{ $x->foo() }}'],
            'static call' => ['{{ \\App\\Models\\User::query() }}'],
            'globals' => ['{{ $GLOBALS[\'x\'] }}'],
            'this' => ['{{ $this }}'],
            'backticks' => ['{{ `ls` }}'],
            'assignment' => ['{{ $x = 1 }}'],
            'eval' => ["{{ eval('1') }}"],
            'call_user_func' => ["{{ call_user_func('system', 'ls') }}"],
            'server' => ['{{ $_SERVER[\'PATH\'] }}'],
        ];
    }

    #[DataProvider('forbiddenExpressionProvider')]
    public function test_unsafe_expressions_throw_controlled_errors(string $template): void
    {
        try {
            $this->renderer->render($template, ['x' => 'value']);
            $this->fail('Expected a TemplateRenderException for unsafe expression.');
        } catch (TemplateRenderException $exception) {
            $this->assertNotSame('', $exception->getMessage());
            $this->assertStringNotContainsString('<?php', $exception->getMessage());
            $this->assertStringNotContainsString('shell_exec', $exception->getMessage());
        }
    }

    public function test_syntax_errors_include_source_line(): void
    {
        $template = "line one\nline two\n{{ \$x->foo() }}";

        try {
            $this->renderer->render($template, []);
            $this->fail('Expected a TemplateRenderException.');
        } catch (TemplateRenderException $exception) {
            $this->assertSame(3, $exception->getLineNumber());
            $this->assertStringContainsString('خط 3', $exception->getMessage());
        }
    }

    public function test_unclosed_blocks_are_reported_with_directive_name(): void
    {
        try {
            $this->renderer->render("@if(true)\n<p>text</p>", []);
            $this->fail('Expected a TemplateRenderException.');
        } catch (TemplateRenderException $exception) {
            $this->assertStringContainsString('@if', $exception->getMessage());
            $this->assertSame(1, $exception->getLineNumber());
        }
    }

    public function test_unclosed_output_tag_is_reported(): void
    {
        $this->expectException(TemplateRenderException::class);

        $this->renderer->render('<p>{{ name </p>', ['name' => 'x']);
    }

    public function test_unexpected_closing_directive_is_reported(): void
    {
        $this->expectException(TemplateRenderException::class);

        $this->renderer->render('@endif', []);
    }

    public function test_unknown_filter_is_rejected(): void
    {
        $this->expectException(TemplateRenderException::class);

        $this->renderer->render('{{ name | exec("ls") }}', ['name' => 'x']);
    }

    public function test_division_by_zero_is_a_controlled_error(): void
    {
        $this->expectException(TemplateRenderException::class);

        $this->renderer->render('{{ 1 / 0 }}', []);
    }

    public function test_arrays_are_rendered_as_json_not_php_errors(): void
    {
        $html = $this->renderer->render('{{ items }}', ['items' => ['a' => 1]]);

        $this->assertStringContainsString('&quot;a&quot;', $html);
    }

    public function test_objects_are_not_traversed(): void
    {
        $object = new \stdClass;
        $object->name = 'secret';

        $html = $this->renderer->render('[{{ object.name }}]', ['object' => $object]);

        $this->assertSame('[secret]', $html);
    }

    public function test_seeded_hotel_voucher_constructs_render(): void
    {
        $template = <<<'HTML'
<html lang="fa" dir="rtl"><body>
<h1>{{ hotel_name }}</h1>
<p>کد رزرو: {{ booking_reference }} | تاریخ صدور: @jdate(now())</p>
@if(isset($guests) && count($guests) > 0)
@foreach($guests as $guest)
{{ loop.iteration }}|{{ guest.name }}|{{ guest.passport ?? '—' }}
@endforeach
@endif
@if(isset($notes) && $notes)<div>{{ notes }}</div>@endif
<span>{{ total_price | number }} {{ currency }}</span>
</body></html>
HTML;

        $html = $this->renderer->render($template, [
            'hotel_name' => 'هتل پارسیان',
            'booking_reference' => 'BK-100',
            'guests' => [
                ['name' => 'علی', 'passport' => 'P1'],
                ['name' => 'مریم'],
            ],
            'notes' => 'لطفاً زودتر بیایید',
            'total_price' => 12500000,
            'currency' => 'IRR',
        ]);

        $this->assertStringContainsString('هتل پارسیان', $html);
        $this->assertStringContainsString('BK-100', $html);
        $this->assertStringContainsString('1|علی|P1', $html);
        $this->assertStringContainsString('2|مریم|—', $html);
        $this->assertStringContainsString('لطفاً زودتر بیایید', $html);
        $this->assertStringContainsString('12,500,000 IRR', $html);
    }

    public function test_seeded_invoice_constructs_render(): void
    {
        $template = <<<'HTML'
<div>{{ invoice_number }}</div>
<div>Issued: @date($issue_date, 'Y-m-d')</div>
<div>{{ status ?? 'UNPAID' }}</div>
@foreach($items as $item)
{{ loop.iteration }}|{{ item.description }}|{{ item.quantity }}|{{ item.unit_price | number }}|{{ item.amount | number }}
@endforeach
@if(($discount ?? 0) > 0)<span>-{{ discount | number }}</span>@endif
<span>{{ subtotal | number }} {{ currency }}</span>
HTML;

        $html = $this->renderer->render($template, [
            'invoice_number' => 'INV-9',
            'issue_date' => '2026-11-08',
            'items' => [
                ['description' => 'Tour', 'quantity' => 2, 'unit_price' => 1500, 'amount' => 3000],
            ],
            'discount' => 100,
            'subtotal' => 3000,
            'currency' => 'USD',
        ]);

        $this->assertStringContainsString('INV-9', $html);
        $this->assertStringContainsString('Issued: 2026-11-08', $html);
        $this->assertStringContainsString('UNPAID', $html);
        $this->assertStringContainsString('1|Tour|2|1,500|3,000', $html);
        $this->assertStringContainsString('-100', $html);
        $this->assertStringContainsString('3,000 USD', $html);
    }

    public function test_output_is_deterministic_when_rendered_twice(): void
    {
        $template = '@foreach($items as $item){{ item }}@endforeach';
        $data = ['items' => ['a', 'b', 'c']];

        $this->assertSame(
            $this->renderer->render($template, $data),
            $this->renderer->render($template, $data),
        );
    }
}
