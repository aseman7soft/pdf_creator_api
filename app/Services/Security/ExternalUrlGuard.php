<?php

declare(strict_types=1);

namespace App\Services\Security;

use App\Exceptions\DocumentGenerationException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

/**
 * SSRF guard for remote image variables.
 *
 * Rules:
 *  - http/https only, no user-info in the URL,
 *  - every host (including redirect targets) is resolved and ALL resolved
 *    addresses must be public/routable — loopback, private, link-local,
 *    carrier-grade NAT, multicast and documentation ranges are rejected,
 *  - response size and MIME type are checked against config before the bytes
 *    are turned into a data: URI,
 *  - the HTTP client never talks to the internal Gotenberg host because only
 *    the template data controls this guard and the guard only allows public
 *    addresses.
 */
final class ExternalUrlGuard
{
    private const MAX_REDIRECTS = 2;

    /**
     * @var (callable(string): list<string>)|null
     */
    private $resolver;

    /**
     * @param  (callable(string): list<string>)|null  $resolver  Test seam for DNS resolution.
     */
    public function __construct(?callable $resolver = null)
    {
        $this->resolver = $resolver;
    }

    /**
     * Validate a URL before it is fetched. Throws a controlled exception when
     * the URL is not an allowed public http(s) address.
     */
    public function assertAllowed(string $url): void
    {
        $parts = parse_url($url);

        if (! is_array($parts) || ! isset($parts['scheme'], $parts['host'])) {
            throw DocumentGenerationException::renderError('آدرس تصویر معتبر نیست.');
        }

        $scheme = strtolower((string) $parts['scheme']);

        if (! in_array($scheme, ['http', 'https'], true)) {
            throw DocumentGenerationException::renderError('تنها آدرس‌های http و https برای تصویر اینترنتی مجاز هستند.');
        }

        if (isset($parts['user']) || isset($parts['pass'])) {
            throw DocumentGenerationException::renderError('آدرس تصویر نباید شامل اطلاعات ورود باشد.');
        }

        $host = (string) $parts['host'];

        if ($host === '' || str_contains($host, ' ')) {
            throw DocumentGenerationException::renderError('میزبان آدرس تصویر معتبر نیست.');
        }

        foreach ($this->resolveHost($host) as $ip) {
            if (! $this->isPublicAddress($ip)) {
                throw DocumentGenerationException::renderError('آدرس تصویر به شبکه خصوصی یا محلی اشاره می‌کند و مجاز نیست.');
            }
        }
    }

    /**
     * Fetch a remote image and return it as a data: URI, or throw a controlled
     * render error when it is disallowed/unavailable/invalid.
     */
    public function fetchImageAsDataUri(string $url): string
    {
        if (! (bool) config('pdf.security.allow_remote_image_variables', true)) {
            throw DocumentGenerationException::renderError('دریافت تصویر از اینترنت برای متغیرهای تصویری غیرفعال است.');
        }

        $maxBytes = max(1, (int) config('pdf.security.max_remote_image_bytes', 5 * 1024 * 1024));
        $timeout = max(1, (int) config('pdf.security.remote_image_timeout', 10));
        $currentUrl = $url;
        $response = null;

        for ($redirect = 0; $redirect <= self::MAX_REDIRECTS; $redirect++) {
            $this->assertAllowed($currentUrl);
            $response = $this->request($currentUrl, $timeout);
            $status = $response->status();

            if ($status >= 300 && $status < 400) {
                $location = $response->header('Location');

                if ($location === '' || $location === null) {
                    throw DocumentGenerationException::renderError('پاسخ انتقال تصویر معتبر نیست.');
                }

                $currentUrl = $this->absoluteUrl($currentUrl, $location);

                continue;
            }

            break;
        }

        if (! $response instanceof Response || $response->status() !== 200) {
            $status = $response instanceof Response ? $response->status() : 0;

            throw DocumentGenerationException::renderError('دریافت تصویر از آدرس داده‌شده ناموفق بود. (کد '.$status.')');
        }

        $contentLength = (int) ($response->header('Content-Length') ?: 0);

        if ($contentLength > $maxBytes) {
            throw DocumentGenerationException::renderError('حجم تصویر اینترنتی بیش از حد مجاز است.');
        }

        $body = $response->body();

        if (strlen($body) > $maxBytes) {
            throw DocumentGenerationException::renderError('حجم تصویر اینترنتی بیش از حد مجاز است.');
        }

        if ($body === '') {
            throw DocumentGenerationException::renderError('تصویر دریافتی خالی است.');
        }

        $mime = $this->detectMime($response, $body);
        $allowedMimes = (array) config('pdf.security.allowed_image_mimes', [
            'image/png', 'image/jpeg', 'image/webp', 'image/gif',
        ]);

        if (! in_array($mime, $allowedMimes, true)) {
            throw DocumentGenerationException::renderError('نوع فایل تصویر دریافتی مجاز نیست.');
        }

        return 'data:'.$mime.';base64,'.base64_encode($body);
    }

    private function request(string $url, int $timeout): Response
    {
        try {
            return Http::timeout($timeout)
                ->connectTimeout(min(5, $timeout))
                ->withOptions([
                    'allow_redirects' => false,
                    'http_errors' => false,
                ])
                ->withHeaders(['Accept' => 'image/*'])
                ->get($url);
        } catch (ConnectionException $exception) {
            throw DocumentGenerationException::renderError('اتصال به آدرس تصویر ممکن نبود.', $exception);
        } catch (\Throwable $exception) {
            throw DocumentGenerationException::renderError('دریافت تصویر از اینترنت با خطا مواجه شد.', $exception);
        }
    }

    /**
     * @return list<string>
     */
    private function resolveHost(string $host): array
    {
        if (filter_var($host, FILTER_VALIDATE_IP) !== false) {
            return [$host];
        }

        if ($this->resolver !== null) {
            /** @var list<string> $addresses */
            $addresses = array_values(array_filter(
                (array) ($this->resolver)($host),
                static fn (mixed $address): bool => is_string($address) && $address !== '',
            ));

            if ($addresses === []) {
                throw DocumentGenerationException::renderError('میزبان آدرس تصویر قابل شناسایی نیست.');
            }

            return $addresses;
        }

        $addresses = [];

        $ipv4 = @gethostbynamel($host);

        if (is_array($ipv4)) {
            $addresses = array_merge($addresses, $ipv4);
        }

        try {
            $records = @dns_get_record($host, DNS_AAAA);

            if (is_array($records)) {
                foreach ($records as $record) {
                    if (isset($record['ipv6']) && is_string($record['ipv6'])) {
                        $addresses[] = $record['ipv6'];
                    }
                }
            }
        } catch (\Throwable) {
            // DNS failures are treated as "no address" below.
        }

        $addresses = array_values(array_unique(array_filter(
            $addresses,
            static fn (mixed $address): bool => is_string($address) && $address !== '',
        )));

        if ($addresses === []) {
            throw DocumentGenerationException::renderError('میزبان آدرس تصویر قابل شناسایی نیست.');
        }

        return $addresses;
    }

    private function isPublicAddress(string $ip): bool
    {
        if (filter_var($ip, FILTER_VALIDATE_IP) === false) {
            return false;
        }

        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false) {
            return false;
        }

        if (str_contains($ip, ':')) {
            return $this->isPublicIpv6($ip);
        }

        $long = ip2long($ip);

        if ($long === false) {
            return false;
        }

        $blockedRanges = [
            ['0.0.0.0', 8],
            ['10.0.0.0', 8],
            ['100.64.0.0', 10],
            ['127.0.0.0', 8],
            ['169.254.0.0', 16],
            ['172.16.0.0', 12],
            ['192.0.0.0', 24],
            ['192.0.2.0', 24],
            ['192.168.0.0', 16],
            ['198.18.0.0', 15],
            ['198.51.100.0', 24],
            ['203.0.113.0', 24],
            ['224.0.0.0', 4],
            ['240.0.0.0', 4],
        ];

        foreach ($blockedRanges as [$network, $prefix]) {
            $networkLong = ip2long($network);

            if ($networkLong === false) {
                continue;
            }

            $mask = $prefix === 0 ? 0 : (-1 << (32 - $prefix));

            if (($long & $mask) === ($networkLong & $mask)) {
                return false;
            }
        }

        return true;
    }

    private function isPublicIpv6(string $ip): bool
    {
        $packed = @inet_pton($ip);

        if ($packed === false || strlen($packed) !== 16) {
            return false;
        }

        $firstTwo = unpack('n', substr($packed, 0, 2))[1] ?? 0;
        $firstByte = ord($packed[0]);

        if ($packed === inet_pton('::1') || $packed === inet_pton('::')) {
            return false;
        }

        // fe80::/10 link-local
        if (($firstTwo & 0xFFC0) === 0xFE80) {
            return false;
        }

        // fc00::/7 unique local
        if (($firstByte & 0xFE) === 0xFC) {
            return false;
        }

        // 2001:db8::/32 documentation
        if (($firstTwo === 0x2001) && ($packed[2] === "\x0d") && ($packed[3] === "\xb8")) {
            return false;
        }

        // ::ffff:0:0/96 IPv4-mapped addresses: validate the embedded IPv4.
        if (substr($packed, 0, 12) === str_repeat("\x00", 10)."\xff\xff") {
            $embedded = inet_ntop(substr($packed, 12, 4));

            return $embedded !== false && $this->isPublicAddress($embedded);
        }

        // ff00::/8 multicast
        if ($firstByte === 0xFF) {
            return false;
        }

        return true;
    }

    private function absoluteUrl(string $base, string $location): string
    {
        if (preg_match('/^[a-z][a-z0-9+.-]*:\/\//i', $location) === 1) {
            return $location;
        }

        $parts = parse_url($base);

        if (! is_array($parts) || ! isset($parts['scheme'], $parts['host'])) {
            throw DocumentGenerationException::renderError('آدرس انتقال تصویر معتبر نیست.');
        }

        $origin = $parts['scheme'].'://'.$parts['host'];

        if (isset($parts['port'])) {
            $origin .= ':'.$parts['port'];
        }

        if (str_starts_with($location, '/')) {
            return $origin.$location;
        }

        $directory = isset($parts['path']) ? rtrim(dirname($parts['path']), '/') : '';

        return $origin.$directory.'/'.$location;
    }

    private function detectMime(Response $response, string $body): string
    {
        $header = (string) ($response->header('Content-Type') ?: '');
        $header = strtolower(trim(explode(';', $header)[0]));

        if ($header !== '') {
            return match ($header) {
                'image/jpg' => 'image/jpeg',
                'image/x-png' => 'image/png',
                default => $header,
            };
        }

        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $detected = $finfo->buffer($body);

        return is_string($detected) ? strtolower($detected) : '';
    }
}
