<?php
// app/Filament/Resources/TenantResource/RelationManagers/LeasesRelationManager.php

namespace App\Filament\Resources\TenantResource\RelationManagers;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class LeasesRelationManager extends RelationManager
{
    protected static string $relationship = 'leases';
    protected static ?string $title = 'Leases';
    protected static ?string $icon = 'heroicon-o-document-text';

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('rent_amount')
            ->columns([
                Tables\Columns\TextColumn::make('unit.property.name')
                    ->label('Property')
                    ->searchable()
                    ->icon('heroicon-m-building-office-2'),

                Tables\Columns\TextColumn::make('unit.unit_number')
                    ->label('Unit')
                    ->badge()
                    ->color('info'),

                Tables\Columns\TextColumn::make('start_date')
                    ->date()
                    ->sortable(),

                Tables\Columns\TextColumn::make('end_date')
                    ->date()
                    ->sortable()
                    ->placeholder('Open-ended'),

                Tables\Columns\TextColumn::make('rent_amount')
                    ->money('USD')
                    ->sortable(),

                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->colors([
                        'gray' => 'draft',
                        'success' => 'active',
                        'danger' => 'expired',
                        'warning' => 'terminated',
                        'info' => 'renewed',
                    ]),

                Tables\Columns\TextColumn::make('payments_count')
                    ->counts('payments')
                    ->label('Payments')
                    ->badge()
                    ->color('info'),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'draft' => 'Draft',
                        'active' => 'Active',
                        'expired' => 'Expired',
                        'terminated' => 'Terminated',
                    ]),
            ])
            ->actions([
                Tables\Actions\ViewAction::make()
                    ->url(fn($record) => route('filament.admin.resources.leases.view', $record)),
            ])
            ->defaultSort('start_date', 'desc');
    }

    public function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('rent_amount')
                ->disabled()
                ->prefix('$'),
        ]);
    }
}