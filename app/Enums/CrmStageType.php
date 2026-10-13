<?php
namespace App\Enums;
use Filament\Support\Contracts\HasLabel;
enum CrmStageType: string implements HasLabel { case Open = 'open'; case Won = 'won'; case Lost = 'lost'; public function getLabel(): ?string{return __('crm.stage_types.'.$this->value);} public static function options(): array { return collect(self::cases())->mapWithKeys(fn(self $case) => [$case->value => $case->getLabel()])->all(); } }
