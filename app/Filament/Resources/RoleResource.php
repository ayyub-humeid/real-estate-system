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
