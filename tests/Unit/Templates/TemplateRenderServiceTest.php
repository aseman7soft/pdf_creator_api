<?php

declare(strict_types=1);

namespace Tests\Unit\Templates;

use App\Enums\AssetType;
use App\Exceptions\DocumentGenerationException;
use App\Exceptions\TemplateRenderException;
use App\Models\Asset;
use App\Models\Template;
use App\Services\Templates\TemplateRenderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class TemplateRenderServiceTest extends TestCase
{
    use RefreshDatabase;

    private TemplateRenderService $renderer;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        $this->renderer = $this->app->make(TemplateRenderService::class);
    }

    public function test_renders_html_and_flattens_asset_references_from_html_and_css(): void
    {
        $this->createFontAsset('vazirmatn-regular', 'FONT-BYTES-REGULAR');
        $logo = $this->createImageAsset('logo', 'LOGO-BYTES');

        $template = Template::factory()->create([
            'html' => '<!DOCTYPE html><html lang="fa" dir="rtl"><head><title>x</title></head><body>'
                .'<img src="asset://logo"><h1>{{ hotel_name }}</h1></body></html>',
            'css' => ".brand { background: url('asset://logo'); } .font { font-family: x; }",
            'header_html' => null,
            'footer_html' => null,
        ]);

        $rendered = $this->renderer->render($template, ['hotel_name' => 'هتل پارسیان']);

        $this->assertStringContainsString('هتل پارسیان', $rendered->html);
        $this->assertStringNotContainsString('asset://', $rendered->html);
        $this->assertStringContainsString('logo.'.$logo->extension, $rendered->html);
        $this->assertStringContainsString('<style>', $rendered->html);
        $this->assertStringContainsString('font-family: x', $rendered->html);
        $this->assertSame('LOGO-BYTES', $rendered->assets['logo.png']);
        $this->assertStringEndsWith('.pdf', $rendered->filename);
    }

    public function test_css_is_injected_inside_head_and_assets_in_css_are_flattened(): void
    {
        $this->createFontAsset('vazirmatn-bold', 'FONT-BYTES-BOLD');

        $template = Template::factory()->create([
            'html' => '<html><head><meta charset="utf-8"></head><body>docs</body></html>',
            'css' => "@font-face { font-family: 'Vazirmatn'; src: url('asset://vazirmatn-bold') format('woff2'); }",
        ]);

        $rendered = $this->renderer->render($template, []);

        $headPosition = strpos($rendered->html, '</head>');
        $stylePosition = strpos($rendered->html, '<style>');

        $this->assertNotFalse($stylePosition);
        $this->assertLessThan($headPosition, $stylePosition);
        $this->assertStringContainsString('vazirmatn-bold.woff2', $rendered->html);
        $this->assertArrayHasKey('vazirmatn-bold.woff2', $rendered->assets);
    }

    public function test_header_and_footer_are_rendered_and_sanitised(): void
    {
        $template = Template::factory()->create([
            'html' => '<html><body><p>{{ guest_name }}</p></body></html>',
            'header_html' => '<header onclick="x()">{{ guest_name }}</header>',
            'footer_html' => '<footer>صفحه <span class="pageNumber"></span></footer>',
        ]);

        $rendered = $this->renderer->render($template, ['guest_name' => 'علی']);

        $this->assertSame('<header>علی</header>', $rendered->headerHtml);
        $this->assertStringContainsString('pageNumber', (string) $rendered->footerHtml);
        $this->assertTrue($rendered->pdfOptions->displayHeaderFooter);
    }

    public function test_missing_asset_raises_controlled_render_error(): void
    {
        $template = Template::factory()->create([
            'html' => '<html><body><img src="asset://does-not-exist"></body></html>',
        ]);

        try {
            $this->renderer->render($template, []);
            $this->fail('Expected a TemplateRenderException.');
        } catch (TemplateRenderException $exception) {
            $this->assertStringContainsString('does-not-exist', $exception->getMessage());
            $this->assertSame('RENDER_ERROR', $exception->getErrorCode());
        }
    }

    public function test_image_variable_with_asset_handle_is_flattened(): void
    {
        $this->createImageAsset('hotel-logo', 'LOGO-CONTENT');

        $template = Template::factory()->create([
            'html' => '<html><body><img src="{{ logo }}"></body></html>',
        ]);
        $template->variables()->create([
            'name' => 'logo',
            'label' => 'لوگو',
            'type' => 'image',
            'is_required' => false,
            'sort_order' => 1,
        ]);

        $rendered = $this->renderer->render($template->refresh(), ['logo' => 'asset://hotel-logo']);

        $this->assertStringContainsString('src="hotel-logo.png"', $rendered->html);
        $this->assertSame('LOGO-CONTENT', $rendered->assets['hotel-logo.png']);
    }

    public function test_image_variable_with_public_url_is_downloaded_as_data_uri(): void
    {
        $imageBytes = "\x89PNG\r\n\x1a\n-fake-image-bytes";
        Http::fake([
            'https://93.184.216.34/logo.png' => Http::response($imageBytes, 200, ['Content-Type' => 'image/png']),
        ]);

        $template = Template::factory()->create([
            'html' => '<html><body><img src="{{ logo }}"></body></html>',
        ]);
        $template->variables()->create([
            'name' => 'logo',
            'label' => 'لوگو',
            'type' => 'image',
            'is_required' => false,
            'sort_order' => 1,
        ]);

        $rendered = $this->renderer->render($template->refresh(), ['logo' => 'https://93.184.216.34/logo.png']);

        $this->assertStringContainsString('data:image/png;base64,'.base64_encode($imageBytes), $rendered->html);
        Http::assertSentCount(1);
    }

    public function test_image_variable_with_private_address_is_rejected_without_any_request(): void
    {
        Http::fake();

        $template = Template::factory()->create([
            'html' => '<html><body><img src="{{ logo }}"></body></html>',
        ]);
        $template->variables()->create([
            'name' => 'logo',
            'label' => 'لوگو',
            'type' => 'image',
            'is_required' => false,
            'sort_order' => 1,
        ]);

        try {
            $this->renderer->render($template->refresh(), ['logo' => 'http://127.0.0.1/internal.png']);
            $this->fail('Expected a DocumentGenerationException.');
        } catch (DocumentGenerationException $exception) {
            $this->assertSame('RENDER_ERROR', $exception->getErrorCode());
        }

        Http::assertNothingSent();
    }

    public function test_scripts_and_event_handlers_in_templates_are_sanitised(): void
    {
        $template = Template::factory()->create([
            'html' => '<html><body><div onclick="evil()">safe</div><script>evil()</script></body></html>',
        ]);

        $rendered = $this->renderer->render($template, []);

        $this->assertStringNotContainsString('<script', $rendered->html);
        $this->assertStringNotContainsString('onclick', $rendered->html);
        $this->assertStringContainsString('safe', $rendered->html);
    }

    public function test_pdf_options_are_built_from_template_defaults_and_request_overrides(): void
    {
        $template = Template::factory()->create([
            'html' => '<html><body>x</body></html>',
            'page_size' => 'A4',
            'orientation' => 'portrait',
            'margins' => ['top' => 10, 'right' => 12, 'bottom' => 14, 'left' => 16],
            'pdf_options' => ['print_background' => true, 'scale' => 1.0],
        ]);

        $rendered = $this->renderer->render($template, [], [
            'format' => 'A3',
            'orientation' => 'landscape',
            'scale' => 0.8,
            'print_background' => false,
            'margins' => ['top' => 25.4],
        ]);

        $options = $rendered->pdfOptions;
        $fields = $options->toGotenbergFields();

        $this->assertSame('A3', $options->format);
        $this->assertTrue($options->landscape);
        $this->assertFalse($options->printBackground);
        $this->assertSame(0.8, $options->scale);
        $this->assertSame('1', $fields['marginTop']);
        $this->assertSame('0.4724', $fields['marginRight']);
        $this->assertSame('16.5354', $fields['paperHeight']);
        $this->assertSame('11.6929', $fields['paperWidth']);
    }

    public function test_custom_page_size_uses_millimetre_dimensions(): void
    {
        $template = Template::factory()->create([
            'html' => '<html><body>x</body></html>',
            'page_size' => 'Custom',
        ]);

        $rendered = $this->renderer->render($template, [], [
            'paper_width' => 100,
            'paper_height' => 150,
        ]);

        $this->assertSame('Custom', $rendered->pdfOptions->format);
        $this->assertSame('3.937', $rendered->pdfOptions->formatInches($rendered->pdfOptions->paperWidthInches));
        $this->assertSame('5.9055', $rendered->pdfOptions->formatInches($rendered->pdfOptions->paperHeightInches));
    }

    public function test_empty_template_html_is_rejected(): void
    {
        $template = Template::factory()->create(['html' => '   ']);

        $this->expectException(TemplateRenderException::class);

        $this->renderer->render($template, []);
    }

    public function test_sanitizer_authority_still_rejects_php_directives_in_stored_templates(): void
    {
        $template = Template::factory()->create([
            'html' => '<html><body>@php echo 1; @endphp</body></html>',
        ]);

        $this->expectException(TemplateRenderException::class);

        $this->renderer->render($template, []);
    }

    private function createFontAsset(string $handle, string $contents): Asset
    {
        $path = 'assets/fonts/'.$handle.'.woff2';
        Storage::disk('local')->put($path, $contents);

        return Asset::factory()->font()->create([
            'handle' => $handle,
            'extension' => 'woff2',
            'mime_type' => 'font/woff2',
            'disk' => 'local',
            'path' => $path,
            'size' => strlen($contents),
        ]);
    }

    private function createImageAsset(string $handle, string $contents): Asset
    {
        $path = 'assets/images/'.$handle.'.png';
        Storage::disk('local')->put($path, $contents);

        return Asset::factory()->create([
            'handle' => $handle,
            'type' => AssetType::Image,
            'extension' => 'png',
            'mime_type' => 'image/png',
            'disk' => 'local',
            'path' => $path,
            'size' => strlen($contents),
        ]);
    }
}
