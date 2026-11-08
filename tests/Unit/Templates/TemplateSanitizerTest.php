<?php

declare(strict_types=1);

namespace Tests\Unit\Templates;

use App\Exceptions\TemplateSanitizerException;
use App\Services\Templates\TemplateSanitizer;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class TemplateSanitizerTest extends TestCase
{
    private TemplateSanitizer $sanitizer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->sanitizer = $this->app->make(TemplateSanitizer::class);
    }

    public function test_removes_script_elements_and_their_content(): void
    {
        $html = $this->sanitizer->sanitize('<p>ok</p><script>alert("x")</script><p>after</p>');

        $this->assertStringNotContainsString('<script', $html);
        $this->assertStringNotContainsString('alert', $html);
        $this->assertStringContainsString('<p>ok</p>', $html);
        $this->assertStringContainsString('<p>after</p>', $html);
    }

    public function test_removes_iframe_object_embed_and_applet(): void
    {
        $html = $this->sanitizer->sanitize(
            '<iframe src="https://evil.test"></iframe><object data="x"></object><embed src="x"><applet code="x"></applet>',
        );

        $this->assertStringNotContainsString('<iframe', $html);
        $this->assertStringNotContainsString('<object', $html);
        $this->assertStringNotContainsString('<embed', $html);
        $this->assertStringNotContainsString('<applet', $html);
    }

    public function test_removes_inline_event_handlers(): void
    {
        $html = $this->sanitizer->sanitize('<div onclick="alert(1)" onmouseover=\'x()\' onload=y>safe</div>');

        $this->assertStringNotContainsString('onclick', $html);
        $this->assertStringNotContainsString('onmouseover', $html);
        $this->assertStringNotContainsString('onload', $html);
        $this->assertStringContainsString('safe', $html);
    }

    public function test_removes_javascript_and_vbscript_urls(): void
    {
        $html = $this->sanitizer->sanitize(
            '<a href="javascript:alert(1)">x</a><a href="JaVaScRiPt:alert(2)">y</a><a href="vbscript:msgbox">z</a>',
        );

        $this->assertStringNotContainsString('javascript:', strtolower($html));
        $this->assertStringNotContainsString('vbscript:', strtolower($html));
        $this->assertStringNotContainsString('alert(1)', $html);
    }

    public function test_removes_data_html_uris(): void
    {
        $html = $this->sanitizer->sanitize('<a href="data:text/html;base64,PHNjcmlwdD4=">x</a>');

        $this->assertStringNotContainsString('text/html', $html);
    }

    public function test_removes_external_css_imports(): void
    {
        $html = $this->sanitizer->sanitize(
            '<style>@import url("https://fonts.example.com/font.css"); body { color: red; }</style>',
        );

        $this->assertStringNotContainsString('@import', $html);
        $this->assertStringContainsString('body { color: red; }', $html);
        $this->assertStringNotContainsString('fonts.example.com', $html);
    }

    public function test_removes_external_css_urls_but_keeps_local_ones(): void
    {
        $css = <<<'CSS'
@font-face { font-family: 'Vazirmatn'; src: url('asset://vazirmatn-regular') format('woff2'); }
body { background: url("https://cdn.example.com/bg.png"); }
.logo { background: url('/images/logo.png'); }
.data { background: url('data:image/png;base64,AAAA'); }
.anchor { color: #111; }
CSS;

        $html = $this->sanitizer->sanitize('<style>'.$css.'</style>');

        $this->assertStringContainsString("url('asset://vazirmatn-regular')", $html);
        $this->assertStringContainsString("url('/images/logo.png')", $html);
        $this->assertStringContainsString('data:image/png;base64,AAAA', $html);
        $this->assertStringNotContainsString('cdn.example.com', $html);
        $this->assertStringContainsString("url('')", $html);
    }

    public function test_removes_external_image_sources_and_keeps_relative_and_anchor_links(): void
    {
        $html = $this->sanitizer->sanitize(
            '<img src="https://evil.test/x.png"><img src="/local.png"><a href="#section">s</a><a href="https://example.com">e</a>',
        );

        $this->assertStringNotContainsString('evil.test', $html);
        $this->assertStringContainsString('/local.png', $html);
        $this->assertStringContainsString('href="#section"', $html);
        $this->assertStringNotContainsString('example.com', $html);
    }

    public function test_removes_base_tag_and_meta_refresh(): void
    {
        $html = $this->sanitizer->sanitize(
            '<head><base href="https://evil.test/"><meta http-equiv="refresh" content="0;url=https://evil.test"><meta charset="utf-8"></head>',
        );

        $this->assertStringNotContainsString('<base', $html);
        $this->assertStringNotContainsString('refresh', $html);
        $this->assertStringContainsString('meta charset', $html);
    }

    public function test_removes_external_stylesheet_links_but_keeps_relative_ones(): void
    {
        $html = $this->sanitizer->sanitize(
            '<link rel="stylesheet" href="https://cdn.example.com/a.css"><link rel="stylesheet" href="/css/local.css">',
        );

        $this->assertStringNotContainsString('cdn.example.com', $html);
        $this->assertStringContainsString('/css/local.css', $html);
    }

    public function test_keeps_style_blocks_and_asset_fonts_for_seeded_templates(): void
    {
        $template = <<<'HTML'
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
<style>
@font-face { font-family: 'Vazirmatn'; src: url('asset://vazirmatn-regular') format('woff2'); font-weight: 400; }
@media print { .page { page-break-after: always; } }
</style>
</head>
<body><h1>واچر هتل</h1><img src="asset://hotel-logo-v1"></body>
</html>
HTML;

        $sanitized = $this->sanitizer->sanitize($template);

        $this->assertStringContainsString('<style>', $sanitized);
        $this->assertStringContainsString('@font-face', $sanitized);
        $this->assertStringContainsString('@media print', $sanitized);
        $this->assertStringContainsString("url('asset://vazirmatn-regular')", $sanitized);
        $this->assertStringContainsString('src="asset://hotel-logo-v1"', $sanitized);
        $this->assertStringContainsString('واچر هتل', $sanitized);
    }

    public function test_is_safe_detects_markup_that_needs_cleaning(): void
    {
        $this->assertFalse($this->sanitizer->isSafe('<p onclick="x()">a</p>'));
        $this->assertTrue($this->sanitizer->isSafe('<p>safe</p><style>.a{color:red}</style>'));
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function forbiddenProvider(): array
    {
        return [
            'php tag' => ['<?php echo "x"; ?>'],
            'short echo' => ['<?= $x ?>'],
            'blade php' => ['@php echo 1; @endphp'],
            'raw output' => ['{!! $x !!}'],
            'include' => ["@include('x')"],
            'extends' => ["@extends('x')"],
            'inject' => ['@inject("x")'],
            'use' => ['@use("x")'],
        ];
    }

    #[DataProvider('forbiddenProvider')]
    public function test_forbidden_constructs_throw_with_line_info(string $template): void
    {
        try {
            $this->sanitizer->sanitize($template);
            $this->fail('Expected a TemplateSanitizerException.');
        } catch (TemplateSanitizerException $exception) {
            $this->assertNotSame('', $exception->getMessage());
            $this->assertSame('RENDER_ERROR', $exception->getErrorCode());
            $this->assertIsInt($exception->getLineNumber());
        }
    }

    public function test_sanitizing_twice_is_stable(): void
    {
        $html = '<p onclick="x()">ok</p><script>bad()</script><style>body{background:url(https://x.test/a.png)}</style>';

        $once = $this->sanitizer->sanitize($html);
        $twice = $this->sanitizer->sanitize($once);

        $this->assertSame($once, $twice);
    }
}
