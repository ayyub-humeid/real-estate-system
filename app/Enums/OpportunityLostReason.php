<?php
namespace App\Enums;
use Filament\Support\Contracts\HasLabel;
enum OpportunityLostReason: string implements HasLabel { case PriceTooHigh = 'price_too_high'; case CustomerChangedMind = 'customer_changed_mind'; case PropertyNotSuitable = 'property_not_suitable'; case Competitor = 'competitor'; case FinancingIssue = 'financing_issue'; case NoResponse = 'no_response'; case Other = 'other'; public function getLabel(): ?string{return __('crm.lost_reasons.'.$this->value);} public static function options(): array { return collect(self::cases())->mapWithKeys(fn(self $case) => [$case->value => $case->getLabel()])->all(); } }
