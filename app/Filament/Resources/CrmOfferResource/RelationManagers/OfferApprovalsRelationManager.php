<?php

namespace App\Filament\Resources\CrmOfferResource\RelationManagers;

use App\Enums\OfferApprovalStatus;
use App\Models\CrmOfferApproval;
use App\Services\OfferNegotiationService;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;

class OfferApprovalsRelationManager extends RelationManager
{
    protected static string $relationship = 'approvals';
    public static function getTitle(\Illuminate\Database\Eloquent\Model $ownerRecord, string $pageClass): string
    {
        return __('crm.offers.navigation_approvals');
    }
    public function isReadOnly(): bool
    {
        return false;
    }
    public function form(Forms\Form $form): Forms\Form
    {
        return $form->schema([]);
    }
    public function table(Tables\Table $table): Tables\Table
    {
        return $table->columns([
            Tables\Columns\TextColumn::make('revision.revision_number')
                ->label(__('crm.offers.fields.revision'))
                ->formatStateUsing(fn($state) => __('crm.offers.revision_number', ['number' => $state])),
            Tables\Columns\TextColumn::make('requestedBy.name')
                ->label(__('crm.offers.fields.requested_by'))
                ->placeholder(__('—')),
            Tables\Columns\TextColumn::make('assignedApprover.name')
                ->label(__('crm.offers.fields.assigned_approver'))
                ->placeholder(__('—')),
            Tables\Columns\TextColumn::make('status')
                ->label(__('crm.fields.status'))->badge()
                ->formatStateUsing(fn($state) => $state instanceof OfferApprovalStatus ? $state->getLabel() : __('crm.offers.approval_statuses.' . $state))
                ->color(fn($state) => $state instanceof OfferApprovalStatus ? $state->color() : OfferApprovalStatus::tryFrom((string) $state)?->color()),
            Tables\Columns\TextColumn::make('decidedBy.name')
                ->label(__('crm.offers.fields.decided_by'))
                ->placeholder(__('—')),
            Tables\Columns\TextColumn::make('decided_at')->label(__('crm.offers.fields.decided_at'))->dateTime()->placeholder(__('—')),
        ])
            ->emptyStateHeading(__('crm.offer_empty.approvals_heading'))
            ->emptyStateDescription(__('crm.offer_empty.approvals_description'))
            ->emptyStateIcon('heroicon-o-check-badge')
            ->actions([
                    Tables\Actions\Action::make('approve')->label(__('crm.offers.actions.approve'))->modalHeading(__('crm.offer_modals.approve'))->color('success')->requiresConfirmation()->visible(fn(CrmOfferApproval $record) => $record->status === OfferApprovalStatus::Pending && auth()->user()->can('approveRevision', $record->revision))->action(fn(CrmOfferApproval $record) => $this->decide($record, true, [])),
                    Tables\Actions\Action::make('reject')->label(__('crm.offers.actions.reject'))->modalHeading(__('crm.offer_modals.reject'))->color('danger')->form([Forms\Components\Textarea::make('decision_notes')->label(__('crm.offers.fields.decision_notes'))])->visible(fn(CrmOfferApproval $record) => $record->status === OfferApprovalStatus::Pending && auth()->user()->can('rejectRevision', $record->revision))->action(fn(CrmOfferApproval $record, array $data) => $this->decide($record, false, $data)),
                ]);
    }
    private function decide(CrmOfferApproval $record, bool $approved, array $data): void
    {
        try {
            app(OfferNegotiationService::class)->decideApproval(auth()->user(), $record, $approved, $data['decision_notes'] ?? null);
            Notification::make()->success()->title($approved ? __('crm.offers.actions.approve') : __('crm.offers.actions.reject'))->send();
        } catch (ValidationException $exception) {
            Notification::make()->danger()->title(__('crm.offers.errors.action_failed'))
                ->body(collect($exception->errors())->flatten()->first())->send();
            throw $exception;
        } catch (AuthorizationException) {
            Notification::make()->danger()->title(__('crm.errors.unauthorized'))->send();
        }
    }
}
