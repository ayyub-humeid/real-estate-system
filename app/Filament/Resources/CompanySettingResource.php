<?php

namespace App\Filament\Resources;

use App\Filament\Resources\CompanySettingResource\Pages;
use App\Models\CompanySetting;
use App\Enums\CrmAssignmentMode;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class CompanySettingResource extends Resource
{
    use \App\Filament\Resources\Concerns\UsesLocalizedResourceLabels;
    protected static ?string $model = CompanySetting::class;
    protected static ?string $navigationIcon = 'heroicon-o-cog-6-tooth';
    protected static ?string $navigationGroup = '🏢 Core';
    protected static ?int $navigationSort = 10;
    protected static ?string $navigationLabel = 'Company Settings';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make(__('Company Branding'))
                    ->description(__('Upload company logo, contract background, authorized signature, and stamp'))
                    ->schema([
                        Forms\Components\FileUpload::make('logo')
                            ->label(__('Company Logo'))
                            ->image()
                            ->directory('company-settings/logos')
                            ->imageEditor()
                            ->imageResizeTargetWidth('800')
                            ->imageResizeTargetHeight('400')
                            ->imageResizeMode('contain')
                            ->imageResizeUpscale(false)
                            ->rules(['dimensions:max_width=4000,max_height=4000'])
                            ->maxSize(2048)
                            ->helperText(__('Displayed on draft previews and issued contract PDFs. Upload a PNG or JPG no larger than 2 MB and 4000×4000 pixels.')),

                        Forms\Components\FileUpload::make('contract_background')
                            ->label(__('Contract Background Image'))
                            ->image()
                            ->directory('company-settings/backgrounds')
                            ->imageEditor()
                            ->imageResizeTargetWidth('1440')
                            ->imageResizeTargetHeight('2035')
                            ->imageResizeMode('contain')
                            ->maxSize(5120)
                            ->helperText(__('Watermark or background image for contract documents')),

                        Forms\Components\FileUpload::make('contract_signature')
                            ->label(__('Authorized Company Signature'))
                            ->image()
                            ->directory('company-settings/signatures')
                            ->imageEditor()
                            ->imageResizeTargetWidth('800')
                            ->imageResizeTargetHeight('300')
                            ->imageResizeMode('contain')
                            ->imageResizeUpscale(false)
                            ->rules(['dimensions:max_width=3000,max_height=1500'])
                            ->maxSize(1024)
                            ->helperText(__('Used only when an authorized company signature is explicitly recorded. Upload a PNG or JPG no larger than 1 MB and 3000×1500 pixels.')),

                        Forms\Components\FileUpload::make('contract_stamp')
                            ->label(__('Company Contract Stamp'))
                            ->image()
                            ->directory('company-settings/stamps')
                            ->imageEditor()
                            ->imageResizeTargetWidth('800')
                            ->imageResizeTargetHeight('800')
                            ->imageResizeMode('contain')
                            ->imageResizeUpscale(false)
                            ->rules(['dimensions:max_width=3000,max_height=3000'])
                            ->maxSize(1024)
                            ->helperText(__('Upload a PNG or JPG no larger than 1 MB and 3000×3000 pixels.'))
                            ->visible(fn (Forms\Get $get): bool => (bool) $get('show_contract_stamp')),
                    ])
                    ->columns(3)
                    ->collapsible(),

                Forms\Components\Section::make(__('Company Information'))
                    ->description(__('Legal information for contracts and invoices'))
                    ->schema([
                        Forms\Components\Select::make('company_id')
                            ->label(__('Company'))
                            ->relationship('company', 'name')
                            ->required()
                            ->disabled(fn() => !auth()->user()->isSuperAdmin())
                            ->default(fn() => auth()->user()->company_id),

                        Forms\Components\TextInput::make('company_legal_name')
                            ->label(__('Legal Name'))
                            ->maxLength(255),

                        Forms\Components\Textarea::make('company_address')
                            ->label(__('Company Address'))
                            ->rows(2),

                        Forms\Components\TextInput::make('company_phone')
                            ->label(__('Company Phone'))
                            ->tel()
                            ->maxLength(255),

                        Forms\Components\TextInput::make('company_email')
                            ->label(__('Company Email'))
                            ->email()
                            ->maxLength(255),

                        Forms\Components\TextInput::make('tax_id')
                            ->label(__('Tax ID / VAT Number'))
                            ->maxLength(255),

                        Forms\Components\TextInput::make('registration_number')
                            ->label(__('Commercial Registration'))
                            ->maxLength(255),

                        Forms\Components\TextInput::make('website')
                            ->label(__('Website'))
                            ->url()
                            ->maxLength(255),
                    ])
                    ->columns(2)
                    ->collapsible(),

                Forms\Components\Section::make(__('Contract Document Settings'))
                    ->schema([
                        Forms\Components\Textarea::make('contract_terms')
                            ->label(__('Default Contract Terms & Conditions'))
                            ->rows(5)
                            ->helperText(__('These terms are available to reusable contract templates.')),

                        Forms\Components\Textarea::make('contract_footer_text')
                            ->label(__('Contract Footer Text'))
                            ->rows(2)
                            ->placeholder(__('This contract is legally binding...')),

                        Forms\Components\ColorPicker::make('contract_header_color')
                            ->label(__('Contract Header Color'))
                            ->default('#1e40af'),

                        Forms\Components\Toggle::make('show_contract_stamp')
                            ->label(__('Show Company Stamp/Seal'))
                            ->default(true),
                    ])
                    ->columns(2)
                    ->collapsible(),

                Forms\Components\Section::make(__('Receipt Settings'))
                    ->schema([
                        Forms\Components\Textarea::make('receipt_terms')
                            ->label(__('Receipt Terms'))
                            ->rows(3),

                        Forms\Components\ColorPicker::make('receipt_header_color')
                            ->label(__('Receipt Header Color'))
                            ->default('#059669'),
                    ])
                    ->columns(2)
                    ->collapsible(),

                Forms\Components\Section::make(__('crm.navigation.crm'))
                    ->schema([
                        Forms\Components\Select::make('crm_assignment_mode')
                            ->label(__('crm.fields.assignment_mode'))
                            ->options(CrmAssignmentMode::options())
                            ->default(CrmAssignmentMode::Manual->value)
                            ->required(),
                    ])
                    ->collapsible(),

                Forms\Components\Section::make(__('Payment Policies'))
                    ->schema([
                        Forms\Components\TextInput::make('payment_grace_period_days')
                            ->label(__('Grace Period (Days)'))
                            ->numeric()
                            ->default(5)
                            ->minValue(0)
                            ->helperText(__('Days allowed after due date before marking overdue')),

                        Forms\Components\TextInput::make('late_payment_fee_percentage')
                            ->label(__('Late Payment Fee (%)'))
                            ->numeric()
                            ->default(0)
                            ->minValue(0)
                            ->suffix('%')
                            ->helperText(__('Percentage fee for late payments')),

                        Forms\Components\Toggle::make('allow_maintenance_unit_marketing')
                            ->label(__('Allow marketing during maintenance'))
                            ->helperText(__('Published Listings for maintenance Units are blocked unless enabled.'))
                            ->default(false),
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
                    ->label(__('Company'))
                    ->searchable()
                    ->sortable(),

                Tables\Columns\ImageColumn::make('logo')
                    ->circular()
                    ->defaultImageUrl(fn($record) => 
                        'https://ui-avatars.com/api/?name=' . urlencode($record->company->name ?? 'Company')
                    ),

                Tables\Columns\IconColumn::make('contract_signature')
                    ->label(__('Signature'))
                    ->boolean()
                    ->trueIcon('heroicon-o-check-circle')
                    ->falseIcon('heroicon-o-x-circle')
                    ->trueColor('success')
                    ->falseColor('gray')
                    ->state(fn($record) => filled($record->contract_signature)),

                Tables\Columns\TextColumn::make('tax_id')
                    ->label(__('Tax ID'))
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
