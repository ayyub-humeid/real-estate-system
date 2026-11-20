<?php

namespace App\Filament\Resources\UnitReservationResource\Pages;

use App\Enums\{ContractType, ReservationStatus};
use App\Filament\Resources\ContractResource;
use App\Filament\Resources\UnitReservationResource;
use App\Filament\Resources\UnitReservationResource\RelationManagers\ReservationStatusHistoriesRelationManager;
use App\Filament\Support\RunsWorkflowAction;
use App\Models\{Company, Contract, ContractTemplateVersion, Party};
use App\Services\{ContractDraftService, ReservationWorkflowService};
use Filament\Actions;
use Filament\Forms;
use Filament\Infolists\Infolist;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Support\HtmlString;

class ViewUnitReservation extends ViewRecord
{
    use RunsWorkflowAction;

    protected static string $resource = UnitReservationResource::class;

    public function getTitle(): string { return $this->record->reservation_number; }

    public function getSubheading(): ?string
    {
        $status = $this->record->status;

        if ($status === ReservationStatus::Active && $this->record->valid_until) {
            return __('reservations.guidance.active', ['date' => $this->record->valid_until->translatedFormat('Y-m-d')]);
        }
        if ($status === ReservationStatus::Active) {
            return __('reservations.guidance.active_no_date');
        }
        if ($status === ReservationStatus::Expired && $this->record->valid_until) {
            return __('reservations.guidance.expired', ['date' => $this->record->valid_until->translatedFormat('Y-m-d')]);
        }

        return __('reservations.guidance.' . $status->value);
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make()->label(__('crm.actions.edit'))->visible(fn () => $this->record->status->value === 'draft'),
            $this->workflowAction('send', __('reservations.actions.send_for_approval'), 'info'),
            $this->workflowAction('approve', __('reservations.actions.approve_activate'), 'success'),
            $this->workflowAction('reject', __('reservations.actions.reject'), 'danger', true),
            $this->workflowAction('cancel', __('reservations.actions.cancel'), 'danger', true),
            $this->workflowAction('extend', __('reservations.actions.extend'), 'warning', false, true),
            $this->workflowAction('expire', __('reservations.actions.expire'), 'warning'),
            $this->createContractAction(),
        ];
    }

    public function getRelationManagers(): array { return [ReservationStatusHistoriesRelationManager::class]; }
    public function infolist(Infolist $infolist): Infolist { return UnitReservationResource::infolist($infolist); }

    private function workflowAction(string $name, string $label, string $color, bool $reason = false, bool $extend = false): Actions\Action
    {
        return Actions\Action::make($name)
            ->label($label)->modalHeading($label)->color($color)->requiresConfirmation()
            ->modalDescription(__('reservations.action_descriptions.' . $name))
            ->form(fn (): array => UnitReservationResource::workflowForm($this->record, $reason, $extend))
            ->visible(fn (): bool => UnitReservationResource::workflowActionAllowed($name, $this->record)
                && auth()->user()->can(UnitReservationResource::workflowAbility($name), $this->record))
            ->action(function (array $data) use ($name, $label): void {
                $this->runWorkflowAction(
                    operation: function () use ($name, $data) {
                        $service = app(ReservationWorkflowService::class);

                        return match ($name) {
                            'send' => $service->sendForApproval(auth()->user(), $this->record),
                            'approve' => $service->approveAndActivate(auth()->user(), $this->record),
                            'reject' => $service->rejectRequest(auth()->user(), $this->record, $data['reason'] ?? null),
                            'cancel' => $service->cancelReservation(auth()->user(), $this->record, $data['reason'] ?? null),
                            'extend' => $service->extendReservation(auth()->user(), $this->record, $data['valid_until']),
                            'expire' => $service->expireReservation(auth()->user(), $this->record),
                        };
                    },
                    successTitle: $label,
                    onSuccess: function (): void {
                        $this->record = $this->record->fresh(['snapshot']);
                    },
                    rethrowValidation: true,
                );
            });
    }

    private function createContractAction(): Actions\Action
    {
        return Actions\Action::make('create_contract')
            ->label(__('contract_domain.from_reservation.action'))
            ->icon('heroicon-o-document-plus')->color('primary')
            ->tooltip(__('contract_domain.from_reservation.tooltip'))
            ->modalHeading(__('contract_domain.from_reservation.heading'))
            ->modalDescription(fn (): HtmlString => $this->contractSummary())
            ->modalSubmitActionLabel(__('contract_domain.from_reservation.submit'))
            ->visible(fn (): bool => $this->record->status === ReservationStatus::Active
                && ! Contract::withoutGlobalScopes()->where('reservation_id', $this->record->id)->exists()
                && auth()->user()->can('create', Contract::class)
                && auth()->user()->can('create_contract_from_reservation'))
            ->form(fn (): array => [
                Forms\Components\Select::make('template_version_id')
                    ->label(__('Template version'))
                    ->options(fn (): array => ContractTemplateVersion::withoutGlobalScopes()
                        ->with('template:id,name')
                        ->where('company_id', $this->record->company_id)
                        ->whereHas('template', fn ($query) => $query
                            ->where('is_active', true)
                            ->where('contract_type', $this->derivedContractType()->value))
                        ->orderByDesc('id')
                        ->get()
                        ->mapWithKeys(fn (ContractTemplateVersion $version): array => [
                            $version->id => ($version->template?->name ?? __('Template')).' — V'.$version->version_number,
                        ])->all())
                    ->searchable()
                    ->preload()
                    ->required()
                    ->helperText(__('contract_domain.from_reservation.template_help')),
                Forms\Components\DatePicker::make('start_date')
                    ->label(__('Start date'))
                    ->default(now())
                    ->required()
                    ->live()
                    ->helperText(__('contract_domain.from_reservation.start_date_help'))
                    ->afterStateUpdated(fn ($state, Forms\Set $set) => $set('end_date', $this->suggestedEndDate($state))),
                Forms\Components\DatePicker::make('end_date')
                    ->label(__('End date'))
                    ->default(fn (): ?string => $this->suggestedEndDate(now()->toDateString()))
                    ->visible(fn (): bool => $this->derivedContractType() === ContractType::Lease)
                    ->required(fn (): bool => $this->derivedContractType() === ContractType::Lease)
                    ->helperText(__('contract_domain.from_reservation.end_date_help')),
            ])
            ->action(function (array $data): void {
                $this->runWorkflowAction(
                    operation: fn () => app(ContractDraftService::class)->createFromReservation(auth()->user(), $this->record, $data),
                    successTitle: __('contract_domain.from_reservation.done'),
                    onSuccess: fn (Contract $contract) => $this->redirect(ContractResource::getUrl('view', ['record' => $contract])),
                    rethrowValidation: true,
                );
            });
    }

    private function derivedContractType(): ContractType
    {
        return ContractType::fromOpportunityType($this->record->transaction_type);
    }

    private function suggestedEndDate(?string $start): ?string
    {
        if (! $start || ! $this->record->lease_term_value || ! $this->record->lease_term_unit) {
            return null;
        }
        $date = \Illuminate\Support\Carbon::parse($start);

        return ($this->record->lease_term_unit === 'year'
            ? $date->addYears($this->record->lease_term_value)
            : $date->addMonths($this->record->lease_term_value))->toDateString();
    }

    /** What will be created, in plain words, so the admin knows before confirming. */
    private function contractSummary(): HtmlString
    {
        $type = $this->derivedContractType();
        $company = Company::withoutGlobalScopes()->find($this->record->company_id);
        $customerParty = Party::withoutGlobalScopes()->find($this->record->customer_party_id);
        [$customerRole, $companyRole] = $type === ContractType::Lease
            ? [__('contract_domain.from_reservation.role_tenant'), __('contract_domain.from_reservation.role_landlord')]
            : [__('contract_domain.from_reservation.role_buyer'), __('contract_domain.from_reservation.role_seller')];

        $lines = [
            __('contract_domain.from_reservation.intro', ['type' => $type->getLabel()]),
            __('contract_domain.from_reservation.parties_line', [
                'customer' => $customerParty?->name ?? '—',
                'customer_role' => $customerRole,
                'company' => $company?->name ?? '—',
                'company_role' => $companyRole,
            ]),
        ];

        return new HtmlString(collect($lines)->map(fn (string $line): string => '<p>'.e($line).'</p>')->implode(''));
    }
}
