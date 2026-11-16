<?php

declare(strict_types=1);

namespace Tests\Unit\Support;

use App\Support\QrCode;
use PHPUnit\Framework\TestCase;

class QrCodeTest extends TestCase
{
    public function test_svg_is_local_markup_without_remote_urls(): void
    {
        $svg = QrCode::svg('AH-HTL-1405-88421', 96);

        $this->assertStringStartsWith('<svg', $svg);
        $this->assertStringContainsString('viewBox="0 0 ', $svg);
        $this->assertStringNotContainsString('https://', $svg);
        $this->assertDoesNotMatchRegularExpression('/\bhttps?:\/\//', $svg);
        $this->assertStringNotContainsString('<script', $svg);
        $this->assertStringNotContainsString('AH-HTL', $svg);
    }

    public function test_empty_text_renders_nothing(): void
    {
        $this->assertSame('', QrCode::svg('   '));
    }

    public function test_matrix_has_finder_patterns(): void
    {
        $matrix = QrCode::matrix(array_values(unpack('C*', 'HELLO') ?: []));

        $this->assertCount(21, $matrix);
        $this->assertTrue($matrix[0][0]);
        $this->assertTrue($matrix[0][6]);
        $this->assertFalse($matrix[0][7]);
        $this->assertTrue($matrix[3][3]);
    }
}
