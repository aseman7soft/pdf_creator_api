<?php

declare(strict_types=1);

namespace Tests\Feature\Documents;

use App\Exceptions\DocumentGenerationException;
use App\Services\Security\ExternalUrlGuard;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ExternalUrlGuardTest extends TestCase
{
    private const PNG_BYTES = "\x89PNG\r\n\x1a\nFAKE-PNG-BYTES";

    /**
     * @return array<string, array{0: string}>
     */
    public static function blockedUrlProvider(): array
    {
        return [
            'loopback' => ['http://127.0.0.1/logo.png'],
            'loopback variant' => ['http://127.1.2.3/logo.png'],
            'private 10/8' => ['http://10.0.0.5/logo.png'],
            'private 172.16/12' => ['http://172.16.0.1/logo.png'],
            'private 192.168/16' => ['http://192.168.1.10/logo.png'],
            'metadata' => ['http://169.254.169.254/latest/meta-data'],
            'unspecified' => ['http://0.0.0.0/logo.png'],
            'carrier grade nat' => ['http://100.64.0.1/logo.png'],
            'ipv6 loopback' => ['http://[::1]/logo.png'],
            'ipv6 unique local' => ['http://[fc00::1]/logo.png'],
            'ipv6 link local' => ['http://[fe80::1]/logo.png'],
            'ftp scheme' => ['ftp://93.184.216.34/logo.png'],
            'file scheme' => ['file:///etc/passwd'],
            'credentials' => ['http://user:pass@93.184.216.34/logo.png'],
            'javascript' => ['javascript:alert(1)'],
            'no host' => ['http:///logo.png'],
        ];
    }

    #[DataProvider('blockedUrlProvider')]
    public function test_blocked_urls_are_rejected(string $url): void
    {
        Http::fake();

        $guard = $this->app->make(ExternalUrlGuard::class);

        try {
            $guard->assertAllowed($url);
            $this->fail('Expected the URL to be rejected: '.$url);
        } catch (DocumentGenerationException $exception) {
            $this->assertSame('RENDER_ERROR', $exception->getErrorCode());
        }

        Http::assertNothingSent();
    }

    public function test_public_ip_addresses_are_allowed(): void
    {
        $guard = $this->app->make(ExternalUrlGuard::class);

        $guard->assertAllowed('https://93.184.216.34/logo.png');
        $guard->assertAllowed('http://8.8.8.8/image.jpg');

        $this->assertTrue(true);
    }

    public function test_hostname_resolving_to_private_address_is_rejected(): void
    {
        $guard = new ExternalUrlGuard(static fn (string $host): array => ['10.1.2.3']);

        $this->expectException(DocumentGenerationException::class);

        $guard->assertAllowed('https://internal.example.test/logo.png');
    }

    public function test_hostname_resolving_to_public_address_is_allowed(): void
    {
        $guard = new ExternalUrlGuard(static fn (string $host): array => ['93.184.216.34']);

        $guard->assertAllowed('https://public.example.test/logo.png');

        $this->assertTrue(true);
    }

    public function test_empty_dns_resolution_is_rejected(): void
    {
        $guard = new ExternalUrlGuard(static fn (string $host): array => []);

        $this->expectException(DocumentGenerationException::class);

        $guard->assertAllowed('https://unknown.example.test/logo.png');
    }

    public function test_fetch_converts_allowed_image_to_data_uri(): void
    {
        Http::fake([
            'https://93.184.216.34/logo.png' => Http::response(self::PNG_BYTES, 200, ['Content-Type' => 'image/png']),
        ]);

        $guard = $this->app->make(ExternalUrlGuard::class);

        $dataUri = $guard->fetchImageAsDataUri('https://93.184.216.34/logo.png');

        $this->assertSame('data:image/png;base64,'.base64_encode(self::PNG_BYTES), $dataUri);
        Http::assertSentCount(1);
    }

    public function test_fetch_accepts_jpeg_alias_content_type(): void
    {
        Http::fake([
            'https://93.184.216.34/photo.jpg' => Http::response('JPEG-BYTES', 200, ['Content-Type' => 'image/jpg']),
        ]);

        $guard = $this->app->make(ExternalUrlGuard::class);

        $this->assertStringStartsWith(
            'data:image/jpeg;base64,',
            $guard->fetchImageAsDataUri('https://93.184.216.34/photo.jpg'),
        );
    }

    public function test_non_image_content_type_is_rejected(): void
    {
        Http::fake([
            'https://93.184.216.34/page.html' => Http::response('<html></html>', 200, ['Content-Type' => 'text/html']),
        ]);

        $guard = $this->app->make(ExternalUrlGuard::class);

        $this->expectException(DocumentGenerationException::class);

        $guard->fetchImageAsDataUri('https://93.184.216.34/page.html');
    }

    public function test_oversized_content_length_is_rejected(): void
    {
        config(['pdf.security.max_remote_image_bytes' => 100]);

        Http::fake([
            'https://93.184.216.34/big.png' => Http::response(
                str_repeat('x', 200),
                200,
                ['Content-Type' => 'image/png', 'Content-Length' => '200'],
            ),
        ]);

        $guard = $this->app->make(ExternalUrlGuard::class);

        $this->expectException(DocumentGenerationException::class);

        $guard->fetchImageAsDataUri('https://93.184.216.34/big.png');
    }

    public function test_empty_body_is_rejected(): void
    {
        Http::fake(['https://93.184.216.34/empty.png' => Http::response('', 200, ['Content-Type' => 'image/png'])]);

        $guard = $this->app->make(ExternalUrlGuard::class);

        $this->expectException(DocumentGenerationException::class);

        $guard->fetchImageAsDataUri('https://93.184.216.34/empty.png');
    }

    public function test_redirect_to_private_address_is_rejected(): void
    {
        Http::fake([
            'https://93.184.216.34/redirect.png' => Http::response('', 302, ['Location' => 'http://127.0.0.1/secret.png']),
        ]);

        $guard = $this->app->make(ExternalUrlGuard::class);

        try {
            $guard->fetchImageAsDataUri('https://93.184.216.34/redirect.png');
            $this->fail('Expected the redirect to be rejected.');
        } catch (DocumentGenerationException $exception) {
            $this->assertSame('RENDER_ERROR', $exception->getErrorCode());
        }

        Http::assertSentCount(1);
    }

    public function test_redirect_to_public_address_is_followed(): void
    {
        Http::fake([
            'https://93.184.216.34/first.png' => Http::response('', 302, ['Location' => 'https://93.184.216.34/second.png']),
            'https://93.184.216.34/second.png' => Http::response(self::PNG_BYTES, 200, ['Content-Type' => 'image/png']),
        ]);

        $guard = $this->app->make(ExternalUrlGuard::class);

        $dataUri = $guard->fetchImageAsDataUri('https://93.184.216.34/first.png');

        $this->assertStringStartsWith('data:image/png;base64,', $dataUri);
        Http::assertSentCount(2);
    }

    public function test_remote_images_can_be_disabled_by_configuration(): void
    {
        config(['pdf.security.allow_remote_image_variables' => false]);

        $guard = $this->app->make(ExternalUrlGuard::class);

        $this->expectException(DocumentGenerationException::class);

        $guard->fetchImageAsDataUri('https://93.184.216.34/logo.png');
    }

    public function test_connection_failure_is_a_controlled_render_error(): void
    {
        Http::fake(fn () => throw new ConnectionException('Connection refused'));

        $guard = $this->app->make(ExternalUrlGuard::class);

        try {
            $guard->fetchImageAsDataUri('https://93.184.216.34/logo.png');
            $this->fail('Expected a controlled exception.');
        } catch (DocumentGenerationException $exception) {
            $this->assertSame('RENDER_ERROR', $exception->getErrorCode());
        }
    }
}
