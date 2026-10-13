<?php
namespace App\Enums;
use Filament\Support\Contracts\HasLabel;
enum LeadStatus: string implements HasLabel { case Active = 'active'; case Inactive = 'inactive'; public function getLabel(): ?string{return __('crm.lead_status.'.$this->value);} public static function options(): array { return collect(self::cases())->mapWithKeys(fn(self $case) => [$case->value => $case->getLabel()])->all(); } }
