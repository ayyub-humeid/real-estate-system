<?php

namespace App\Filament\Resources\ProjectDesignPackageResource\Pages;

use App\Enums\{DesignPackageStatus, DesignVersionDecision};
use App\Filament\Resources\ProjectDesignPackageResource;
use App\Filament\Support\RunsWorkflowAction;
use App\Models\DesignPackageVersion;
use App\Services\DesignPackageService;
use Filament\Actions;
use Filament\Forms;
use Filament\Resources\Pages\ViewRecord;

/** The whole design workflow on one page: upload a version, then approve or reject it. */
class ViewProjectDesignPackage extends ViewRecord
{
    use RunsWorkflowAction;

    protected static string $resource = ProjectDesignPackageResource::class;

    public function getTitle(): string
    {
        return $this->record->name;
    }

    public function getSubheading(): ?string
    {
        $latest = $this->pendingVersion() ?? $this->record->latestVersion;

        return __('project.design.next.'.$this->record->status->value, ['version' => $latest ? 'V'.$latest->version_number : '']);
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('uploadVersion')
                ->label(__('project.design.actions.upload'))
                ->icon('heroicon-o-arrow-up-tray')
                ->color('primary')
                ->modalHeading(__('project.design.actions.upload'))
                ->modalDescription(__('project.design.actions.upload_description'))
                ->modalSubmitActionLabel(__('project.design.actions.upload_submit'))
                ->visible(fn (): bool => auth()->user()->can('uploadVersion', $this->record))
                ->form([
                    Forms\Components\FileUpload::make('file_path')
                        ->label(__('project.design.fields.file'))
                        ->helperText(__('project.design.help.file'))
                        ->required()
                        ->disk(config('filesystems.default'))
                        ->directory('documents/design')
                        ->storeFileNamesIn('file_name'),
                    Forms\Components\Textarea::make('notes')->label(__('project.design.fields.version_notes'))
                        ->helperText(__('project.design.help.version_notes')),
                ])
                ->action(fn (array $data) => $this->runWorkflowAction(
                    operation: fn () => app(DesignPackageService::class)->uploadVersion(auth()->user(), $this->record, $data),
                    successTitle: fn (DesignPackageVersion $version): string => __('project.design.done.uploaded', ['version' => 'V'.$version->version_number]),
                    onSuccess: fn () => $this->refreshRecord(),
                    rethrowValidation: true,
                )),
            Actions\Action::make('approveVersion')
                ->label(fn (): string => __('project.design.actions.approve', ['version' => 'V'.$this->pendingVersion()?->version_number]))
                ->icon('heroicon-o-check-circle')
                ->color('success')
                ->modalHeading(fn (): string => __('project.design.actions.approve', ['version' => 'V'.$this->pendingVersion()?->version_number]))
                ->modalDescription(__('project.design.actions.approve_description'))
                ->visible(fn (): bool => $this->pendingVersion() !== null && auth()->user()->can('decideVersion', $this->record))
                ->form([Forms\Components\Textarea::make('comment')->label(__('project.design.fields.comment_optional'))])
                ->action(fn (array $data) => $this->runWorkflowAction(
                    operation: fn () => app(DesignPackageService::class)->approve(auth()->user(), $this->pendingVersion(), $data['comment'] ?? null),
                    successTitle: __('project.design.done.approved'),
                    onSuccess: fn () => $this->refreshRecord(),
                    rethrowValidation: true,
                )),
            Actions\Action::make('rejectVersion')
                ->label(fn (): string => __('project.design.actions.reject', ['version' => 'V'.$this->pendingVersion()?->version_number]))
                ->icon('heroicon-o-x-circle')
                ->color('danger')
                ->modalHeading(fn (): string => __('project.design.actions.reject', ['version' => 'V'.$this->pendingVersion()?->version_number]))
                ->modalDescription(__('project.design.actions.reject_description'))
                ->visible(fn (): bool => $this->pendingVersion() !== null && auth()->user()->can('decideVersion', $this->record))
                ->form([Forms\Components\Textarea::make('comment')->label(__('project.design.fields.comment'))
                    ->helperText(__('project.design.help.reject_comment'))->required()])
                ->action(fn (array $data) => $this->runWorkflowAction(
                    operation: fn () => app(DesignPackageService::class)->reject(auth()->user(), $this->pendingVersion(), $data['comment'] ?? null),
                    successTitle: __('project.design.done.rejected'),
                    onSuccess: fn () => $this->refreshRecord(),
                    rethrowValidation: true,
                )),
            Actions\EditAction::make()->label(__('project.design.actions.edit')),
        ];
    }

    /** The version waiting for a decision, if any. */
    private function pendingVersion(): ?DesignPackageVersion
    {
        $latest = $this->record->latestVersion;

        return $latest?->decision === DesignVersionDecision::Pending ? $latest : null;
    }

    private function refreshRecord(): void
    {
        $this->record->refresh();
        $this->record->unsetRelation('latestVersion');
        $this->record->unsetRelation('versions');
    }
}
