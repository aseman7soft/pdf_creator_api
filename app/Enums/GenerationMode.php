<?php

declare(strict_types=1);

namespace App\Enums;

enum GenerationMode: string
{
    case Sync = 'sync';
    case Async = 'async';

    public function label(): string
    {
        return match ($this) {
            self::Sync => 'هم‌زمان',
            self::Async => 'غیرهم‌زمان (صف)',
        };
    }
}
