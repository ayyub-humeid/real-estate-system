<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

/** Computed from the package's latest drawing version; never set by hand. */
enum DesignPackageStatus: string implements HasLabel
{
    case NoDrawings = 'no_drawings';
    case AwaitingDecision = 'awaiting_decision';
    case ChangesRequested = 'changes_requested';
    case Approved = 'approved';

    public function getLabel(): ?string
    {
        return __('project.design.statuses.'.$this->value);
    }

    public function description(): string
    {
        return __('project.design.status_descriptions.'.$this->value);
    }

    public function color(): string
    {
        return match ($this) {
            self::NoDrawings => 'gray',
            self::AwaitingDecision => 'warning',
            self::ChangesRequested => 'danger',
            self::Approved => 'success',
        };
    }

    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn (self $case): array => [$case->value => $case->getLabel()])->all();
    }
}
