<?php

namespace App\Services;

use App\Enums\{ContractStatus, ContractType, ContractVersionStatus, ReservationStatus};
use App\Models\{
    Company,
    Contract,
    ContractParty,
    ContractTemplateVersion,
    ContractTerm,
    ContractVersion,
    Party,
    Project,
    ProjectPlannedUnit,
    Property,
    Unit,
    UnitReservation,
    User,
};
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ContractDraftService
{
    /** Creates a contract identity and its only editable initial version. */
    public function create(User $actor, array $data): Contract
    {
        $companyId = $this->company($actor, $data);

        if (! $actor->can('create', Contract::class)) {
            throw new AuthorizationException;
        }

        return DB::transaction(function () use ($actor, $data, $companyId): Contract {
            // This serializes legal number allocation on MySQL and PostgreSQL.
            Company::withoutGlobalScopes()->lockForUpdate()->findOrFail($companyId);
            $targets = $this->normaliseTargets($data, $companyId);

            $contract = Contract::withoutGlobalScopes()->create([
                'company_id' => $companyId,
                'contract_number' => $this->nextNumber($companyId),
                'contract_type' => $targets['type'],
                'reservation_id' => $targets['reservation']?->id,
                'property_id' => $targets['property']?->id,
                'project_id' => $targets['project']?->id,
                'unit_id' => $targets['unit']?->id,
                'planned_unit_id' => $targets['plannedUnit']?->id,
                'status' => ContractStatus::Draft,
                'created_by' => $actor->id,
            ]);

            $version = ContractVersion::withoutGlobalScopes()->create([
                'company_id' => $companyId,
                'contract_id' => $contract->id,
                'version_number' => 1,
                'status' => ContractVersionStatus::Draft,
                'template_version_id' => $data['template_version_id'] ?? null,
                'total_amount' => $data['total_amount'] ?? null,
                'currency' => $data['currency'] ?? null,
                'start_date' => $data['start_date'] ?? null,
                'end_date' => $data['end_date'] ?? null,
                'delivery_date' => $data['delivery_date'] ?? null,
                'unit_snapshot' => $this->unitSnapshot($targets['unit'], $targets['plannedUnit']),
                'reservation_snapshot' => $this->reservationSnapshot($targets['reservation']),
                'created_by' => $actor->id,
            ]);

            if (! empty($data['template_version_id'])) {
                $this->copyTemplate($version, (int) $data['template_version_id'], $companyId, $targets['type']);
            }

            $this->replaceParties($version, $data['parties'] ?? [], $companyId);
            $this->appendTerms($version, $data['terms'] ?? [], $companyId);

            return $contract->load('versions.parties');
        });
    }

    /**
     * A draft, or a version returned by its reviewer, is changed in place.
     * Once approved, substantive changes require a new legal version.
     */
    public function updateDraft(User $actor, ContractVersion $version, array $data): ContractVersion
    {
        if (! $actor->can('update_contract_version')) {
            throw new AuthorizationException;
        }

        return DB::transaction(function () use ($actor, $version, $data): ContractVersion {
            $locked = ContractVersion::withoutGlobalScopes()
                ->with(['parties', 'terms'])
                ->lockForUpdate()
                ->findOrFail($version->id);

            $this->sameCompany($actor, $locked->company_id);

            if (! in_array($locked->status, [ContractVersionStatus::Draft, ContractVersionStatus::RevisionRequired], true)) {
                $this->invalid('version', __('Only a draft or returned version can be edited.'));
            }

            $locked->update([
                'total_amount' => $data['total_amount'] ?? null,
                'currency' => $data['currency'] ?? null,
                'start_date' => $data['start_date'] ?? null,
                'end_date' => $data['end_date'] ?? null,
                'delivery_date' => $data['delivery_date'] ?? null,
            ]);

            // These belong to the draft version. Deleting/recreating them cannot
            // touch submitted, approved, or signed legal history.
            $locked->terms()->delete();
            $locked->parties()->delete();
            $this->replaceParties($locked, $data['parties'] ?? [], $locked->company_id);
            $this->replaceTerms($locked, $data['terms'] ?? [], $locked->company_id);

            return $locked->fresh(['parties.party', 'terms']);
        });
    }

    private function company(User $actor, array $data): int
    {
        $id = $actor->isSuperAdmin() ? ($data['company_id'] ?? null) : $actor->company_id;
        if (! $id) {
            $this->invalid('company_id', __('Select the company that will own this contract.'));
        }

        return (int) $id;
    }

    private function nextNumber(int $companyId): string
    {
        $last = Contract::withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->orderByDesc('id')
            ->value('contract_number');
        $number = $last && preg_match('/(\d+)$/', $last, $matches)
            ? ((int) $matches[1] + 1)
            : 1;

        return 'CON-'.now()->format('Y').'-'.str_pad((string) $number, 6, '0', STR_PAD_LEFT);
    }

    /** @return array{reservation:?UnitReservation,property:?Property,project:?Project,unit:?Unit,plannedUnit:?ProjectPlannedUnit,type:ContractType} */
    private function normaliseTargets(array $data, int $companyId): array
    {
        $reservation = $this->owned(UnitReservation::class, $data['reservation_id'] ?? null, $companyId, 'reservation_id');
        $property = $this->owned(Property::class, $data['property_id'] ?? null, $companyId, 'property_id');
        $project = $this->owned(Project::class, $data['project_id'] ?? null, $companyId, 'project_id');
        $unit = $this->owned(Unit::class, $data['unit_id'] ?? null, $companyId, 'unit_id');
        $plannedUnit = $this->owned(ProjectPlannedUnit::class, $data['planned_unit_id'] ?? null, $companyId, 'planned_unit_id');

        if ($reservation) {
            if ($reservation->status !== ReservationStatus::Active) {
                $this->invalid('reservation_id', __('Only an active reservation can be used as a contract source.'));
            }
            $reservationUnit = Unit::withoutGlobalScopes()->findOrFail($reservation->unit_id);
            if ($unit && $unit->id !== $reservationUnit->id) {
                $this->invalid('unit_id', __('The reservation belongs to a different actual unit.'));
            }
            $unit = $reservationUnit;
            $plannedUnit ??= $unit->planned_unit_id
                ? ProjectPlannedUnit::withoutGlobalScopes()->find($unit->planned_unit_id)
                : null;
        }

        if ($unit) {
            if ($property && (int) $property->id !== (int) $unit->property_id) {
                $this->invalid('property_id', __('The selected property does not own the actual unit.'));
            }
            $property ??= Property::withoutGlobalScopes()->find($unit->property_id);
            if ($project && (int) $project->id !== (int) $unit->project_id) {
                $this->invalid('project_id', __('The selected project does not own the actual unit.'));
            }
            $project ??= $unit->project_id ? Project::withoutGlobalScopes()->find($unit->project_id) : null;
        }

        if ($plannedUnit) {
            $plannedUnit->loadMissing('floor.building.project');
            $plannedProject = $plannedUnit->floor?->building?->project;
            if (! $plannedProject) {
                $this->invalid('planned_unit_id', __('The planned unit is missing its project hierarchy.'));
            }
            if ($project && $project->id !== $plannedProject->id) {
                $this->invalid('project_id', __('The selected project does not own the planned unit.'));
            }
            $project ??= $plannedProject;
            if ($unit && (int) $unit->planned_unit_id !== (int) $plannedUnit->id) {
                $this->invalid('planned_unit_id', __('The actual unit is not the execution of this planned unit.'));
            }
        }

        $value = $data['contract_type'] instanceof ContractType
            ? $data['contract_type']->value
            : (string) ($data['contract_type'] ?? '');
        $type = ContractType::tryFrom($value);
        if (! $type) {
            $this->invalid('contract_type', __('Select a valid contract type.'));
        }
        if ($type->requiresActualUnit() && ! $unit) {
            $this->invalid('unit_id', __('Sale and lease contracts require an actual unit.'));
        }
        if ($type->requiresPlannedUnit() && ! $plannedUnit) {
            $this->invalid('planned_unit_id', __('An off-plan sale contract requires a planned unit.'));
        }

        return compact('reservation', 'property', 'project', 'unit', 'plannedUnit', 'type');
    }

    private function owned(string $class, mixed $id, int $companyId, string $field): ?object
    {
        if (! $id) {
            return null;
        }
        $record = $class::withoutGlobalScopes()->findOrFail($id);
        if ((int) $record->company_id !== $companyId) {
            $this->invalid($field, __('The selected record belongs to another company.'));
        }

        return $record;
    }

    private function copyTemplate(ContractVersion $version, int $templateVersionId, int $companyId, ContractType $contractType): void
    {
        $templateVersion = ContractTemplateVersion::withoutGlobalScopes()->with('template')->findOrFail($templateVersionId);
        if ((int) $templateVersion->company_id !== $companyId || ! $templateVersion->template || ! $templateVersion->template->is_active) {
            $this->invalid('template_version_id', __('Select an active template from the contract company.'));
        }
        if ($templateVersion->template->contract_type !== $contractType) {
            $this->invalid('template_version_id', __('The template type must match the contract type.'));
        }

        foreach ($templateVersion->clauses ?? [] as $index => $clause) {
            $version->terms()->create([
                'company_id' => $companyId,
                'term_type' => $clause['type'] ?? 'clause',
                'position' => $index + 1,
                'title' => $clause['title'] ?? __('Clause'),
                'text' => $clause['text'] ?? null,
            ]);
        }
    }

    private function replaceParties(ContractVersion $version, array $parties, int $companyId): void
    {
        $seen = [];
        foreach ($parties as $item) {
            $partyId = (int) ($item['party_id'] ?? 0);
            $role = trim((string) ($item['role'] ?? ''));
            if (! $partyId || $role === '') {
                $this->invalid('parties', __('Every contract party needs a party and role.'));
            }
            if (isset($seen[$partyId.'|'.$role])) {
                $this->invalid('parties', __('The same party role may only be added once per version.'));
            }
            $seen[$partyId.'|'.$role] = true;

            $party = Party::withoutGlobalScopes()->findOrFail($partyId);
            if ((int) $party->company_id !== $companyId) {
                $this->invalid('parties', __('Selected party belongs to another company.'));
            }
            $share = $item['share_percentage'] ?? null;
            if ($share !== null && ((float) $share < 0 || (float) $share > 100)) {
                $this->invalid('parties', __('A party share must be between 0 and 100.'));
            }

            ContractParty::withoutGlobalScopes()->create([
                'company_id' => $companyId,
                'contract_version_id' => $version->id,
                'party_id' => $party->id,
                'role' => $role,
                'share_percentage' => $share,
                'snapshot' => [
                    'name' => $party->name,
                    'legal_name' => $party->legal_name,
                    'national_id' => $party->national_id,
                    'registration_number' => $party->registration_number,
                    'tax_number' => $party->tax_number,
                    'address' => $party->address,
                    'email' => $party->email,
                    'phone' => $party->phone,
                ],
            ]);
        }
    }

    private function replaceTerms(ContractVersion $version, array $terms, int $companyId): void
    {
        $this->appendTerms($version, $terms, $companyId);
    }

    private function appendTerms(ContractVersion $version, array $terms, int $companyId): void
    {
        $offset = (int) $version->terms()->max('position');
        foreach (array_values($terms) as $index => $term) {
            $title = trim((string) ($term['title'] ?? ''));
            if ($title === '') {
                $this->invalid("terms.{$index}.title", __('Every contract term needs a title.'));
            }
            ContractTerm::withoutGlobalScopes()->create([
                'company_id' => $companyId,
                'contract_version_id' => $version->id,
                'term_type' => $term['term_type'] ?? 'clause',
                'position' => $offset + $index + 1,
                'title' => $title,
                'text' => $term['text'] ?? null,
                'metadata' => $term['metadata'] ?? null,
            ]);
        }
    }

    private function unitSnapshot(?Unit $unit, ?ProjectPlannedUnit $plannedUnit): ?array
    {
        if (! $unit && ! $plannedUnit) {
            return null;
        }
        $unit?->loadMissing(['property', 'project', 'plannedUnit.floor.building']);
        $plannedUnit?->loadMissing('floor.building.project');

        return [
            'actual_unit_id' => $unit?->id,
            'planned_unit_id' => $plannedUnit?->id ?? $unit?->planned_unit_id,
            'unit_number' => $unit?->unit_number,
            'planned_unit_code' => $plannedUnit?->code ?? $unit?->plannedUnit?->code,
            'property_id' => $unit?->property_id,
            'property_name' => $unit?->property?->name,
            'project_id' => $unit?->project_id ?? $plannedUnit?->floor?->building?->project_id,
            'project_name' => $unit?->project?->name ?? $plannedUnit?->floor?->building?->project?->name,
            'unit_type' => $unit?->type ?? $plannedUnit?->unit_type,
            'area' => $unit?->actual_area ?? $plannedUnit?->planned_area,
            'area_unit' => $unit?->area_unit,
            'building_name' => $unit?->plannedUnit?->floor?->building?->name ?? $plannedUnit?->floor?->building?->name,
        ];
    }

    private function reservationSnapshot(?UnitReservation $reservation): ?array
    {
        if (! $reservation) {
            return null;
        }
        return [
            'reservation_id' => $reservation->id,
            'reservation_number' => $reservation->reservation_number,
            'source' => $reservation->source?->value ?? $reservation->source,
            'transaction_type' => $reservation->transaction_type?->value ?? $reservation->transaction_type,
            'agreed_amount' => $reservation->agreed_amount,
            'currency' => $reservation->currency,
            'valid_until' => optional($reservation->valid_until)->toIso8601String(),
            'payment_method' => $reservation->payment_method,
            'payment_terms' => $reservation->payment_terms,
            'terms' => $reservation->terms_json,
        ];
    }

    private function sameCompany(User $actor, int $companyId): void
    {
        if (! $actor->isSuperAdmin() && (int) $actor->company_id !== $companyId) {
            throw new AuthorizationException;
        }
    }

    private function invalid(string $key, string $message): never
    {
        throw ValidationException::withMessages([$key => $message]);
    }
}
