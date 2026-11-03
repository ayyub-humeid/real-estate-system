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
    use \App\Filament\Resources\Concerns\UsesLocalizedResourceLabels;
    protected static ?string $model = ProjectDesignPackage::class;
    protected static ?string $navigationIcon = 'heroicon-o-pencil-square';
    protected static bool $shouldRegisterNavigation = false;

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('name')->label(__('Name'))->required(),
            Forms\Components\TextInput::make('code')->label(__('Code'))->helperText(__('project.design_package_help.code')),
            Forms\Components\Select::make('discipline')->label(__('Discipline'))->options(ProjectDesignPackage::disciplineOptions())->native(false),
            Forms\Components\DatePicker::make('target_submission_date')->label(__('Target Submission Date')),
            Forms\Components\Textarea::make('description')->label(__('Description'))->columnSpanFull(),
            Forms\Components\Placeholder::make('status')->label(__('Status'))->content(fn(?ProjectDesignPackage $record) => $record ? __('project.statuses.'.$record->status) : __('Created through a Project')),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            Tables\Columns\TextColumn::make('project.name')->label(__('Project'))->searchable(),
            Tables\Columns\TextColumn::make('name')->label(__('Name'))->searchable(),
            Tables\Columns\TextColumn::make('discipline')->label(__('Discipline'))->badge()
                ->formatStateUsing(fn (?string $state): string => ProjectDesignPackage::disciplineLabel($state)),
            Tables\Columns\TextColumn::make('status')->label(__('Status'))->badge()
                ->formatStateUsing(fn (?string $state): string => $state ? __('project.statuses.'.$state) : __('—')),
            Tables\Columns\TextColumn::make('target_submission_date')->label(__('Target Submission Date'))->date(),
        ])->actions([Tables\Actions\ViewAction::make(), Tables\Actions\EditAction::make()]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListProjectDesignPackages::route('/'),
            'view' => Pages\ViewProjectDesignPackage::route('/{record}'),
            'edit' => Pages\EditProjectDesignPackage::route('/{record}/edit')
        ];
    }
}
