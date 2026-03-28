<?php

namespace App\Filament\Resources;

use App\Filament\Resources\CompanySettingResource\Pages;
use App\Models\CompanySetting;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class CompanySettingResource extends Resource
{
    protected static ?string $model = CompanySetting::class;
    protected static ?string $navigationIcon = 'heroicon-o-cog-6-tooth';
    protected static ?string $navigationGroup = '🏢 Core';
    protected static ?int $navigationSort = 10;
    protected static ?string $navigationLabel = 'Company Settings';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Company Branding')
                    ->description('Upload company logo, lease background, and signature')
                    ->schema([
                        Forms\Components\FileUpload::make('logo')
                            ->label('Company Logo')
                            ->image()
                            ->directory('company-settings/logos')
                            ->imageEditor()
                            ->maxSize(2048),

                        Forms\Components\FileUpload::make('lease_background')
                            ->label('Lease Background Image')
                            ->image()
                            ->directory('company-settings/backgrounds')
                            ->imageEditor()
                            ->maxSize(5120)
                            ->helperText('Watermark or background image for lease documents'),

                        Forms\Components\FileUpload::make('signature')
                            ->label('Company Signature')
                            ->image()
                            ->directory('company-settings/signatures')
                            ->imageEditor()
                            ->maxSize(1024)
                            ->helperText('Digital signature for contracts'),
                    ])
                    ->columns(3)
                    ->collapsible(),

                Forms\Components\Section::make('Company Information')
                    ->description('Legal information for contracts and invoices')
                    ->schema([
                        Forms\Components\Select::make('company_id')
                            ->relationship('company', 'name')
                            ->required()
                            ->disabled(fn() => !auth()->user()->isSuperAdmin())
                            ->default(fn() => auth()->user()->company_id),

                        Forms\Components\TextInput::make('company_legal_name')
                            ->label('Legal Name')
                            ->maxLength(255),

                        Forms\Components\Textarea::make('company_address')
                            ->rows(2),

                        Forms\Components\TextInput::make('company_phone')
                            ->tel()
                            ->maxLength(255),

                        Forms\Components\TextInput::make('company_email')
                            ->email()
                            ->maxLength(255),

                        Forms\Components\TextInput::make('tax_id')
                            ->label('Tax ID / VAT Number')
                            ->maxLength(255),

                        Forms\Components\TextInput::make('registration_number')
                            ->label('Commercial Registration')
                            ->maxLength(255),

                        Forms\Components\TextInput::make('website')
                            ->url()
                            ->maxLength(255),
                    ])
                    ->columns(2)
                    ->collapsible(),

                Forms\Components\Section::make('Lease Document Settings')
                    ->schema([
                        Forms\Components\Textarea::make('lease_terms')
                            ->label('Default Lease Terms & Conditions')
                            ->rows(5)
                            ->helperText('These terms will appear in all lease contracts'),

                        Forms\Components\Textarea::make('lease_footer_text')
                            ->label('Lease Footer Text')
                            ->rows(2)
                            ->placeholder('This contract is legally binding...'),

                        Forms\Components\ColorPicker::make('lease_header_color')
                            ->label('Lease Header Color')
                            ->default('#1e40af'),

                        Forms\Components\Toggle::make('show_company_stamp')
                            ->label('Show Company Stamp/Seal')
                            ->default(true),
                    ])
                    ->columns(2)
                    ->collapsible(),

                Forms\Components\Section::make('Receipt Settings')
                    ->schema([
                        Forms\Components\Textarea::make('receipt_terms')
                            ->rows(3),

                        Forms\Components\ColorPicker::make('receipt_header_color')
                            ->default('#059669'),
                    ])
                    ->columns(2)
                    ->collapsible(),

                Forms\Components\Section::make('Payment Policies')
                    ->schema([
                        Forms\Components\TextInput::make('payment_grace_period_days')
                            ->label('Grace Period (Days)')
                            ->numeric()
                            ->default(5)
                            ->minValue(0)
                            ->helperText('Days allowed after due date before marking overdue'),

                        Forms\Components\TextInput::make('late_payment_fee_percentage')
                            ->label('Late Payment Fee (%)')
                            ->numeric()
                            ->default(0)
                            ->minValue(0)
                            ->suffix('%')
                            ->helperText('Percentage fee for late payments'),
                    ])
                    ->columns(2)
                    ->collapsible(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('company.name')
                    ->label('Company')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\ImageColumn::make('logo')
                    ->circular()
                    ->defaultImageUrl(fn($record) => 
                        'https://ui-avatars.com/api/?name=' . urlencode($record->company->name ?? 'Company')
                    ),

                Tables\Columns\IconColumn::make('signature')
                    ->label('Signature')
                    ->boolean()
                    ->trueIcon('heroicon-o-check-circle')
                    ->falseIcon('heroicon-o-x-circle')
                    ->trueColor('success')
                    ->falseColor('gray')
                    ->state(fn($record) => filled($record->signature)),

                Tables\Columns\TextColumn::make('tax_id')
                    ->label('Tax ID')
                    ->toggleable(),

                Tables\Columns\TextColumn::make('updated_at')
                    ->dateTime()
                    ->since()
                    ->sortable(),
            ])
            ->filters([
                //
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListCompanySettings::route('/'),
            'create' => Pages\CreateCompanySetting::route('/create'),
            'edit' => Pages\EditCompanySetting::route('/{record}/edit'),
        ];
    }
}