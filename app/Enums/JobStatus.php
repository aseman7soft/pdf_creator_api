<?php

declare(strict_types=1);

namespace App\Enums;

enum JobStatus: string
{
    case Pending = 'pending';
    case Processing = 'processing';
    case Completed = 'completed';
    case Failed = 'failed';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'در صف',
            self::Processing => 'در حال اجرا',
            self::Completed => 'موفق',
            self::Failed => 'ناموفق',
        };
    }

    public function badgeClass(): string
    {
        return match ($this) {
            self::Pending => 'text-bg-secondary',
            self::Processing => 'text-bg-info',
            self::Completed => 'text-bg-success',
            self::Failed => 'text-bg-danger',
        };
    }

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        $options = [];

        foreach (self::cases() as $case) {
            $options[$case->value] = $case->label();
        }

        return $options;
    }
}
