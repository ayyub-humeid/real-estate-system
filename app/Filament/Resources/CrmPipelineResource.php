<?php

namespace App\Filament\Resources;

use App\Filament\Resources\CrmPipelineResource\{Pages, RelationManagers};
use App\Models\{Company, CrmPipeline};
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Validation\Rules\Unique;

class CrmPipelineResource extends Resource
{
    protected static ?string $model = CrmPipeline::class;
    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';
    protected static ?string $navigationGroup = '💼 CRM';

    public static function getNavigationLabel(): string
    {
        return __('crm.navigation.pipelines');
    }

    public static function getNavigationGroup(): ?string
    {
        return '💼 ' . __('crm.navigation.crm');
    }

    public static function getModelLabel(): string
    {
        return __('crm.models.pipeline');
    }

    public static function getPluralModelLabel(): string
    {
        return __('crm.models.pipelines');
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Select::make('company_id')
                ->label(__('crm.fields.company'))
                ->options(Company::withoutGlobalScopes()->orderBy('name')->pluck('name', 'id'))
                ->searchable()
                ->required()
                ->live()
                ->visible(fn () => auth()->user()->isSuperAdmin()),
            Forms\Components\TextInput::make('name')
                ->label(__('crm.fields.name'))
                ->required()
                ->maxLength(255)
                ->live(onBlur: true)
                ->unique(
                    table: 'crm_pipelines',
                    column: 'name',
                    ignoreRecord: true,
                    modifyRuleUsing: fn (Unique $rule, Forms\Get $get) => $rule->where(
                        'company_id',
                        auth()->user()?->isSuperAdmin() ? $get('company_id') : auth()->user()?->company_id,
                    ),
                )
                ->helperText(__('crm.help.unique_pipeline_name')),
            Forms\Components\Toggle::make('is_default')->label(__('crm.fields.default')),
            Forms\Components\Toggle::make('is_active')->label(__('crm.fields.active'))->default(true),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')->label(__('crm.fields.name'))->searchable()->weight('medium')
                    ->formatStateUsing(fn ($state, CrmPipeline $record) => $record->display_name),
                Tables\Columns\IconColumn::make('is_default')->label(__('crm.fields.default'))->boolean(),
                Tables\Columns\IconColumn::make('is_active')->label(__('crm.fields.active'))->boolean(),
                Tables\Columns\TextColumn::make('stages_count')->label(__('crm.navigation.stages'))->counts('stages')->badge(),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ]);
    }

    public static function getRelations(): array
    {
        return [RelationManagers\StagesRelationManager::class];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListCrmPipelines::route('/'),
            'create' => Pages\CreateCrmPipeline::route('/create'),
            'view' => Pages\ViewCrmPipeline::route('/{record}'),
            'edit' => Pages\EditCrmPipeline::route('/{record}/edit'),
        ];
    }
}
