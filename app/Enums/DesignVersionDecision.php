<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum DesignVersionDecision: string implements HasLabel
{
    case Pending = 'pending';
    case Approved = 'approved';
    case Rejected = 'rejected';
    /** A newer version was uploaded before this one was decided. */
    case Superseded = 'superseded';

    public function getLabel(): ?string
    {
        return __('project.design.decisions.'.$this->value);
    }

    public function color(): string
    {
        return match ($this) {
            self::Pending => 'warning',
            self::Approved => 'success',
            self::Rejected => 'danger',
            self::Superseded => 'gray',
        };
    }
}
