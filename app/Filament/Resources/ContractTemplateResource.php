<?php

namespace App\Filament\Resources;

use App\Enums\ContractType;
use App\Filament\Resources\ContractTemplateResource\Pages;
use App\Filament\Resources\ContractTemplateResource\RelationManagers;
use App\Models\{Company, ContractTemplate};
use Filament\Forms;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class ContractTemplateResource extends Resource
{
    protected static ?string $model = ContractTemplate::class;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

    protected static ?string $navigationGroup = '💼 Sales & Marketing';

    public static function getNavigationGroup(): ?string { return __('💼 Sales & Marketing'); }
    public static function getNavigationLabel(): string { return __('Contract templates'); }
    public static function getModelLabel(): string { return __('Contract template'); }
    public static function getPluralModelLabel(): string { return __('Contract templates'); }

    public static function form(Forms\Form $form): Forms\Form
    {
        return $form->schema([
            Forms\Components\Select::make('company_id')->label(__('Company'))
                ->options(Company::withoutGlobalScopes()->orderBy('name')->pluck('name', 'id'))
                ->visible(fn (): bool => auth()->user()->isSuperAdmin())
                ->required(fn (): bool => auth()->user()->isSuperAdmin()),
            Forms\Components\TextInput::make('name')->label(__('Name'))->required(),
            Forms\Components\Select::make('contract_type')->label(__('Contract type'))
                ->options(ContractType::options())
                ->required(),
            Forms\Components\Toggle::make('is_active')->label(__('Active'))->default(true),
        ]);
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist->schema([
            Infolists\Components\Section::make(__('Contract template'))
                ->schema([
                    Infolists\Components\TextEntry::make('name')->label(__('Name'))->weight('bold'),
                    Infolists\Components\TextEntry::make('contract_type')->label(__('Contract type'))->formatStateUsing(fn ($state): string => $state instanceof ContractType ? $state->getLabel() : ContractType::tryFrom((string) $state)?->getLabel() ?? (string) $state)->badge(),
                    Infolists\Components\IconEntry::make('is_active')->label(__('Active'))->boolean(),
                ])->columns(3),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            Tables\Columns\TextColumn::make('name')->label(__('Name'))->searchable(),
            Tables\Columns\TextColumn::make('contract_type')->label(__('Contract type'))->formatStateUsing(fn ($state): string => $state instanceof ContractType ? $state->getLabel() : ContractType::tryFrom((string) $state)?->getLabel() ?? (string) $state)->badge(),
            Tables\Columns\IconColumn::make('is_active')->label(__('Active'))->boolean(),
            Tables\Columns\TextColumn::make('versions_count')->counts('versions')->label(__('Versions')),
        ])->actions([
            Tables\Actions\ViewAction::make()->label(__('Details')),
            Tables\Actions\EditAction::make()->label(__('Edit')),
        ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListContractTemplates::route('/'),
            'create' => Pages\CreateContractTemplate::route('/create'),
            'view' => Pages\ViewContractTemplate::route('/{record}'),
            'edit' => Pages\EditContractTemplate::route('/{record}/edit'),
        ];
    }

    public static function getRelations(): array
    {
        return [RelationManagers\VersionsRelationManager::class];
    }
}
