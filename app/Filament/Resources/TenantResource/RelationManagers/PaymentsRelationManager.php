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
    protected static ?string $icon = 'heroicon-o-banknotes';

    public static function getTitle(\Illuminate\Database\Eloquent\Model $ownerRecord, string $pageClass): string
    {
        return __('Payment History');
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('amount')
            ->columns([
                Tables\Columns\TextColumn::make('lease.unit.property.name')
                    ->label(__('Property'))
                    ->searchable(),

                Tables\Columns\TextColumn::make('lease.unit.unit_number')
                    ->label(__('Unit'))
                    ->badge()
                    ->color('info'),

                Tables\Columns\TextColumn::make('due_date')
                    ->date()
                    ->sortable(),

                Tables\Columns\TextColumn::make('amount')
                    ->money('USD')
                    ->sortable(),

                Tables\Columns\TextColumn::make('paid_amount')
                    ->label(__('Paid'))
                    ->money('USD')
                    ->color('success'),

                Tables\Columns\TextColumn::make('remaining_amount')
                    ->label(__('Remaining after this payment'))
                    ->money('USD')
                    ->color(fn($state) => $state > 0 ? 'danger' : 'success'),

                Tables\Columns\TextColumn::make('payment_date')
                    ->date()
                    ->placeholder(__('Not paid')),

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
                        'pending' => __('tenant.statuses.pending'),
                        'paid' => __('Paid'),
                        'overdue' => __('Overdue'),
                        'partial' => __('Partial'),
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
