<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum CrmActivityType: string implements HasLabel
{
    case Call = 'call'; case Meeting = 'meeting'; case Viewing = 'viewing'; case Message = 'message'; case Note = 'note';
    public function getLabel(): ?string { return __('crm.activities.types.' . $this->value); }
    public static function options(): array { return collect(self::cases())->mapWithKeys(fn (self $case) => [$case->value => $case->getLabel()])->all(); }
}
