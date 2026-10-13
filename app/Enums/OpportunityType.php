<?php
namespace App\Enums;
use Filament\Support\Contracts\HasLabel;
enum OpportunityType: string implements HasLabel { case Sale = 'sale'; case Rent = 'rent'; public function getLabel(): ?string{return __('crm.opportunity_types.'.$this->value);} public static function options(): array { return collect(self::cases())->mapWithKeys(fn(self $case) => [$case->value => $case->getLabel()])->all(); } }
