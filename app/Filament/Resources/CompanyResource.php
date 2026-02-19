<?php

namespace App\Filament\Resources;

use App\Filament\Resources\CompanyResource\Pages;
use App\Filament\Resources\CompanyResource\RelationManagers;
use App\Models\Company;
use Filament\Forms;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Facades\Storage;

class CompanyResource extends Resource
{
    protected static ?string $model = Company::class;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';
    protected static ?string $navigationGroup = '🏢 Core';
    protected static ?int $navigationSort = 1;
    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->withCount([
                'users',
                'users as active_users_count' => fn($query) => $query->whereIn('role', ['company_admin', 'property_manager']),
                'users as tenants_count' => fn($query) => $query->where('role', 'tenant')
            ]);
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make('Company Information')
                    ->description('Basic company details and contact information')
                    ->schema([
                        TextInput::make('name')
                            ->required()
                            ->maxLength(255)
                            ->columnSpan(2),

                        TextInput::make('email')
                            ->email()
                            ->required()
                            ->unique(ignoreRecord: true)
                            ->maxLength(255)
                            ->live(debounce: 500)
                            ->afterStateUpdated(
                                fn($state, Forms\Set $set) =>
                                $set('email', strtolower($state))
                            )
                            ->suffixIcon('heroicon-m-envelope'),

                        TextInput::make('phone')
                            ->tel()
                            ->maxLength(255)
                            ->prefixIcon('heroicon-m-phone'),

                        Textarea::make('address')
                            ->rows(3)
                            ->columnSpanFull(),

                        FileUpload::make('logo')
                            ->image()
                            ->directory('company-logos')
                            ->imageEditor()
                            ->maxSize(2048)
                            ->maxFiles(1)
                            ->columnSpanFull(),

                        Placeholder::make('logo_preview')
                            ->label('Logo Preview')
                            ->content(function (Forms\Get $get) {
                                $logo = $get('logo');
                                if (!$logo) return '';
                                if (is_array($logo)) {
                                    $logo = $logo[0] ?? null;
                                    if (!$logo) return '';
                                }

                                $url = is_string($logo)
                                    ? Storage::url($logo)
                                    : $logo->temporaryUrl();

                                return new \Illuminate\Support\HtmlString(
                                    "<img src='{$url}' class='h-32 w-32 object-cover rounded-lg border' />"
                                );
                            })
                            ->visible(fn(Forms\Get $get) => filled($get('logo')))
                            ->columnSpanFull(),

                        Toggle::make('is_active')
                            ->label('Active Status')
                            ->default(true)
                            ->helperText(
                                fn($state) =>
                                !$state
                                    ? '⚠️ Users from this company won\'t be able to login'
                                    : '✅ Company is active'
                            ),
                    ])
                    ->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
               Tables\Columns\ImageColumn::make('logo')
    ->circular()
    ->disk('public') 
    ->defaultImageUrl(
        fn($record) =>
        'https://ui-avatars.com/api/?name=' . urlencode($record->name) . '&color=7F9CF5&background=EBF4FF'
    ),

                Tables\Columns\TextColumn::make('name')
                    ->searchable()
                    ->sortable()
                    ->weight('bold')
                    ->icon('heroicon-m-building-office'),

                Tables\Columns\TextColumn::make('email')
                    ->searchable()
                    ->icon('heroicon-m-envelope')
                    ->copyable()
                    ->tooltip('Click to copy'),

                Tables\Columns\TextColumn::make('phone')
                    ->searchable()
                    ->icon('heroicon-m-phone')
                    ->toggleable(),

                Tables\Columns\IconColumn::make('is_active')
                    ->boolean()
                    ->label('Status')
                    ->sortable()
                    ->trueIcon('heroicon-o-check-circle')
                    ->falseIcon('heroicon-o-x-circle')
                    ->trueColor('success')
                    ->falseColor('danger'),

                Tables\Columns\TextColumn::make('users_count')
                    ->counts('users')
                    ->label('Users')
                    ->badge()
                    ->color('info')
                    ->url(
                        fn($record): string =>
                        $record->users_count > 0
                            ? route('filament.admin.resources.users.index', ['tableFilters[company][value]' => $record->id])
                            : '#'
                    )
                    ->tooltip(
                        fn($record): string =>
                        $record->users_count > 0
                            ? 'Click to view users'
                            : 'No users'
                    ),

                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->since()
                    ->toggleable(),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('is_active')
                    ->label('Status')
                    ->placeholder('All companies')
                    ->trueLabel('Active only')
                    ->falseLabel('Inactive only')
                    ->native(false),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->emptyStateHeading('No companies yet')
            ->emptyStateDescription('Create your first company to get started.')
            ->emptyStateIcon('heroicon-o-building-office')
            ->poll('30s');
    }

//   public static function getRelations(): array
// {
//     return [
//         RelationManagers\UsersRelationManager::class,
//     ];
// }
public static function getRelations(): array
{
    return [
        RelationManagers\UsersRelationManager::make([
            'lazy' => true,
        ]),
    ];
}

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListCompanies::route('/'),
            'create' => Pages\CreateCompany::route('/create'),
            'edit' => Pages\EditCompany::route('/{record}/edit'),
            'view' => Pages\ViewCompany::route('/{record}'),

        ];
    }
}
