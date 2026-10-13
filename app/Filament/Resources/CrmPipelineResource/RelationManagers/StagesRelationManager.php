<?php

namespace App\Filament\Resources\CrmPipelineResource\RelationManagers;

use App\Enums\{CrmRequirementType, CrmStageType};
use App\Models\CrmPipelineStage;
use App\Services\CrmPipelineStageService;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\Rules\Unique;
use Illuminate\Validation\ValidationException;

class StagesRelationManager extends RelationManager
{
    protected static string $relationship = 'stages';

    public function isReadOnly(): bool
    {
        // Pipeline configuration is deliberately manageable on a parent View
        // page as well as Edit. Policies and services still protect all writes.
        return false;
    }

    public static function getTitle(\Illuminate\Database\Eloquent\Model $ownerRecord, string $pageClass): string
    {
        return __('crm.navigation.stages');
    }

    public function form(Forms\Form $form): Forms\Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('code')
                ->label(__('crm.fields.code'))
                ->required()
                ->alphaDash()
                ->live(onBlur: true)
                ->unique(
                    table: 'crm_pipeline_stages',
                    column: 'code',
                    ignoreRecord: true,
                    modifyRuleUsing: fn (Unique $rule) => $rule->where('pipeline_id', $this->getOwnerRecord()->id),
                )
                ->disabled(fn (?CrmPipelineStage $record) => $record?->opportunities()->exists()),
            Forms\Components\TextInput::make('label')
                ->label(__('crm.fields.label'))
                ->required()
                ->maxLength(255),
            Forms\Components\Select::make('stage_type')
                ->label(__('crm.fields.stage_type'))
                ->options(CrmStageType::options())
                ->required()
                ->disabled(fn (?CrmPipelineStage $record) => $record?->opportunities()->exists()),
            Forms\Components\TextInput::make('position')
                ->label(__('crm.fields.position'))
                ->numeric()
                ->integer()
                ->minValue(0)
                ->required()
                ->unique(
                    table: 'crm_pipeline_stages',
                    column: 'position',
                    ignoreRecord: true,
                    modifyRuleUsing: fn (Unique $rule) => $rule->where('pipeline_id', $this->getOwnerRecord()->id),
                )
                ->helperText(__('crm.help.unique_stage_position')),
            Forms\Components\Toggle::make('is_active')
                ->label(__('crm.fields.active'))
                ->default(true),
        ]);
    }

    public function table(Tables\Table $table): Tables\Table
    {
        return $table
            ->defaultSort('position')
            ->emptyStateHeading(__('crm.empty.stages.heading'))
            ->emptyStateDescription(__('crm.empty.stages.description'))
            ->emptyStateIcon('heroicon-o-rectangle-stack')
            ->columns([
                Tables\Columns\TextColumn::make('position')->label(__('crm.fields.position'))->badge()->color('gray'),
                Tables\Columns\TextColumn::make('label')->label(__('crm.fields.label'))->searchable()->weight('medium')
                    ->formatStateUsing(fn ($state, CrmPipelineStage $record) => $record->display_label),
                Tables\Columns\TextColumn::make('code')->label(__('crm.fields.code'))->badge()->color('gray'),
                Tables\Columns\TextColumn::make('stage_type')->label(__('crm.fields.stage_type'))->badge()
                    ->formatStateUsing(fn ($state) => $state instanceof CrmStageType ? $state->getLabel() : __('crm.stage_types.' . $state)),
                Tables\Columns\IconColumn::make('is_active')->label(__('crm.fields.active'))->boolean(),
                Tables\Columns\TextColumn::make('requirements_count')->label(__('crm.navigation.requirements'))->counts('requirements')->badge(),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->label(__('crm.actions.create_stage'))
                    ->icon('heroicon-o-plus')
                    ->mutateFormDataUsing(function (array $data): array {
                        $data['company_id'] = $this->getOwnerRecord()->company_id;

                        return $data;
                    })
                    ->using(function (array $data): CrmPipelineStage {
                        return $this->run(
                            fn () => app(CrmPipelineStageService::class)->create(auth()->user(), $this->getOwnerRecord(), $data),
                            __('crm.actions.create_stage'),
                        );
                    }),
            ])
            ->actions([
                Tables\Actions\ViewAction::make()->label(__('crm.actions.details')),
                Tables\Actions\EditAction::make()
                    ->using(function (CrmPipelineStage $record, array $data): CrmPipelineStage {
                        return $this->run(
                            fn () => app(CrmPipelineStageService::class)->update(auth()->user(), $record, $data),
                            __('crm.actions.update_stage'),
                        );
                    }),
                Tables\Actions\Action::make('requirements')
                    ->label(__('crm.actions.manage_requirements'))
                    ->icon('heroicon-o-list-bullet')
                    ->tooltip(__('crm.actions.manage_requirements'))
                    ->modalHeading(__('crm.actions.manage_requirements'))
                    ->modalDescription(__('crm.help.requirements_configuration'))
                    ->modalWidth('4xl')
                    ->visible(fn (CrmPipelineStage $record): bool => auth()->user()->can('update', $record))
                    ->form([
                        Forms\Components\Repeater::make('requirements')
                            ->label(__('crm.navigation.requirements'))
                            ->addActionLabel(__('crm.actions.add_requirement'))
                            ->itemLabel(fn (array $state): ?string => $state['label'] ?? null)
                            ->collapsible()
                            ->schema([
                                Forms\Components\Hidden::make('id'),
                                Forms\Components\TextInput::make('label')
                                    ->label(__('crm.fields.label'))
                                    ->helperText(__('crm.help.requirement_label'))
                                    ->required()
                                    ->maxLength(255),
                                Forms\Components\Select::make('requirement_type')
                                    ->label(__('crm.fields.requirement_type'))
                                    ->options(CrmRequirementType::options())
                                    ->required()
                                    ->live(),
                                Forms\Components\Select::make('field')
                                    ->label(__('crm.fields.required_field'))
                                    ->options(CrmPipelineStageService::fieldOptions())
                                    ->searchable()
                                    ->visible(fn (Forms\Get $get): bool => in_array($get('requirement_type'), [
                                        CrmRequirementType::FieldRequired->value,
                                        CrmRequirementType::FieldEquals->value,
                                    ], true))
                                    ->required(fn (Forms\Get $get): bool => in_array($get('requirement_type'), [
                                        CrmRequirementType::FieldRequired->value,
                                        CrmRequirementType::FieldEquals->value,
                                    ], true)),
                                Forms\Components\TextInput::make('expected_value')
                                    ->label(__('crm.fields.expected_value'))
                                    ->visible(fn (Forms\Get $get): bool => $get('requirement_type') === CrmRequirementType::FieldEquals->value)
                                    ->required(fn (Forms\Get $get): bool => $get('requirement_type') === CrmRequirementType::FieldEquals->value),
                                Forms\Components\Select::make('related_record')
                                    ->label(__('crm.fields.required_record'))
                                    ->options(CrmPipelineStageService::relatedRecordOptions())
                                    ->visible(fn (Forms\Get $get): bool => $get('requirement_type') === CrmRequirementType::RelatedRecordExists->value)
                                    ->required(fn (Forms\Get $get): bool => $get('requirement_type') === CrmRequirementType::RelatedRecordExists->value),
                                Forms\Components\Placeholder::make('checklist_explanation')
                                    ->label(__('crm.fields.how_it_works'))
                                    ->content(__('crm.help.checklist_requirement'))
                                    ->visible(fn (Forms\Get $get): bool => $get('requirement_type') === CrmRequirementType::ChecklistCompleted->value)
                                    ->columnSpanFull(),
                                Forms\Components\Toggle::make('is_required')
                                    ->label(__('crm.fields.mandatory'))
                                    ->default(true),
                                Forms\Components\Toggle::make('is_active')
                                    ->label(__('crm.fields.active'))
                                    ->default(true),
                            ])
                            ->defaultItems(0)
                            ->columns(2),
                    ])
                    ->fillForm(fn (CrmPipelineStage $record) => [
                        'requirements' => $record->requirements()->get()->map(
                            fn ($requirement) => [
                                'id' => $requirement->id,
                                'label' => $requirement->label,
                                'requirement_type' => $requirement->requirement_type?->value,
                                'field' => $requirement->config_json['field'] ?? null,
                                'expected_value' => $requirement->config_json['value'] ?? null,
                                'related_record' => $this->relatedRecordKey($requirement->config_json ?? []),
                                'is_required' => $requirement->is_required,
                                'is_active' => $requirement->is_active,
                            ],
                        )->all(),
                    ])
                    ->action(function (CrmPipelineStage $record, array $data): void {
                        $this->run(
                            fn () => app(CrmPipelineStageService::class)->syncRequirements(auth()->user(), $record, $data['requirements'] ?? []),
                            __('crm.actions.manage_requirements'),
                        );
                    }),
                Tables\Actions\DeleteAction::make()
                    ->visible(fn (CrmPipelineStage $record) => ! $record->opportunities()->exists()),
            ]);
    }

    private function run(callable $operation, string $action): mixed
    {
        try {
            return $operation();
        } catch (ValidationException $exception) {
            Notification::make()
                ->danger()
                ->title($action)
                ->body(collect($exception->errors())->flatten()->first())
                ->send();

            throw $exception;
        } catch (AuthorizationException $exception) {
            Notification::make()->danger()->title(__('crm.errors.unauthorized'))->send();

            throw $exception;
        }
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        // HasCompany deliberately does not inject company_id for Super Admin.
        $data['company_id'] = $this->getOwnerRecord()->company_id;

        return $data;
    }

    private function relatedRecordKey(array $config): ?string
    {
        return ($config['relation'] ?? null) === 'inquiries'
            && ($config['field'] ?? null) === 'contact_result'
            && ($config['operator'] ?? null) === 'not_empty'
            ? 'inquiry_contact_result'
            : null;
    }
}
