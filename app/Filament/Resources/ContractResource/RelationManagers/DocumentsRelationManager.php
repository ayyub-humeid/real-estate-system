<?php

namespace App\Filament\Resources\ContractResource\RelationManagers;

use App\Enums\ContractVersionStatus;
use App\Models\Document;
use App\Services\ContractWorkflowService;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class DocumentsRelationManager extends RelationManager
{
    protected static string $relationship = 'documents';

    public static function getTitle($ownerRecord, string $pageClass): string
    {
        return __('Contract documents');
    }

    public static function getModelLabel(): string
    {
        return __('Document');
    }

    public static function getPluralModelLabel(): string
    {
        return __('Contract documents');
    }

    public function isReadOnly(): bool
    {
        return false;
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('title')
                    ->label(__('Document title'))
                    ->searchable()
                    ->weight('bold')
                    ->icon('heroicon-o-document-text')
                    ->iconColor('primary')
                    ->description(fn (Document $record): string => $record->latestVersion?->file_name ?? '—')
                    ->wrap(),
                Tables\Columns\TextColumn::make('latestVersion.version_number')
                    ->label(__('Version'))
                    ->prefix('V')
                    ->badge()
                    ->color('primary'),
                Tables\Columns\TextColumn::make('latestVersion.file_size')
                    ->label(__('File size'))
                    ->formatStateUsing(fn ($state): string => $state ? number_format($state / 1024, 1).' KB' : '—')
                    ->alignEnd(),
                Tables\Columns\TextColumn::make('latestVersion.uploadedBy.name')
                    ->label(__('Uploaded by'))
                    ->placeholder('—'),
                Tables\Columns\TextColumn::make('created_at')
                    ->label(__('Uploaded at'))
                    ->dateTime()
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->emptyStateIcon('heroicon-o-document-text')
            ->emptyStateHeading(__('No legal documents attached to this contract yet.'))
            ->emptyStateDescription(fn (): string => $this->documentUploadGuidance())
            ->headerActions([
                Tables\Actions\Action::make('attachDocument')
                    ->label(__('Upload contract document'))
                    ->icon('heroicon-o-arrow-up-tray')
                    ->color('primary')
                    ->visible(function (): bool {
                        $latest = $this->getOwnerRecord()->versions()->latest('version_number')->first();

                        return $this->documentsAreEditable() && auth()->user()->can('attach_contract_document');
                    })
                    ->modalHeading(__('Upload contract document'))
                    ->modalDescription(__('Contract documents can be changed until this version is submitted for review.'))
                    ->modalSubmitActionLabel(__('Upload document'))
                    ->form([
                        Forms\Components\TextInput::make('title')->label(__('Document title'))->required(),
                        Forms\Components\Textarea::make('description')->label(__('Description')),
                        Forms\Components\FileUpload::make('file_path')
                            ->label(__('Contract file (PDF/Document)'))
                            ->required()
                            ->disk(config('filesystems.default'))
                            ->directory('documents/contracts')
                            ->storeFileNamesIn('file_name'),
                    ])
                    ->action(function (array $data): void {
                        $latest = $this->getOwnerRecord()->versions()->latest('version_number')->first();
                        try {
                            app(ContractWorkflowService::class)->attachDocument(auth()->user(), $latest, $data);
                            Notification::make()->success()->title(__('Legal document attached'))->send();
                        } catch (\Throwable $e) {
                            Notification::make()->danger()
                                ->title(__('Cannot attach document'))
                                ->body($e->getMessage())
                                ->persistent()
                                ->send();
                        }
                    }),
            ])
            ->actions([
                Tables\Actions\Action::make('download')
                    ->label(__('Download'))
                    ->icon('heroicon-o-arrow-down-tray')
                    ->color('primary')
                    ->url(fn (Document $record): ?string => $record->latestVersion ? storage_url($record->latestVersion->file_path) : null)
                    ->openUrlInNewTab()
                    ->visible(fn (Document $record): bool => $record->latestVersion !== null),
                Tables\Actions\Action::make('replace')
                    ->label(__('Replace'))
                    ->icon('heroicon-o-arrow-path')
                    ->color('warning')
                    ->visible(fn (Document $record): bool => $this->documentIsEditable($record) && auth()->user()->can('attach_contract_document'))
                    ->modalHeading(__('Replace contract document'))
                    ->modalDescription(__('The previous draft file will be removed only when it is not used anywhere else.'))
                    ->modalSubmitActionLabel(__('Replace document'))
                    ->fillForm(fn (Document $record): array => [
                        'title' => $record->title,
                        'description' => $record->description,
                    ])
                    ->form($this->documentForm())
                    ->action(function (Document $record, array $data): void {
                        try {
                            app(ContractWorkflowService::class)->replaceDocument(
                                auth()->user(),
                                $this->latestVersion(),
                                $record,
                                $data,
                            );
                            Notification::make()->success()->title(__('Contract document replaced'))->send();
                        } catch (\Throwable $exception) {
                            $this->notifyDocumentFailure($exception, __('Cannot replace contract document'));
                        }
                    }),
                Tables\Actions\Action::make('delete')
                    ->label(__('Delete'))
                    ->icon('heroicon-o-trash')
                    ->color('danger')
                    ->visible(fn (Document $record): bool => $this->documentIsEditable($record) && auth()->user()->can('attach_contract_document'))
                    ->requiresConfirmation()
                    ->modalHeading(__('Delete contract document'))
                    ->modalDescription(__('The draft file will be permanently removed only when it is not used anywhere else.'))
                    ->modalSubmitActionLabel(__('Delete document'))
                    ->action(function (Document $record): void {
                        try {
                            app(ContractWorkflowService::class)->deleteDocument(
                                auth()->user(),
                                $this->latestVersion(),
                                $record,
                            );
                            Notification::make()->success()->title(__('Contract document deleted'))->send();
                        } catch (\Throwable $exception) {
                            $this->notifyDocumentFailure($exception, __('Cannot delete contract document'));
                        }
                    }),
                Tables\Actions\ViewAction::make()
                    ->form([
                        Forms\Components\TextInput::make('title')->label(__('Document title')),
                        Forms\Components\Textarea::make('description')->label(__('Description')),
                        Forms\Components\Placeholder::make('file_name')
                            ->label(__('File name'))
                            ->content(fn (?Document $record): string => $record?->latestVersion?->file_name ?? '—'),
                        Forms\Components\Placeholder::make('file_size')
                            ->label(__('File size'))
                            ->content(fn (?Document $record): string => $record?->latestVersion?->file_size ? round($record->latestVersion->file_size / 1024, 1).' KB' : '—'),
                        Forms\Components\Placeholder::make('uploaded_by')
                            ->label(__('Uploaded by'))
                            ->content(fn (?Document $record): string => $record?->latestVersion?->uploadedBy?->name ?? '—'),
                        Forms\Components\Placeholder::make('created_at')
                            ->label(__('Upload date'))
                            ->content(fn (?Document $record): string => $record?->latestVersion?->created_at?->toDayDateTimeString() ?? '—'),
                    ]),
            ]);
    }

    private function documentForm(): array
    {
        return [
            Forms\Components\TextInput::make('title')->label(__('Document title'))->required(),
            Forms\Components\Textarea::make('description')->label(__('Description')),
            Forms\Components\FileUpload::make('file_path')
                ->label(__('Contract file (PDF/Document)'))
                ->required()
                ->disk(config('filesystems.default'))
                ->directory('documents/contracts')
                ->storeFileNamesIn('file_name'),
        ];
    }

    private function latestVersion(): \App\Models\ContractVersion
    {
        return $this->getOwnerRecord()->versions()->latest('version_number')->firstOrFail();
    }

    private function documentsAreEditable(): bool
    {
        return in_array($this->latestVersion()->status, [ContractVersionStatus::Draft, ContractVersionStatus::RevisionRequired], true);
    }

    private function documentIsEditable(Document $document): bool
    {
        return $this->documentsAreEditable()
            && $document->documentable_type === \App\Models\ContractVersion::class
            && (int) $document->documentable_id === (int) $this->latestVersion()->id;
    }

    private function notifyDocumentFailure(\Throwable $exception, string $title): void
    {
        $message = $exception instanceof \Illuminate\Validation\ValidationException
            ? collect($exception->errors())->flatten()->first()
            : $exception->getMessage();

        Notification::make()->danger()->title($title)->body($message)->persistent()->send();
    }

    private function documentUploadGuidance(): string
    {
        $latest = $this->getOwnerRecord()->versions()->latest('version_number')->first();

        if (! $latest) {
            return __('contract_domain.documents.no_version');
        }

        return match ($latest->status) {
            ContractVersionStatus::Draft, ContractVersionStatus::RevisionRequired => auth()->user()->can('attach_contract_document')
                ? __('contract_domain.documents.manage_draft')
                : __('contract_domain.documents.no_upload_permission'),
            ContractVersionStatus::Submitted => __('contract_domain.documents.wait_for_approval'),
            ContractVersionStatus::Approved => __('contract_domain.documents.approved_version'),
            ContractVersionStatus::Signed => __('contract_domain.documents.signed_version'),
            ContractVersionStatus::Superseded => __('contract_domain.documents.superseded_version'),
        };
    }
}
