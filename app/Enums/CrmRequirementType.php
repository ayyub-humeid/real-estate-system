<?php
namespace App\Enums;
use Filament\Support\Contracts\HasLabel;
enum CrmRequirementType: string implements HasLabel
{
    case FieldRequired = 'field_required';
    case FieldEquals = 'field_equals';
    case RelatedRecordExists = 'related_record_exists';
    case ChecklistCompleted = 'checklist_completed';
    case ActivityExists = 'activity_exists';
    public function getLabel(): ?string
    {
        return __('crm.requirement_types.' . $this->value);
    }
    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn(self $case) => [$case->value => $case->getLabel()])->all();
    }
}
