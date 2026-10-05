<?php

namespace App\Filament\Resources;

use App\Filament\Resources\DocumentResource\Pages;
use App\Models\Document;
use App\Models\ProjectDesignPackage;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/** Documents are logical records; immutable files live in DocumentVersion. */
class DocumentResource extends Resource
{
    protected static ?string $model = Document::class;
    protected static ?string $navigationIcon = 'heroicon-o-document-text';
    protected static bool $shouldRegisterNavigation = false;

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery()
            ->where('documents.documentable_type', ProjectDesignPackage::class)
            ->with(['documentable.project', 'createdBy:id,name', 'latestVersion'])
            ->withCount('versions');

        if (!auth()->user()->isSuperAdmin()) {
            $query->whereIn('documents.documentable_id', ProjectDesignPackage::withoutGlobalScopes()
                ->where('company_id', auth()->user()->company_id)
                ->select('id'));
        }

        return $query;
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('title')->disabled(),
            Forms\Components\Textarea::make('description')->disabled()->columnSpanFull(),
            Forms\Components\Placeholder::make('package')->content(fn(?Document $record) => $record?->documentable?->name ?? '—'),
            Forms\Components\Placeholder::make('latest_version')->content(fn(?Document $record) => $record?->latestVersion ? "V{$record->latestVersion->version_number} — {$record->latestVersion->file_name}" : 'No version'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            Tables\Columns\TextColumn::make('title')->searchable()->weight('medium'),
            Tables\Columns\TextColumn::make('documentable.project.name')->label('Project')->searchable(),
            Tables\Columns\TextColumn::make('documentable.name')->label('Design package')->searchable(),
            Tables\Columns\TextColumn::make('versions_count')->label('Versions')->badge()->alignCenter(),
            Tables\Columns\TextColumn::make('latestVersion.version_number')->label('Latest')->formatStateUsing(fn($state) => $state ? "V{$state}" : '—')->badge()->color('info'),
            Tables\Columns\TextColumn::make('latestVersion.file_name')->label('Latest file')->placeholder('No version'),
            Tables\Columns\TextColumn::make('createdBy.name')->label('Created by')->toggleable(),
            Tables\Columns\TextColumn::make('created_at')->dateTime()->since()->toggleable(),
        ])->actions([
                    Tables\Actions\ViewAction::make(),
                    Tables\Actions\Action::make('openLatestVersion')->label('Open latest')->icon('heroicon-o-arrow-top-right-on-square')
                        ->url(fn(Document $record) => $record->latestVersion ? storage_url($record->latestVersion->file_path) : null)
                        ->openUrlInNewTab()->visible(fn(Document $record) => $record->latestVersion !== null),
                ])->defaultSort('created_at', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListDocuments::route('/'),
            'view' => Pages\ViewDocument::route('/{record}')
        ];
    }
}
