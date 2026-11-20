<?php

namespace App\Filament\Support;

use App\Enums\{OpportunityType, UnitStatus};
use App\Models\{Project, Unit};
use Closure;
use Filament\Forms;

/**
 * Project -> Unit selection shared by every form that records which unit a
 * customer is interested in (opportunity form, lead opportunities tab, offer form).
 * Only Ready units of the same company are offered, so a sold, rented,
 * reserved or inactive unit can never be picked for a new customer.
 */
class OpportunityUnitFields
{
    /**
     * @param Closure(Forms\Get): ?int $companyId   company whose projects/units are listed
     * @param Closure(Forms\Get): mixed|null $type  current opportunity type (sale/rent) to show the matching price
     * @return array<int, Forms\Components\Component>
     */
    public static function schema(Closure $companyId, ?Closure $type = null, bool $withProject = true, ?string $unitHelp = null): array
    {
        $fields = [];

        if ($withProject) {
            $fields[] = Forms\Components\Select::make('project_id')
                ->label(__('sales.fields.project'))
                ->helperText(__('sales.help.opportunity_project'))
                ->options(fn (Forms\Get $get): array => self::projects($companyId($get)))
                ->searchable()
                ->live()
                ->afterStateUpdated(fn (Forms\Set $set) => $set('unit_id', null));
        }

        $fields[] = Forms\Components\Select::make('unit_id')
            ->label(__('sales.fields.unit'))
            ->helperText($unitHelp ?? __('sales.help.opportunity_unit'))
            ->options(fn (Forms\Get $get): array => self::unitOptions($companyId($get), $withProject ? $get('project_id') : null, $type ? $type($get) : null))
            ->getOptionLabelUsing(fn ($value): ?string => ($unit = Unit::withoutGlobalScopes()->with('property:id,name')->find($value)) ? self::label($unit, null) : null)
            ->searchable();

        return $fields;
    }

    private static function projects(?int $companyId): array
    {
        if (! $companyId) {
            return [];
        }

        return Project::withoutGlobalScopes()->where('company_id', $companyId)->orderBy('name')->limit(200)->pluck('name', 'id')->all();
    }

    /** Ready units of the company (optionally of one project), keyed by id. Public so it can be tested without a browser. */
    public static function unitOptions(?int $companyId, mixed $projectId, mixed $type): array
    {
        if (! $companyId) {
            return [];
        }

        $type = $type instanceof OpportunityType ? $type : OpportunityType::tryFrom((string) $type);

        return Unit::withoutGlobalScopes()
            ->with('property:id,name')
            ->where('company_id', $companyId)
            ->where('status', UnitStatus::Ready->value)
            ->when(filled($projectId), fn ($query) => $query->where('project_id', $projectId))
            ->orderBy('unit_number')
            ->limit(150)
            ->get()
            ->mapWithKeys(fn (Unit $unit): array => [$unit->id => self::label($unit, $type)])
            ->all();
    }

    /** "A-101 — Tower One · 120,000 USD" (the price that matches the deal type, when known). */
    public static function label(Unit $unit, ?OpportunityType $type): string
    {
        $label = __('sales.fields.unit_label', ['number' => $unit->unit_number, 'property' => $unit->property?->name ?? '—']);
        [$price, $currency] = match ($type) {
            OpportunityType::Sale => [$unit->sale_price, $unit->sale_currency],
            OpportunityType::Rent => [$unit->rent_price, $unit->rent_currency],
            default => [null, null],
        };

        return filled($price) && (float) $price > 0 ? $label.' · '.number_format((float) $price).' '.$currency : $label;
    }
}
