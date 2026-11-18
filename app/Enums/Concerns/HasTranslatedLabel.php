<?php

namespace App\Enums\Concerns;

use Illuminate\Support\Str;

/** Label lookup: lang/{locale}/enums.php => enums.<enum_snake_name>.<case value>. */
trait HasTranslatedLabel
{
    public function getLabel(): ?string
    {
        return self::labelFor($this->value);
    }

    public static function labelFor(?string $value): string
    {
        if ($value === null || $value === '') {
            return '—';
        }

        $key = 'enums.' . Str::snake(class_basename(static::class)) . '.' . $value;
        $label = __($key);

        return is_string($label) && $label !== $key ? $label : $value;
    }

    /** @return array<string, string> */
    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $case) => [$case->value => $case->getLabel()])
            ->all();
    }

    /** @return array<int, string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
