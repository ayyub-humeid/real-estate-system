<?php
namespace App\Filament\Resources;
use App\Models\Payment; use Filament\Resources\Resource; use Filament\Tables; use Filament\Tables\Table;
class PaymentResource extends Resource { protected static ?string $model=Payment::class; protected static bool $shouldRegisterNavigation=false; protected static ?string $navigationIcon='heroicon-o-banknotes'; public static function table(Table $table):Table{return $table->columns([Tables\Columns\TextColumn::make('project.name'),Tables\Columns\TextColumn::make('party.name'),Tables\Columns\TextColumn::make('project_amount'),Tables\Columns\TextColumn::make('status')->badge()]);} public static function getPages():array{return [];} }
