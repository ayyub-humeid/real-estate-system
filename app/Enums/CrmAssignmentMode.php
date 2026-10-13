<?php
namespace App\Enums;
use Filament\Support\Contracts\HasLabel;
enum CrmAssignmentMode: string implements HasLabel
{
    case Manual = 'manual';
    case RoundRobin = 'round_robin';
    case UnassignedQueue = 'unassigned_queue';
    public function getLabel(): ?string
    {
        return __('crm.assignment_modes.' . $this->value);
    }
    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn(self $case) => [$case->value => $case->getLabel()])->all();
    }
}
