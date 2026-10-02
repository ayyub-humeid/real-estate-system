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
    protected static ?string $model = Party::class;
    protected static ?string $navigationIcon = 'heroicon-o-user-group';
    protected static ?string $navigationGroup = '🏠 Properties';

    private static function companyField(): Forms\Components\Component
    {
        return Forms\Components\Select::make('company_id')
            ->label('Company')
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
            Forms\Components\Select::make('type')->options(['individual' => 'Individual', 'company' => 'Company', 'organization' => 'Organization'])->required(),
            Forms\Components\TextInput::make('name')->required(),
            Forms\Components\TextInput::make('legal_name'),
            Forms\Components\TextInput::make('phone'),
            Forms\Components\TextInput::make('email')->email(),
            Forms\Components\Textarea::make('address')->columnSpanFull(),
            Forms\Components\Textarea::make('notes')->columnSpanFull(),
            Forms\Components\Toggle::make('is_active')->default(true),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            Tables\Columns\TextColumn::make('name')->searchable()->sortable(),
            Tables\Columns\TextColumn::make('type')->badge(),
            Tables\Columns\TextColumn::make('phone'),
            Tables\Columns\IconColumn::make('is_active')->boolean(),
        ])->actions([
            Tables\Actions\EditAction::make(),
            Tables\Actions\DeleteAction::make()
                ->before(function (Party $record, Tables\Actions\DeleteAction $action) {
                    if ($record->propertyOwnerships()->exists() || $record->acquisitionParties()->exists()) {
                        \Filament\Notifications\Notification::make()
                            ->warning()
                            ->title('Cannot delete this party')
                            ->body('This party is linked to historical records (such as property ownerships or acquisitions). You must remove those links first or use a different workflow.')
                            ->persistent()
                            ->send();
                        
                        $action->cancel();
                    }
                }),
        ]);
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
