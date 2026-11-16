<?php
namespace App\Filament\Resources\PropertyAcquisitionResource\Pages;

use App\Filament\Resources\PropertyAcquisitionResource;
use App\Services\PropertyAcquisitionService;
use App\Models\Project;
use App\Models\ProjectProperty;
use App\Models\Property;
use Filament\Notifications\Notification;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\DB;

class ViewPropertyAcquisition extends ViewRecord
{
    protected static string $resource = PropertyAcquisitionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('startDueDiligence')
                ->label(__('Start due diligence'))
                ->visible(fn() => $this->record->status === 'draft')
                ->authorize('update')
                ->action(function() {
                    $this->performTransition('under_due_diligence', __('Due Diligence Started'));
                }),

            Actions\Action::make('approve')
                ->label(__('Approve acquisition'))
                ->visible(fn() => $this->record->status === 'under_due_diligence')
                ->authorize('approve')
                ->action(function() {
                    $this->performTransition('approved', __('Acquisition Approved'));
                }),

            Actions\Action::make('complete')
                ->label(__('Complete acquisition'))
                ->visible(fn() => $this->record->status === 'approved')
                ->authorize('complete')
                ->action(function() {
                    $this->performTransition('completed', __('Acquisition Completed'));
                }),

            Actions\Action::make('cancel')
                ->label(__('Cancel acquisition'))
                ->visible(fn() => !in_array($this->record->status, ['cancelled', 'completed']))
                ->requiresConfirmation()
                ->form([
                    \Filament\Forms\Components\Textarea::make('reason')->label(__('Cancellation Reason'))->required()
                ])
                ->authorize('cancel')
                ->action(function(array $data) {
                    $this->performTransition('cancelled', __('Acquisition Cancelled'), $data['reason']);
                }),

            // Convert Acquisition to Project - visible only when status = completed
            Actions\Action::make('convertToProject')
                ->label(__('acquisition.actions.convert_to_project'))
                ->icon('heroicon-o-plus-circle')
                ->color('success')
                ->visible(fn() => $this->record->status === 'completed')
                ->authorize('create', Project::class)
                ->requiresConfirmation()
                ->modalHeading(__('acquisition.modals.convert_heading'))
                ->modalDescription(__('acquisition.modals.convert_description'))
                ->modalSubmitActionLabel(__('acquisition.modals.convert_submit'))
                ->form([
                    \Filament\Forms\Components\TextInput::make('project_name')
                        ->label(__('acquisition.fields.project_name'))
                        ->required()
                        ->maxLength(255)
                        ->default(fn() => $this->record->reference_number . ' - Project'),
                    \Filament\Forms\Components\Select::make('project_type')
                        ->label(__('acquisition.fields.project_type'))
                        ->options([
                            'residential' => __('acquisition.project_types.residential'),
                            'commercial' => __('acquisition.project_types.commercial'),
                            'mixed_use' => __('acquisition.project_types.mixed_use'),
                            'land_development' => __('acquisition.project_types.land_development'),
                        ])
                        ->required(),
                    \Filament\Forms\Components\Toggle::make('seed_budget')
                        ->label(__('acquisition.fields.seed_budget'))
                        ->helperText(__('acquisition.fields.seed_budget_help'))
                        ->default(true),
                ])
                ->action(function(array $data) {
                    $this->convertToProject($data);
                })
        ];
    }

    private function convertToProject(array $data): void
    {
        try {
            // All persistence lives in the service so it can be tested directly
            // and so schema-required fields (version_number, budget_line_id,
            // planned_amount) are set in exactly one place.
            $project = app(PropertyAcquisitionService::class)->convertToProject(auth()->user(), $this->record, $data);

            $this->record = $this->record->fresh();

            Notification::make()
                ->success()
                ->title(__('acquisition.notifications.conversion_complete'))
                ->body(__('acquisition.notifications.conversion_body', ['name' => $project->name, 'project_id' => $project->id]))
                ->send();

            // Redirect to the new project
            redirect()->route('filament.admin.resources.projects.view', ['record' => $project->id]);
        } catch (ValidationException $exception) {
            Notification::make()
                ->danger()
                ->title(__('Cannot convert acquisition'))
                ->body(collect($exception->errors())->flatten()->first())
                ->send();
        } catch (AuthorizationException) {
            Notification::make()
                ->danger()
                ->title(__('Unauthorized'))
                ->body(__('You do not have permission for this action.'))
                ->send();
        } catch (\Exception $e) {
            Notification::make()
                ->danger()
                ->title(__('Conversion failed'))
                ->body($e->getMessage())
                ->send();
        }
    }

    private function performTransition(string $status, string $successTitle, ?string $reason = null): void
    {
        try {
            $this->record = app(PropertyAcquisitionService::class)->transition(
                auth()->user(),
                $this->record,
                $status,
                $reason,
            );

            Notification::make()
                ->success()
                ->title($successTitle)
                ->body(__('Current status: :status', ['status' => __($this->record->status)]))
                ->send();
        } catch (ValidationException $exception) {
            Notification::make()
                ->danger()
                ->title(__('Cannot change acquisition status'))
                ->body(collect($exception->errors())->flatten()->first())
                ->send();
        } catch (AuthorizationException) {
            Notification::make()
                ->danger()
                ->title(__('Unauthorized'))
                ->body(__('You do not have permission for this action.'))
                ->send();
        }
    }
}