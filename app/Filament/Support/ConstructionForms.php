<?php

namespace App\Filament\Support;

use App\Models\{BudgetItem, ConstructionWorkPackage, ConstructionWorkPackageTask, Party, ProjectConstruction, User};
use Filament\Forms;

/** Form fields for the construction screens. Each action is opened on its own row, so none asks the user to pick the stage or task again. */
class ConstructionForms
{
    private static function parties(int $companyId): array
    {
        return Party::withoutGlobalScopes()->where('company_id', $companyId)->where('is_active', true)->orderBy('name')->pluck('name', 'id')->all();
    }

    public static function construction(ProjectConstruction $construction): array
    {
        return [
            Forms\Components\Select::make('manager_party_id')->label(__('project.construction.fields.manager'))
                ->options(fn (): array => self::parties((int) $construction->company_id))->searchable(),
            Forms\Components\DatePicker::make('planned_start_date')->label(__('project.construction.fields.planned_start')),
            Forms\Components\DatePicker::make('expected_completion_date')->label(__('project.construction.fields.expected')),
            Forms\Components\Textarea::make('notes')->label(__('project.construction.fields.notes')),
        ];
    }

    public static function package(ProjectConstruction $construction): array
    {
        return [
            Forms\Components\TextInput::make('name')->label(__('project.construction.fields.name'))->required()->maxLength(255),
            Forms\Components\TextInput::make('code')->label(__('project.construction.fields.code')),
            Forms\Components\Select::make('responsible_party_id')->label(__('project.construction.fields.responsible'))
                ->options(fn (): array => self::parties((int) $construction->company_id))->searchable(),
            Forms\Components\Select::make('budget_line_id')->label(__('project.construction.fields.budget_line'))
                ->helperText(__('project.construction.help.budget_line'))
                ->options(fn (): array => BudgetItem::withoutGlobalScopes()
                    ->whereHas('category.budget', fn ($query) => $query->where('project_id', $construction->project_id)->where('status', 'approved'))
                    ->with('category')->get()
                    ->mapWithKeys(fn (BudgetItem $item): array => [$item->budget_line_id => "{$item->category->name} — {$item->name}"])->all())
                ->searchable(),
            Forms\Components\DatePicker::make('planned_start_date')->label(__('project.construction.fields.planned_start')),
            Forms\Components\DatePicker::make('planned_end_date')->label(__('project.construction.fields.planned_end')),
            Forms\Components\Textarea::make('description')->label(__('project.construction.fields.description')),
            Forms\Components\Textarea::make('notes')->label(__('project.construction.fields.notes')),
        ];
    }

    public static function task(ProjectConstruction $construction): array
    {
        return [
            Forms\Components\TextInput::make('name')->label(__('project.construction.fields.name'))->required()->maxLength(255),
            Forms\Components\Select::make('assigned_party_id')->label(__('project.construction.fields.responsible'))
                ->options(fn (): array => self::parties((int) $construction->company_id))->searchable(),
            Forms\Components\Toggle::make('requires_inspection')->label(__('project.construction.fields.requires_inspection'))
                ->helperText(__('project.construction.help.requires_inspection')),
            Forms\Components\DatePicker::make('planned_start_date')->label(__('project.construction.fields.planned_start')),
            Forms\Components\DatePicker::make('planned_end_date')->label(__('project.construction.fields.planned_end')),
            Forms\Components\Textarea::make('description')->label(__('project.construction.fields.description')),
            Forms\Components\Textarea::make('notes')->label(__('project.construction.fields.notes')),
        ];
    }

    public static function progress(ConstructionWorkPackageTask $task, bool $correction = false): array
    {
        return [
            Forms\Components\TextInput::make('progress_percentage')->label(__('project.construction.fields.percentage'))
                ->helperText($correction ? null : __('project.construction.help.percentage'))
                ->numeric()->minValue(0)->maxValue(100)->default((float) $task->progress_percentage)->required(),
            Forms\Components\DatePicker::make('reported_at')->label(__('project.construction.fields.reported_at'))->default(today())->maxDate(today())->required(),
            ...($correction ? [Forms\Components\Textarea::make('correction_reason')->label(__('project.construction.fields.correction_reason'))->required()] : []),
            Forms\Components\Textarea::make('notes')->label(__('project.construction.fields.notes')),
        ];
    }

    public static function inspection(ProjectConstruction $construction): array
    {
        return [
            Forms\Components\Select::make('inspector_party_id')->label(__('project.construction.fields.inspector'))
                ->options(fn (): array => self::parties((int) $construction->company_id))->searchable(),
            Forms\Components\Select::make('result')->label(__('project.construction.fields.result'))->options(__('project.construction.results'))->required(),
            Forms\Components\DatePicker::make('inspection_date')->label(__('project.construction.fields.inspection_date'))->default(today())->maxDate(today())->required(),
            Forms\Components\Textarea::make('notes')->label(__('project.construction.fields.notes')),
        ];
    }

    public static function issue(ConstructionWorkPackage $package, ProjectConstruction $construction): array
    {
        return [
            Forms\Components\Select::make('construction_work_package_task_id')->label(__('project.construction.fields.task'))
                ->options(fn (): array => $package->tasks()->orderBy('name')->pluck('name', 'id')->all())->searchable(),
            Forms\Components\TextInput::make('title')->label(__('project.construction.fields.title'))->required()->maxLength(255),
            Forms\Components\Textarea::make('description')->label(__('project.construction.fields.description'))->required(),
            Forms\Components\Select::make('severity')->label(__('project.construction.fields.severity'))->options(__('project.construction.severities'))->required(),
            Forms\Components\Select::make('assigned_to_user_id')->label(__('project.construction.fields.owner'))
                ->options(fn (): array => User::query()->where('company_id', $construction->company_id)->orderBy('name')->pluck('name', 'id')->all())->searchable(),
            Forms\Components\DatePicker::make('opened_at')->label(__('project.construction.fields.opened_at'))->default(today())->maxDate(today())->required(),
        ];
    }

    public static function delay(ConstructionWorkPackage $package): array
    {
        return [
            Forms\Components\DatePicker::make('revised_end_date')->label(__('project.construction.fields.revised_end'))->required(),
            Forms\Components\Select::make('reason_code')->label(__('project.construction.fields.reason_code'))->options(__('project.construction.delay_reasons'))->required(),
            Forms\Components\Select::make('construction_work_package_task_id')->label(__('project.construction.fields.task'))
                ->options(fn (): array => $package->tasks()->orderBy('name')->pluck('name', 'id')->all())->searchable(),
            Forms\Components\DatePicker::make('reported_at')->label(__('project.construction.fields.reported_at'))->default(today())->maxDate(today())->required(),
            Forms\Components\Textarea::make('description')->label(__('project.construction.fields.description')),
        ];
    }
}
