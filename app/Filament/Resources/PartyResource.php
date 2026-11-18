<?php

namespace App\Filament\Resources;

use App\Filament\Resources\PartyResource\Pages;
use App\Models\Party;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class PartyResource extends Resource
{
    use \App\Filament\Resources\Concerns\UsesLocalizedResourceLabels;
    protected static ?string $model = Party::class;
    protected static ?string $navigationIcon = 'heroicon-o-user-group';
    protected static ?string $navigationGroup = '🏠 Properties';

    private static function companyField(): Forms\Components\Component
    {
        return Forms\Components\Select::make('company_id')
            ->label(__('Company'))
            ->relationship('company', 'name')
            ->searchable()
            ->preload()
            ->required()
            ->visible(fn (): bool => auth()->user()->isSuperAdmin());
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            self::companyField(),
            Forms\Components\Select::make('type')
                ->label(__('Type'))
                ->options(static::partyTypeOptions())
                ->required(),
            Forms\Components\TextInput::make('name')->label(__('Name'))->required(),
            Forms\Components\TextInput::make('legal_name')->label(__('Legal Name')),
            Forms\Components\TextInput::make('phone')->label(__('Phone')),
            Forms\Components\TextInput::make('email')->label(__('Email'))->email(),
            Forms\Components\Textarea::make('address')->label(__('Address'))->columnSpanFull(),
            Forms\Components\Textarea::make('notes')->label(__('Notes'))->columnSpanFull(),
            Forms\Components\Toggle::make('is_active')->label(__('Active'))->default(true),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            Tables\Columns\TextColumn::make('name')->label(__('Name'))->searchable()->sortable(),
            Tables\Columns\TextColumn::make('type')->label(__('Type'))->badge()
                ->formatStateUsing(fn (?string $state) => $state ? __('property_domain.party_types.'.$state) : __('—')),
            Tables\Columns\TextColumn::make('phone')->label(__('Phone')),
            Tables\Columns\IconColumn::make('is_active')->label(__('Active'))->boolean(),
        ])->actions([
            Tables\Actions\EditAction::make(),
            Tables\Actions\DeleteAction::make(),
        ])
        ->emptyStateHeading(__('No parties yet'))
        ->emptyStateDescription(__('Create your first party to get started.'))
        ->emptyStateIcon('heroicon-o-user-group');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListParties::route('/'),
            'create' => Pages\CreateParty::route('/create'),
            'edit' => Pages\EditParty::route('/{record}/edit'),
        ];
    }

    private static function partyTypeOptions(): array
    {
        return collect(['person', 'individual', 'company', 'organization'])
            ->mapWithKeys(fn (string $type): array => [$type => __('property_domain.party_types.'.$type)])
            ->all();
    }
}
