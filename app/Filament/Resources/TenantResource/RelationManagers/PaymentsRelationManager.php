<?php
// app/Filament/Resources/TenantResource/RelationManagers/PaymentsRelationManager.php

namespace App\Filament\Resources\TenantResource\RelationManagers;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class PaymentsRelationManager extends RelationManager
{
    protected static string $relationship = 'payments';
    protected static ?string $title = 'Payment History';
    protected static ?string $icon = 'heroicon-o-banknotes';

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('amount')
            ->columns([
                Tables\Columns\TextColumn::make('lease.unit.property.name')
                    ->label('Property')
                    ->searchable(),

                Tables\Columns\TextColumn::make('lease.unit.unit_number')
                    ->label('Unit')
                    ->badge()
                    ->color('info'),

                Tables\Columns\TextColumn::make('due_date')
                    ->date()
                    ->sortable(),

                Tables\Columns\TextColumn::make('amount')
                    ->money('USD')
                    ->sortable(),

                Tables\Columns\TextColumn::make('paid_amount')
                    ->label('Paid')
                    ->money('USD')
                    ->color('success'),

                Tables\Columns\TextColumn::make('remaining_amount')
                    ->label('Remaining after this payment')
                    ->money('USD')
                    ->color(fn($state) => $state > 0 ? 'danger' : 'success'),

                Tables\Columns\TextColumn::make('payment_date')
                    ->date()
                    ->placeholder('Not paid'),

                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->colors([
                        'gray' => 'pending',
                        'success' => 'paid',
                        'danger' => 'overdue',
                        'warning' => 'partial',
                    ]),

                Tables\Columns\TextColumn::make('payment_method')
                    ->badge()
                    ->toggleable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'pending' => 'Pending',
                        'paid' => 'Paid',
                        'overdue' => 'Overdue',
                        'partial' => 'Partial',
                    ]),
            ])
            ->defaultSort('due_date', 'desc');
    }

    public function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('amount')
                ->disabled()
                ->prefix('$'),
        ]);
    }
}