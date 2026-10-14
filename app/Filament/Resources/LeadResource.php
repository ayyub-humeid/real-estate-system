<?php

namespace App\Filament\Resources;

use App\Enums\{CrmInquirySource, LeadStatus};
use App\Filament\Resources\LeadResource\{Pages, RelationManagers};
use App\Models\{Company, Lead, User};
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class LeadResource extends Resource
{
    protected static ?string $model = Lead::class;
    protected static ?string $navigationIcon = 'heroicon-o-user-group';
    protected static ?string $navigationGroup = '💼 CRM';

    public static function getNavigationLabel(): string
    {
        return __('crm.navigation.leads');
    }

    public static function getNavigationGroup(): ?string
    {
        return '💼 ' . __('crm.navigation.crm');
    }

    public static function getModelLabel(): string
    {
        return __('crm.models.lead');
    }

    public static function getPluralModelLabel(): string
    {
        return __('crm.models.leads');
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Select::make('company_id')
                ->label(__('crm.fields.company'))
                ->options(Company::withoutGlobalScopes()->orderBy('name')->pluck('name', 'id'))
                ->searchable()
                ->required()
                ->live()
                ->visible(fn() => auth()->user()->isSuperAdmin()),
            Forms\Components\TextInput::make('name')->label(__('crm.fields.name'))->required()->maxLength(255),
            Forms\Components\TextInput::make('phone')->label(__('crm.fields.phone'))->tel()->maxLength(32),
            Forms\Components\TextInput::make('email')->label(__('crm.fields.email'))->email()->maxLength(255),
            Forms\Components\Select::make('assigned_to')
                ->label(__('crm.fields.owner'))
                ->options(fn(Forms\Get $get) => User::withoutGlobalScopes()
                    ->when(static::companyId($get), fn($query, $companyId) => $query->where('company_id', $companyId), fn($query) => $query->whereRaw('1 = 0'))
                    ->orderBy('name')
                    ->pluck('name', 'id'))
                ->searchable(),
            Forms\Components\Select::make('source')
                ->label(__('crm.fields.source'))
                ->options(CrmInquirySource::options())
                ->default(CrmInquirySource::Manual->value)
                ->required(),
            Forms\Components\Select::make('status')
                ->label(__('crm.fields.status'))
                ->options(LeadStatus::options())
                ->default(LeadStatus::Active->value)
                ->disabled(fn(?Lead $record) => $record === null),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')->label(__('crm.fields.name'))->searchable(),
                Tables\Columns\TextColumn::make('phone')->label(__('crm.fields.phone'))->searchable(),
                Tables\Columns\TextColumn::make('email')->label(__('crm.fields.email'))->searchable(),
                Tables\Columns\TextColumn::make('assignedTo.name')->label(__('crm.fields.owner'))->placeholder('—'),
                Tables\Columns\TextColumn::make('first_source')->label(__('crm.fields.source'))->badge()
                    ->formatStateUsing(fn($state) => $state instanceof CrmInquirySource ? $state->getLabel() : __('crm.sources.' . $state)),
                Tables\Columns\TextColumn::make('status')->label(__('crm.fields.status'))->badge()
                    ->formatStateUsing(fn($state) => $state instanceof LeadStatus ? $state->getLabel() : __('crm.lead_status.' . $state))
                    // Filament injects a table cell's value as `$state`. Naming this
                    // `$status` makes the container try to instantiate LeadStatus.
                    ->color(fn($state) => $state === LeadStatus::Active || $state === LeadStatus::Active->value ? 'success' : 'gray'),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\InquiriesRelationManager::class,
            RelationManagers\OpportunitiesRelationManager::class,
            RelationManagers\FollowUpTasksRelationManager::class,
            RelationManagers\ActivitiesRelationManager::class,
        ];
    }

    public static function companyId(Forms\Get $get): ?int
    {
        $companyId = auth()->user()?->isSuperAdmin() ? $get('company_id') : auth()->user()?->company_id;

        return filled($companyId) ? (int) $companyId : null;
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListLeads::route('/'),
            'create' => Pages\CreateLead::route('/create'),
            'view' => Pages\ViewLead::route('/{record}'),
            'edit' => Pages\EditLead::route('/{record}/edit'),
        ];
    }
}
