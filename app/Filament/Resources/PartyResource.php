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
                ->options([
                    'individual' => __('Individual'),
                    'company' => __('Company'),
                    'organization' => __('Organization'),
                ])
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
                ->formatStateUsing(fn ($state) => match ($state) {
                    'individual' => __('Individual'),
                    'company' => __('Company'),
                    'organization' => __('Organization'),
                    default => $state ? __($state) : __('—'),
                }),
            Tables\Columns\TextColumn::make('phone')->label(__('Phone')),
            Tables\Columns\IconColumn::make('is_active')->label(__('Active'))->boolean(),
        ])->actions([
            Tables\Actions\EditAction::make(),
            Tables\Actions\DeleteAction::make()
                ->before(function (Party $record, Tables\Actions\DeleteAction $action) {
                    if ($record->propertyOwnerships()->exists() || $record->acquisitionParties()->exists()) {
                        \Filament\Notifications\Notification::make()
                            ->warning()
                            ->title(__('Cannot delete this party'))
                            ->body(__('This party is linked to historical records (such as property ownerships or acquisitions). You must remove those links first or use a different workflow.'))
                            ->persistent()
                            ->send();
                        
                        $action->cancel();
                    }
                }),
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
}
