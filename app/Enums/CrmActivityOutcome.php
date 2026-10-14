<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum CrmActivityOutcome: string implements HasLabel
{
    case Interested = 'interested'; case NoAnswer = 'no_answer'; case NotInterested = 'not_interested'; case Completed = 'completed'; case Cancelled = 'cancelled';
    public function getLabel(): ?string { return __('crm.activities.outcomes.' . $this->value); }
    public static function optionsFor(CrmActivityType $type): array
    {
        $allowed = match ($type) {
            CrmActivityType::Call => [self::Interested, self::NoAnswer, self::NotInterested, self::Completed],
            CrmActivityType::Viewing, CrmActivityType::Meeting => [self::Completed, self::Cancelled],
            CrmActivityType::Message => [self::Interested, self::NoAnswer, self::Completed],
            CrmActivityType::Note => [],
        };
        return collect($allowed)->mapWithKeys(fn (self $case) => [$case->value => $case->getLabel()])->all();
    }
}
