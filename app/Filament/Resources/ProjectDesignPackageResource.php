<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ProjectDesignPackageResource\Pages;
use App\Models\ProjectDesignPackage;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

/**
 * A hidden resource exists so Shield owns the package's standard CRUD
 * permissions. Normal work starts from Project → Design Packages.
 */
class ProjectDesignPackageResource extends Resource
{
    protected static ?string $model = ProjectDesignPackage::class;
    protected static ?string $navigationIcon = 'heroicon-o-pencil-square';
    protected static bool $shouldRegisterNavigation = false;

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('name')->required(),
            Forms\Components\TextInput::make('code'),
            Forms\Components\Select::make('discipline')->options(ProjectDesignPackage::DISCIPLINES),
            Forms\Components\DatePicker::make('target_submission_date'),
            Forms\Components\Textarea::make('description')->columnSpanFull(),
            Forms\Components\Placeholder::make('status')->content(fn (?ProjectDesignPackage $record) => $record ? str_replace('_', ' ', $record->status) : 'Created through a Project'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            Tables\Columns\TextColumn::make('project.name')->searchable(),
            Tables\Columns\TextColumn::make('name')->searchable(),
            Tables\Columns\TextColumn::make('status')->badge(),
            Tables\Columns\TextColumn::make('target_submission_date')->date(),
        ])->actions([Tables\Actions\ViewAction::make(), Tables\Actions\EditAction::make()]);
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ListProjectDesignPackages::route('/'), 'view' => Pages\ViewProjectDesignPackage::route('/{record}'), 'edit' => Pages\EditProjectDesignPackage::route('/{record}/edit')];
    }
}
