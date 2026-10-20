<?php

namespace App\Filament\Resources\ContractTemplateResource\RelationManagers;

use Filament\Forms;
use App\Models\ContractTemplateVersion;
use App\Services\ContractTemplateService;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class VersionsRelationManager extends RelationManager
{
    protected static string $relationship = 'versions';

    public static function getTitle($ownerRecord, string $pageClass): string
    {
        return __('Template versions');
    }

    public function isReadOnly(): bool
    {
        return false;
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('version_number')->label(__('Version'))->badge(),
                Tables\Columns\TextColumn::make('createdBy.name')->label(__('Created by'))->placeholder('—'),
                Tables\Columns\TextColumn::make('created_at')->label(__('Created at'))->dateTime(),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->label(__('Create template version'))
                    ->visible(fn (): bool => auth()->user()->can('update', $this->getOwnerRecord()))
                    ->form([
                        Forms\Components\Textarea::make('body')->label(__('Template body'))->columnSpanFull(),
                        Forms\Components\Repeater::make('clauses')
                            ->label(__('Template clauses'))
                            ->schema([
                                Forms\Components\TextInput::make('title')->label(__('Term title'))->required(),
                                Forms\Components\Textarea::make('text')->label(__('Term text')),
                                Forms\Components\Select::make('type')->label(__('Term type'))
                                    ->options(['clause' => __('Clause'), 'commercial' => __('Commercial term'), 'legal' => __('Legal term')])
                                    ->default('clause'),
                            ])
                            ->columns(2)
                            ->addActionLabel(__('Add clause'))
                            ->columnSpanFull(),
                    ])
                    ->mutateFormDataUsing(function (array $data): array {
                        $data['company_id'] = $this->getOwnerRecord()->company_id;
                        $data['contract_template_id'] = $this->getOwnerRecord()->id;
                        $data['created_by'] = auth()->id();

                        return $data;
                    })
                    ->using(fn (array $data): ContractTemplateVersion => app(ContractTemplateService::class)
                        ->createVersion(auth()->user(), $this->getOwnerRecord(), $data)),
            ])
            ->actions([Tables\Actions\ViewAction::make()->label(__('Details'))]);
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['company_id'] = $this->getOwnerRecord()->company_id;
        $data['contract_template_id'] = $this->getOwnerRecord()->id;

        return $data;
    }
}
