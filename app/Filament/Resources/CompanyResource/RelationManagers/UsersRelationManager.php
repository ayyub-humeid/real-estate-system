<?php
// app/Filament/Resources/CompanyResource/RelationManagers/UsersRelationManager.php

namespace App\Filament\Resources\CompanyResource\RelationManagers;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Hash;

class UsersRelationManager extends RelationManager
{
    protected static string $relationship = 'users';

    protected static ?string $title = 'Company Users';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
            
                Forms\Components\TextInput::make('name')
                    ->required()
                    ->maxLength(255),
                
                Forms\Components\TextInput::make('email')
                    ->email()
                    ->required()
                    ->unique(ignoreRecord: true)
                    ->maxLength(255),
                
                Forms\Components\TextInput::make('phone')
                    ->tel()
                    ->maxLength(255),
                
                Forms\Components\Select::make('role')
                    ->required()
                    ->options([
                        'company_admin' => '⚡ Company Admin',
                        'property_manager' => '📋 Property Manager',
                        'tenant' => '👤 Tenant',
                    ])
                    ->default('tenant')
                    ->native(false),
                
                Forms\Components\TextInput::make('password')
                    ->password()
                    ->dehydrateStateUsing(fn ($state) => filled($state) ? Hash::make($state) : null)
                    ->dehydrated(fn ($state) => filled($state))
                    ->required(fn (string $context): bool => $context === 'create')
                    ->maxLength(255)
                    ->revealable()
                    ->helperText(fn (string $context) => 
                        $context === 'edit' 
                            ? 'Leave empty to keep current password' 
                            : 'Enter a strong password'
                    ),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
                
                Tables\Columns\TextColumn::make('email')
                    ->searchable()
                    ->copyable()
                    ->icon('heroicon-m-envelope'),
                
                Tables\Columns\TextColumn::make('role')
                    ->badge()
                    ->colors([
                        'warning' => 'company_admin',
                        'success' => 'property_manager',
                        'gray' => 'tenant',
                    ])
                    ->formatStateUsing(fn (string $state): string => 
                        str_replace('_', ' ', ucwords($state, '_'))
                    ),
                
                Tables\Columns\TextColumn::make('phone')
                    ->searchable()
                    ->toggleable(),
                
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->since()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('role')
                    ->options([
                        'company_admin' => 'Company Admin',
                        'property_manager' => 'Property Manager',
                        'tenant' => 'Tenant',
                    ]),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->mutateFormDataUsing(function (array $data): array {
                        // Automatically set company_id
                        $data['company_id'] = $this->getOwnerRecord()->id;
                        return $data;
                    }),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->emptyStateHeading('No users yet')
            ->emptyStateDescription('Add your first user to this company.')
            ->emptyStateIcon('heroicon-o-users');
    }
}