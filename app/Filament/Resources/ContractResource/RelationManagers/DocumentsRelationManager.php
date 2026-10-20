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
                    ->weight('bold'),
                Tables\Columns\TextColumn::make('latestVersion.version_number')
                    ->label(__('Version'))
                    ->prefix('V')
                    ->badge()
                    ->color('primary'),
                Tables\Columns\TextColumn::make('latestVersion.file_name')
                    ->label(__('File name'))
                    ->searchable()
                    ->limit(35),
                Tables\Columns\TextColumn::make('latestVersion.file_size')
                    ->label(__('File size'))
                    ->formatStateUsing(fn ($state): string => $state ? round($state / 1024, 1).' KB' : '—'),
                Tables\Columns\TextColumn::make('latestVersion.uploadedBy.name')
                    ->label(__('Uploaded by'))
                    ->placeholder('—'),
                Tables\Columns\TextColumn::make('created_at')
                    ->label(__('Uploaded at'))
                    ->dateTime()
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->headerActions([
                Tables\Actions\Action::make('attachDocument')
                    ->label(__('Attach legal document'))
                    ->icon('heroicon-o-paper-clip')
                    ->visible(function (): bool {
                        $latest = $this->getOwnerRecord()->versions()->latest('version_number')->first();

                        return $latest?->status === ContractVersionStatus::Approved && auth()->user()->can('attach_contract_document');
                    })
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
}
