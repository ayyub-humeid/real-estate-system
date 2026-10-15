<?php

namespace App\Filament\Resources;

use App\Filament\Resources\RoleResource\Pages;
use App\Models\Company;
use App\Models\Role;
use BezhanSalleh\FilamentShield\Facades\FilamentShield;
use BezhanSalleh\FilamentShield\Resources\RoleResource as ShieldRoleResource;
use BezhanSalleh\FilamentShield\Support\Utils;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Components\Component;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\HtmlString;
use Illuminate\Validation\Rules\Unique;

class RoleResource extends ShieldRoleResource
{
    /** Resources administered by the SaaS platform, never delegated through a Company role. */
    private const PLATFORM_RESOURCE_CLASSES = [
        CompanyResource::class,
        UserResource::class,
        PlanResource::class,
        SubscriptionResource::class,
        self::class,
    ];

    /** Company Settings remains deliberately available because it is tenant-owned. */
    public static function getResourceEntitiesSchema(): ?array
    {
        return collect(FilamentShield::getResources())
            ->reject(fn (array $entity) => ! auth()->user()?->isSuperAdmin()
                && in_array($entity['fqcn'] ?? null, self::PLATFORM_RESOURCE_CLASSES, true))
            ->sortKeys()
            ->map(function (array $entity) {
                $label = static::shield()->hasLocalizedPermissionLabels()
                    ? FilamentShield::getLocalizedResourceLabel($entity['fqcn'])
                    : $entity['model'];

                return Forms\Components\Section::make((string) $label)
                    ->description(fn () => new HtmlString('<span style="word-break: break-word;">'.Utils::showModelPath($entity['fqcn']).'</span>'))
                    ->compact()
                    ->schema([static::getCheckBoxListComponentForResource($entity)])
                    ->columnSpan(static::shield()->getSectionColumnSpan())
                    ->collapsible();
            })
            ->toArray();
    }

    /**
     * Presentation-only taxonomy for workflow and relation-manager permissions.
     * The exact permission names stay unchanged and are still what Policies/services authorize.
     */
    private const CUSTOM_PERMISSION_GROUPS = [
        'Property Acquisition' => [
            'Acquisition workflow' => ['exact:approve_property_acquisition', 'exact:cancel_property_acquisition', 'exact:complete_property_acquisition'],
            'Acquisition properties' => ['acquisition_property'],
            'Acquisition parties' => ['acquisition_party'],
            'Property ownership' => ['property_ownership', 'exact:change_property_ownership'],
            'Due diligence cases' => ['due_diligence_case'],
            'Due diligence items' => ['due_diligence_item'],
        ],
        'Project Planning' => [
            'Project workflow' => ['exact:approve_project', 'exact:cancel_project', 'exact:close_project'],
            'Project properties' => ['project_property'],
            'Project members' => ['project_member', 'exact:manage_project_members'],
            'Building floors' => ['project_building_floor'],
            'Buildings' => ['project_building'],
            'Planned unit specifications' => ['planned_unit_specification'],
            'Planned units' => ['planned_unit'],
        ],
        'Design & Engineering' => [
            'Package workflow' => ['exact:assign_design_package', 'exact:replace_design_assignment', 'exact:submit_design_package', 'exact:review_design_submission', 'exact:request_design_revision', 'exact:submit_design_revision', 'exact:approve_design_package', 'exact:close_design_package'],
            'Design findings' => ['exact:create_design_finding', 'exact:waive_design_finding'],
            'Document versions' => ['document_version'],
            'Assignments' => ['design_package_assignment'],
            'Scope items' => ['design_package_scope_item'],
            'Activities' => ['design_package_activity'],
            'Submission documents' => ['design_package_submission_document'],
            'Submissions' => ['design_package_submission'],
            'Reviews' => ['design_package_review'],
            'Review findings' => ['design_review_finding'],
            'Revisions' => ['design_package_revision'],
            'Revision findings' => ['design_revision_finding'],
            'Approvals' => ['design_package_approval'],
        ],
        'Budgeting & Financial Control' => [
            'Budget categories' => ['budget_category'],
            'Budget items' => ['budget_item'],
            'Budget workflow' => ['exact:submit_project_budget', 'exact:approve_project_budget', 'exact:reject_project_budget', 'exact:create_budget_revision', 'exact:cancel_project_budget'],
            'Commitment amendments' => ['financial_commitment_amendment'],
            'Commitment workflow' => ['exact:create_unbudgeted_commitment', 'exact:commit_financial_commitment', 'exact:approve_over_budget_commitment', 'exact:release_financial_commitment', 'exact:cancel_financial_commitment'],
            'Actual costs' => ['actual_cost'],
            'Payment allocations' => ['payment_allocation'],
            'Payment workflow' => ['exact:record_payment', 'exact:allocate_payment', 'exact:void_payment'],
        ],
        'Construction' => [
            'Construction lifecycle' => ['exact:start_project_construction', 'exact:complete_project_construction', 'exact:cancel_project_construction'],
            'Work package tasks' => ['construction_work_package_task'],
            'Work packages' => ['construction_work_package'],
            'Progress updates' => ['construction_progress_update'],
            'Inspections' => ['construction_inspection'],
            'Issues' => ['construction_issue'],
            'Delays' => ['construction_delay'],
        ],
        'Unit Setup' => [
            'Create from approved plan' => ['exact:create_actual_unit_from_planned_unit'],
            'Unit status' => ['exact:change_unit_status', 'exact:reactivate_unit'],
            'Unit ownership' => ['unit_ownership', 'exact:change_unit_ownership'],
            'Unit history and documents' => ['unit_status_history', 'exact:manage_unit_documents'],
        ],
        'CRM' => [
            'Lead workflow' => ['exact:assign_lead'],
            'Opportunity workflow' => ['exact:assign_opportunity', 'exact:move_opportunity_stage', 'exact:close_won_opportunity', 'exact:close_lost_opportunity'],
            'Lead inquiries' => ['lead_inquiry'],
            'Pipeline stages' => ['crm_pipeline_stage'],
            'Stage requirements' => ['crm_stage_requirement'],
            'Requirement completions' => ['opportunity_requirement_completion'],
            'Stage history' => ['opportunity_stage_history'],
            'Follow-up tasks' => [
                'exact:create_crm_follow_up_task',
                'exact:update_crm_follow_up_task',
                'exact:view_own_crm_follow_up_tasks',
                'exact:view_team_crm_follow_up_tasks',
            ],
            'Task assignment and completion' => [
                'exact:assign_crm_follow_up_task',
                'exact:complete_crm_follow_up_task',
                'exact:complete_any_crm_follow_up_task',
                'exact:cancel_crm_follow_up_task',
            ],
            'Activities' => ['exact:create_crm_activity', 'exact:view_crm_activity'],
            'Offers and negotiations' => ['exact:view_own_crm_offer', 'exact:manage_own_crm_offer', 'offer_revision', 'offer_approval', 'exact:finalize_offer_revision', 'exact:request_offer_approval', 'exact:approve_offer_revision', 'exact:reject_offer_revision', 'exact:confirm_offer_agreement', 'exact:withdraw_offer', 'exact:close_offer', 'exact:expire_offer', 'exact:assign_offer'],
        ],
        'Listings Management' => [
            'Listing lifecycle' => ['exact:publish_listing', 'exact:pause_listing', 'exact:archive_listing'],
            'Listing pricing' => ['exact:change_listing_price'],
        ],
    ];

    /** @return array{0: array<string, array<string, array<string, string>>>, 1: array<string, string>} */
    private static function groupCustomPermissionOptions(array $options): array
    {
        $remaining = collect($options);
        $domains = [];

        foreach (self::CUSTOM_PERMISSION_GROUPS as $domain => $groups) {
            $domainGroups = [];

            foreach ($groups as $group => $patterns) {
                $matched = $remaining->filter(fn (string $label, string $permission): bool => collect($patterns)
                    ->contains(fn (string $pattern): bool => static::permissionMatchesPattern($permission, $pattern)));

                if ($matched->isEmpty()) {
                    continue;
                }

                $domainGroups[$group] = $matched->all();
                $remaining = $remaining->except($matched->keys());
            }

            if ($domainGroups !== []) {
                $domains[$domain] = $domainGroups;
            }
        }

        return [$domains, $remaining->all()];
    }

    private static function permissionMatchesPattern(string $permission, string $pattern): bool
    {
        return str_starts_with($pattern, 'exact:')
            ? $permission === substr($pattern, 6)
            : str_contains($permission, $pattern);
    }

    public static function getTabFormComponentForCustomPermissions(): Component
    {
        $options = static::getCustomPermissionOptions() ?? [];
        [$domains, $other] = static::groupCustomPermissionOptions($options);

        if ($other !== []) {
            $domains['other_custom_permissions'] = ['uncategorized' => $other];
        }

        return Forms\Components\Tabs\Tab::make('custom')
            ->label(__('filament-shield::filament-shield.custom'))
            ->visible(fn (): bool => $domains !== [])
            ->badge(count($options))
            ->schema([
                Forms\Components\Grid::make(2)
                    ->schema(collect($domains)
                        ->map(fn (array $groups, string $domain): Forms\Components\Section => Forms\Components\Section::make(static::rolePermissionGroupLabel($domain))
                            ->description(__('permissions.count', ['count' => collect($groups)->map(fn (array $permissions) => count($permissions))->sum()]))
                            ->collapsible()
                            ->columns(2)
                            ->schema(collect($groups)
                                ->map(fn (array $permissions, string $group): Forms\Components\Section => Forms\Components\Section::make(static::rolePermissionGroupLabel($group))
                                    ->compact()
                                    ->schema([
                                        static::getCheckboxListFormComponent(
                                            name: 'custom_'.str($domain.'_'.$group)->slug('_'),
                                            options: $permissions,
                                        ),
                                    ]))
                                ->values()
                                ->all()))
                        ->values()
                        ->all()),
            ]);
    }

    /** Translate custom permission labels for presentation only; database keys remain authorization contracts. */
    public static function getCustomPermissionOptions(): ?array
    {
        return FilamentShield::getCustomPermissions()
            ->mapWithKeys(fn (string $permission): array => [$permission => static::rolePermissionLabel($permission)])
            ->toArray();
    }

    private static function rolePermissionLabel(string $permission): string
    {
        $exactLabel = __('permissions.exact.'.$permission);

        if ($exactLabel !== 'permissions.exact.'.$permission) {
            return $exactLabel;
        }

        foreach (['view_any', 'view_own', 'complete_any', 'force_delete_any', 'force_delete', 'delete_any', 'restore_any', 'create', 'update', 'delete', 'view', 'approve', 'cancel', 'complete', 'manage', 'attach', 'detach', 'clear', 'waive', 'change', 'assign', 'replace', 'submit', 'review', 'request', 'close', 'reject', 'commit', 'release', 'record', 'allocate', 'void', 'start', 'resolve', 'correct', 'reactivate', 'publish', 'pause', 'archive', 'move', 'finalize', 'withdraw', 'expire'] as $action) {
            $prefix = $action.'_';

            if (str_starts_with($permission, $prefix)) {
                $entity = substr($permission, strlen($prefix));

                return __('permissions.label', [
                    'action' => __('permissions.actions.'.$action),
                    'entity' => __('permissions.entities.'.$entity),
                ]);
            }
        }

        return str($permission)->headline()->toString();
    }

    private static function rolePermissionGroupLabel(string $group): string
    {
        return __('permissions.groups.'.str($group)->slug('_'));
    }
    /** Keep platform permissions out of a company role form as well as rejecting forged input in the service. */
    public static function getCheckboxListFormComponent(string $name, array $options, bool $searchable = true, array|int|string|null $columns = null, array|int|string|null $columnSpan = null): Component
    {
        if (! auth()->user()?->isSuperAdmin()) {
            $service = app(\App\Services\CompanyRoleService::class);
            $options = collect($options)
                ->reject(fn (string $label, string $permission) => $service->isPlatformPermission($permission))
                ->all();
        }

        return parent::getCheckboxListFormComponent($name, $options, $searchable, $columns, $columnSpan);
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery()->with('company');
        $user = auth()->user();

        if (! $user) {
            return $query->whereRaw('1 = 0');
        }

        if ($user->isSuperAdmin()) return $query;

        return $user->company_id
            ? $query->where('company_id', $user->company_id)
            : $query->whereRaw('1 = 0');
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Role details')
                ->schema([
                    Forms\Components\TextInput::make('name')
                        ->required()->maxLength(255)
                        ->unique(ignoreRecord: true, modifyRuleUsing: fn (Unique $rule, Forms\Get $get) => $rule->where('company_id', auth()->user()?->isSuperAdmin() ? $get('company_id') : auth()->user()?->company_id)),
                    Forms\Components\TextInput::make('guard_name')
                        ->default('web')->required()->maxLength(255)->disabled()->dehydrated(),
                    Forms\Components\Select::make('company_id')
                        ->label('Owning company')
                        ->options(Company::withoutGlobalScopes()->orderBy('name')->pluck('name', 'id'))
                        ->searchable()->preload()->nullable()
                        ->helperText('Empty means a platform role. Only Super Admin can create platform roles.')
                        ->live()
                        ->visible(fn () => auth()->user()?->isSuperAdmin()),
                    Forms\Components\Hidden::make('company_id')->default(fn () => auth()->user()?->company_id)->dehydrated(fn () => ! auth()->user()?->isSuperAdmin()),
                ])->columns(2),
            static::getShieldFormComponents(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            Tables\Columns\TextColumn::make('name')->weight('font-medium')->searchable(),
            Tables\Columns\TextColumn::make('company.name')->label('Scope')->badge()->default('Platform')->color(fn ($state) => $state ? 'primary' : 'gray')->visible(fn () => auth()->user()?->isSuperAdmin()),
            Tables\Columns\TextColumn::make('permissions_count')->counts('permissions')->badge(),
            Tables\Columns\TextColumn::make('updated_at')->dateTime()->since(),
        ])->actions([Tables\Actions\ViewAction::make(), Tables\Actions\EditAction::make(), Tables\Actions\DeleteAction::make()]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListRoles::route('/'),
            'create' => Pages\CreateRole::route('/create'),
            'view' => Pages\ViewRole::route('/{record}'),
            'edit' => Pages\EditRole::route('/{record}/edit'),
        ];
    }
}
