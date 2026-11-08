<?php

declare(strict_types=1);

namespace App\Enums;

enum DocumentStatus: string
{
    case Pending = 'pending';
    case Processing = 'processing';
    case Completed = 'completed';
    case Failed = 'failed';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'در انتظار',
            self::Processing => 'در حال تولید',
            self::Completed => 'تکمیل شده',
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

    public function isFinal(): bool
    {
        return in_array($this, [self::Completed, self::Failed], true);
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
