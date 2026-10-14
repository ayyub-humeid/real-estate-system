<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum CrmTaskStatus: string implements HasLabel
{
    case Pending = 'pending';
    case InProgress = 'in_progress';
    case Completed = 'completed';
    case Cancelled = 'cancelled';

    public function getLabel(): ?string { return __('crm.tasks.statuses.' . $this->value); }
    public function color(): string
    {
        return match ($this) {
            self::Pending => 'warning',
            self::InProgress => 'info',
            self::Completed => 'success',
            self::Cancelled => 'danger',
        };
    }
    public static function options(): array { return collect(self::cases())->mapWithKeys(fn (self $case) => [$case->value => $case->getLabel()])->all(); }
}
