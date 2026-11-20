<?php

namespace App\Filament\Resources\LeadResource\Pages;

use App\Filament\Resources\{LeadResource, PartyResource};
use App\Filament\Resources\LeadResource\RelationManagers\{ActivitiesRelationManager, FollowUpTasksRelationManager, InquiriesRelationManager, OpportunitiesRelationManager};
use App\Services\LeadPartyConversionService;
use Filament\Actions;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;

class ViewLead extends ViewRecord
{
    protected static string $resource = LeadResource::class;

    public function getSubheading(): ?string
    {
        if ($this->record->party) {
            return __('sales.lead_guidance.registered', ['name' => $this->record->party->name]);
        }

        return __('sales.lead_guidance.not_registered');
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make()->label(__('crm.actions.edit')),
            Actions\Action::make('convertToParty')
                ->label(__('crm.actions.convert_to_party'))
                ->icon('heroicon-o-user-plus')
                ->color('success')
                ->tooltip(__('sales.party.convert_help'))
                ->visible(fn() => !$this->record->party)
                ->authorize('convert', $this->record)
                ->requiresConfirmation()
                ->modalIcon('heroicon-o-user-plus')
                ->modalIconColor('success')
                ->modalHeading(__('crm.modals.convert_to_party_heading'))
                ->modalDescription(__('sales.party.convert_description'))
                ->modalSubmitActionLabel(__('crm.modals.convert_to_party_submit'))
                ->action(fn () => $this->convertToParty()),
        ];
    }

    public function getRelationManagers(): array
    {
        return [InquiriesRelationManager::class, OpportunitiesRelationManager::class, FollowUpTasksRelationManager::class, ActivitiesRelationManager::class];
    }

    /**
     * Links the lead to an existing client with the same phone/email, or creates one.
     * The user stays on the lead (the deal continues here) and is told which of the two happened.
     */
    private function convertToParty(): void
    {
        try {
            $party = app(LeadPartyConversionService::class)->convert(auth()->user(), $this->record);
            $this->record->refresh()->unsetRelation('party');

            Notification::make()
                ->success()
                ->title($party->wasRecentlyCreated ? __('crm.notifications.party_created') : __('sales.party.linked_title'))
                ->body($party->wasRecentlyCreated
                    ? __('crm.notifications.party_created_body', ['name' => $party->name])
                    : __('sales.party.linked_body', ['name' => $party->name]))
                ->send();
        } catch (ValidationException $exception) {
            Notification::make()->danger()
                ->title(__('crm.errors.conversion_failed'))
                ->body(collect($exception->errors())->flatten()->first())
                ->persistent()->send();
        } catch (AuthorizationException) {
            Notification::make()->danger()
                ->title(__('crm.errors.unauthorized'))
                ->body(__('sales.party.unauthorized_body'))
                ->persistent()->send();
        } catch (\Throwable $exception) {
            report($exception);
            Notification::make()->danger()
                ->title(__('crm.errors.conversion_failed'))
                ->body($exception->getMessage())
                ->persistent()->send();
        }
    }

    public function infolist(Infolist $infolist): Infolist
    {
        return $infolist->schema([
            Infolists\Components\Section::make()->schema([
                Infolists\Components\TextEntry::make('name')->label(__('crm.fields.name'))->weight('bold'),
                Infolists\Components\TextEntry::make('phone')->label(__('crm.fields.phone'))->placeholder(__('—')),
                Infolists\Components\TextEntry::make('email')->label(__('crm.fields.email'))->placeholder(__('—')),
                Infolists\Components\TextEntry::make('assignedTo.name')->label(__('crm.fields.owner'))->placeholder(__('crm.kanban.unassigned')),
                Infolists\Components\TextEntry::make('first_source')->label(__('crm.fields.source'))->badge()
                    ->formatStateUsing(fn ($state) => $state instanceof \App\Enums\CrmInquirySource ? $state->getLabel() : __('crm.sources.' . $state)),
                Infolists\Components\TextEntry::make('status')->label(__('crm.fields.status'))->badge()
                    ->formatStateUsing(fn ($state) => $state instanceof \App\Enums\LeadStatus ? $state->getLabel() : __('crm.lead_status.' . $state)),
                Infolists\Components\TextEntry::make('party.name')->label(__('sales.party.client'))
                    ->helperText(__('sales.party.client_help'))
                    ->placeholder(__('sales.party.not_a_client_yet'))
                    ->url(fn ($record): ?string => $record->party && auth()->user()->can('update', $record->party) ? PartyResource::getUrl('edit', ['record' => $record->party]) : null),
            ])->columns(3),
        ]);
    }
}
