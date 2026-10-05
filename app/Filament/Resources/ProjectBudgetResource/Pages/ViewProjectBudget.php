<?php
namespace App\Filament\Resources\ProjectBudgetResource\Pages;
use App\Filament\Resources\ProjectBudgetResource; use Filament\Actions; use Filament\Resources\Pages\ViewRecord;
class ViewProjectBudget extends ViewRecord { protected static string $resource=ProjectBudgetResource::class; protected function getHeaderActions():array{return [Actions\EditAction::make()];} public function getRelationManagers():array{return [\App\Filament\Resources\ProjectBudgetResource\RelationManagers\CategoriesRelationManager::class,\App\Filament\Resources\ProjectBudgetResource\RelationManagers\ItemsRelationManager::class];} }
