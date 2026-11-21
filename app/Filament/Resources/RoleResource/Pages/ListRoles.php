<?php

namespace App\Filament\Resources\RoleResource\Pages;

use App\Filament\Resources\RoleResource;
use App\Filament\Support\RunsWorkflowAction;
use App\Models\{Company, Role};
use App\Services\CompanyRoleService;
use App\Support\StandardRoles;
use Filament\Actions;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;

class ListRoles extends ListRecords
{
    use RunsWorkflowAction;

    protected static string $resource = RoleResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('installStandardRoles')
                ->label(__('roles.standard.action'))
                ->icon('heroicon-o-sparkles')
                ->color('gray')
                ->tooltip(__('roles.standard.tooltip'))
                ->modalHeading(__('roles.standard.action'))
                ->modalDescription(__('roles.standard.description'))
                ->modalSubmitActionLabel(__('roles.standard.submit'))
                ->visible(fn (): bool => auth()->user()->can('create', Role::class) && (auth()->user()->company_id || auth()->user()->isSuperAdmin()))
                ->form(fn (): array => auth()->user()->isSuperAdmin() ? [
                    Forms\Components\Select::make('company_id')->label(__('Company'))
                        ->options(fn () => Company::withoutGlobalScopes()->orderBy('name')->pluck('name', 'id'))
                        ->searchable()->required(),
                ] : [])
                ->action(function (array $data): void {
                    $company = Company::withoutGlobalScopes()->findOrFail(auth()->user()->isSuperAdmin() ? $data['company_id'] : auth()->user()->company_id);

                    $this->runWorkflowAction(
                        operation: fn () => app(CompanyRoleService::class)->installStandardRoles($company),
                        successTitle: fn (array $result): string => count($result['created'])
                            ? __('roles.standard.created', ['roles' => collect($result['created'])->map(fn (string $name): string => __('roles.names.'.$name))->implode(', ')])
                            : __('roles.standard.nothing_new'),
                        successBody: fn (array $result): ?string => count($result['skipped'])
                            ? __('roles.standard.kept', ['roles' => collect($result['skipped'])->map(fn (string $name): string => __('roles.names.'.$name))->implode(', ')])
                            : null,
                    );
                }),
            Actions\CreateAction::make(),
        ];
    }
}
