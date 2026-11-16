<?php

namespace App\Filament\Resources\LeadResource\Pages;

use App\Filament\Resources\LeadResource;
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

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make()->label(__('crm.actions.edit')),
            Actions\Action::make('convertToParty')
                ->label(__('crm.actions.convert_to_party'))
                ->icon('heroicon-o-user-plus')
                ->color('success')
                ->visible(fn() => !$this->record->party)
                ->authorize('convert', $this->record)
                ->requiresConfirmation()
                ->modalHeading(__('crm.modals.convert_to_party_heading'))
                ->modalDescription(__('crm.modals.convert_to_party_description'))
                ->modalSubmitActionLabel(__('crm.modals.convert_to_party_submit'))
                ->action(function () {
                    return $this->convertToParty();
                }),
        ];
    }

    public function getRelationManagers(): array
    {
        return [InquiriesRelationManager::class, OpportunitiesRelationManager::class, FollowUpTasksRelationManager::class, ActivitiesRelationManager::class];
    }

    private function convertToParty(): ?\Illuminate\Http\RedirectResponse
    {
        try {
            $party = app(LeadPartyConversionService::class)->convert(auth()->user(), $this->record);

            Notification::make()
                ->success()
                ->title(__('crm.notifications.party_created'))
                ->body(__('crm.notifications.party_created_body', ['name' => $party->name]))
                ->send();

            return redirect()->route('filament.admin.resources.parties.edit', ['record' => $party->id]);
        } catch (ValidationException $exception) {
            Notification::make()
                ->danger()
                ->title(__('crm.errors.conversion_failed'))
                ->body(collect($exception->errors())->flatten()->first())
                ->send();
        } catch (AuthorizationException) {
            Notification::make()
                ->danger()
                ->title(__('Unauthorized'))
                ->body(__('You do not have permission to convert this Lead.'))
                ->send();
        } catch (\Exception $e) {
            Notification::make()
                ->danger()
                ->title(__('Conversion failed'))
                ->body($e->getMessage())
                ->send();
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
            ])->columns(3),
        ]);
    }
}
