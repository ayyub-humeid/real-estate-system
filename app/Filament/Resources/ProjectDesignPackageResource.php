<?php

namespace App\Filament\Resources;

use App\Enums\{DesignPackageStatus, DesignVersionDecision};
use App\Filament\Resources\ProjectDesignPackageResource\Pages;
use App\Models\{DesignPackageVersion, Party, ProjectDesignPackage};
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

/**
 * Hidden from navigation: work starts from Project → Design packages. The resource
 * gives Shield its CRUD permissions and hosts the package page with its versions.
 */
class ProjectDesignPackageResource extends Resource
{
    use \App\Filament\Resources\Concerns\UsesLocalizedResourceLabels;

    protected static ?string $model = ProjectDesignPackage::class;
    protected static ?string $navigationIcon = 'heroicon-o-pencil-square';
    protected static bool $shouldRegisterNavigation = false;

    public static function getModelLabel(): string
    {
        return __('project.design.model');
    }

    public static function getPluralModelLabel(): string
    {
        return __('project.design.title');
    }

    /** The same fields wherever a package is created or edited. */
    public static function packageFields(int $companyId): array
    {
        return [
            Forms\Components\TextInput::make('name')->label(__('project.design.fields.name'))
                ->helperText(__('project.design.help.name'))->required()->maxLength(255),
            Forms\Components\Select::make('discipline')->label(__('project.design.fields.discipline'))
                ->options(ProjectDesignPackage::disciplineOptions())->native(false),
            Forms\Components\Select::make('engineering_office_id')->label(__('project.design.fields.office'))
                ->helperText(__('project.design.help.office'))
                ->options(fn (): array => Party::withoutGlobalScopes()->where('company_id', $companyId)->where('is_active', true)->orderBy('name')->pluck('name', 'id')->all())
                ->searchable(),
            Forms\Components\DatePicker::make('target_date')->label(__('project.design.fields.target_date'))
                ->helperText(__('project.design.help.target_date')),
            Forms\Components\Textarea::make('notes')->label(__('project.design.fields.notes'))->columnSpanFull(),
        ];
    }

    public static function form(Form $form): Form
    {
        return $form->schema(static::packageFields((int) ($form->getRecord()?->company_id ?? auth()->user()->company_id)));
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            Tables\Columns\TextColumn::make('project.name')->label(__('Project'))->searchable(),
            Tables\Columns\TextColumn::make('name')->label(__('project.design.fields.name'))->searchable(),
            Tables\Columns\TextColumn::make('status')->label(__('project.design.fields.status'))->badge()
                ->formatStateUsing(fn (DesignPackageStatus $state): string => $state->getLabel())
                ->color(fn (DesignPackageStatus $state): string => $state->color()),
        ])->actions([Tables\Actions\ViewAction::make()]);
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist->schema([
            Infolists\Components\Section::make()->schema([
                Infolists\Components\TextEntry::make('project.name')->label(__('Project')),
                Infolists\Components\TextEntry::make('discipline')->label(__('project.design.fields.discipline'))
                    ->formatStateUsing(fn (?string $state): string => ProjectDesignPackage::disciplineLabel($state))->placeholder('—'),
                Infolists\Components\TextEntry::make('engineeringOffice.name')->label(__('project.design.fields.office'))->placeholder(__('project.design.no_office')),
                Infolists\Components\TextEntry::make('status')->label(__('project.design.fields.status'))->badge()
                    ->formatStateUsing(fn (DesignPackageStatus $state): string => $state->getLabel())
                    ->color(fn (DesignPackageStatus $state): string => $state->color())
                    ->tooltip(fn (DesignPackageStatus $state): string => $state->description()),
                Infolists\Components\TextEntry::make('target_date')->label(__('project.design.fields.target_date'))->date()->placeholder('—'),
                Infolists\Components\TextEntry::make('notes')->label(__('project.design.fields.notes'))->placeholder('—')->columnSpanFull(),
            ])->columns(3),
            Infolists\Components\Section::make(__('project.design.history'))
                ->description(__('project.design.history_help'))
                ->schema([
                    Infolists\Components\RepeatableEntry::make('versions')->hiddenLabel()
                        ->schema([
                            Infolists\Components\TextEntry::make('version_number')->label(__('project.design.fields.version'))
                                ->formatStateUsing(fn ($state): string => 'V'.$state)->weight('bold'),
                            Infolists\Components\TextEntry::make('documentVersion.file_name')->label(__('project.design.fields.file'))
                                ->icon('heroicon-o-arrow-down-tray')->color('primary')
                                ->url(fn (DesignPackageVersion $record): ?string => storage_url($record->documentVersion?->file_path))
                                ->openUrlInNewTab(),
                            Infolists\Components\TextEntry::make('decision')->label(__('project.design.fields.decision'))->badge()
                                ->formatStateUsing(fn (DesignVersionDecision $state): string => $state->getLabel())
                                ->color(fn (DesignVersionDecision $state): string => $state->color()),
                            Infolists\Components\TextEntry::make('uploaded')->label(__('project.design.fields.uploaded'))
                                ->state(fn (DesignPackageVersion $record): string => trim(($record->uploadedBy?->name ?? '—').' · '.$record->created_at?->format('Y-m-d'))),
                            Infolists\Components\TextEntry::make('notes')->label(__('project.design.fields.version_notes'))->placeholder('—'),
                            Infolists\Components\TextEntry::make('decided')->label(__('project.design.fields.decided'))
                                ->state(fn (DesignPackageVersion $record): ?string => $record->decided_at ? trim(($record->decidedBy?->name ?? '—').' · '.$record->decided_at->format('Y-m-d')) : null)
                                ->placeholder('—'),
                            Infolists\Components\TextEntry::make('decision_comment')->label(__('project.design.fields.comment'))->placeholder('—')->columnSpanFull(),
                        ])->columns(3),
                ])
                ->visible(fn (ProjectDesignPackage $record): bool => $record->versions()->exists()),
        ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListProjectDesignPackages::route('/'),
            'view' => Pages\ViewProjectDesignPackage::route('/{record}'),
            'edit' => Pages\EditProjectDesignPackage::route('/{record}/edit'),
        ];
    }
}
