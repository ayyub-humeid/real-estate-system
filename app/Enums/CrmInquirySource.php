<?php
namespace App\Enums;
use Filament\Support\Contracts\HasLabel;
enum CrmInquirySource: string implements HasLabel { case Phone = 'phone'; case WhatsApp = 'whatsapp'; case Facebook = 'facebook'; case Instagram = 'instagram'; case Website = 'website'; case WalkIn = 'walk_in'; case Manual = 'manual'; case Other = 'other'; public function getLabel(): ?string{return __('crm.sources.'.$this->value);} public static function options(): array { return collect(self::cases())->mapWithKeys(fn(self $case) => [$case->value => $case->getLabel()])->all(); } }
