<?php

declare(strict_types=1);

namespace App\Enums;

enum TemplateVariableType: string
{
    case String = 'string';
    case Text = 'text';
    case Integer = 'integer';
    case Decimal = 'decimal';
    case Boolean = 'boolean';
    case Date = 'date';
    case DateTime = 'datetime';
    case Image = 'image';
    case Url = 'url';
    case Array = 'array';
    case Object = 'object';

    public function label(): string
    {
        return match ($this) {
            self::String => 'متن کوتاه (String)',
            self::Text => 'متن بلند (Text)',
            self::Integer => 'عدد صحیح (Integer)',
            self::Decimal => 'عدد اعشاری (Decimal)',
            self::Boolean => 'بولین (Boolean)',
            self::Date => 'تاریخ (Date)',
            self::DateTime => 'تاریخ و زمان (DateTime)',
            self::Image => 'تصویر (Image)',
            self::Url => 'آدرس اینترنتی (URL)',
            self::Array => 'آرایه (Array)',
            self::Object => 'شیء (Object)',
        };
    }

    public function isScalar(): bool
    {
        return in_array($this, [
            self::String,
            self::Text,
            self::Integer,
            self::Decimal,
            self::Boolean,
            self::Date,
            self::DateTime,
            self::Image,
            self::Url,
        ], true);
    }

    /**
     * PHP scalar type used when casting an incoming value for rendering.
     */
    public function castType(): string
    {
        return match ($this) {
            self::Integer => 'int',
            self::Decimal => 'float',
            self::Boolean => 'bool',
            self::Array, self::Object => 'array',
            default => 'string',
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
