<?php

declare(strict_types=1);

namespace Tests\Feature\Documents\Support;

/**
 * Shared fixtures producing realistic-looking PDF bytes for tests.
 */
trait FakePdf
{
    protected static function mockPdfBytes(string $marker = ''): string
    {
        return "%PDF-1.4\n%\xE2\xE3\xCF\xD3\n"
            ."1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
            ."2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
            ."3 0 obj\n<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] >>\nendobj\n"
            .$marker."\n"
            ."trailer\n<< /Root 1 0 R /Size 4 >>\n%%EOF\n";
    }
}
