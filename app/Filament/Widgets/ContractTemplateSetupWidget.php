<?php

namespace App\Filament\Widgets;

use App\Enums\ContractType;
use App\Filament\Resources\ContractTemplateResource;
use App\Models\ContractTemplate;
use App\Services\ContractTemplateReadiness;
use Filament\Widgets\Widget;

/** Warns, before anyone is blocked, that some contracts cannot be issued yet. */
class ContractTemplateSetupWidget extends Widget
{
    protected static string $view = 'filament.widgets.contract-template-setup';

    protected static bool $isLazy = false;

    protected static ?int $sort = -10;

    protected int|string|array $columnSpan = 'full';

    public static function canView(): bool
    {
        $user = auth()->user();

        // A platform administrator has no company of their own to set up.
        return $user !== null
            && ! $user->isSuperAdmin()
            && $user->company_id
            && $user->canAny(['view_any_contract', 'create_contract'])
            && app(ContractTemplateReadiness::class)->missingTypes($user->company_id) !== [];
    }

    protected function getViewData(): array
    {
        $companyId = auth()->user()->company_id;
        $readiness = app(ContractTemplateReadiness::class);
        $missing = $readiness->missingTypes($companyId);

        return [
            'heading' => count($missing) === count($readiness->requiredTypes($companyId))
                ? __('contract_domain.readiness.none_heading')
                : __('contract_domain.readiness.some_heading'),
            'gaps' => array_map(fn (ContractType $type): array => $this->gap($companyId, $type), $missing),
        ];
    }

    /** The one next step for this kind of contract that this person is allowed to take. */
    private function gap(int $companyId, ContractType $type): array
    {
        $empty = ContractTemplate::withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->where('contract_type', $type->value)
            ->where('is_active', true)
            ->first();

        $gap = ['type' => $type->getLabel(), 'url' => null, 'action' => null];

        if ($empty) {
            $gap['problem'] = __('contract_domain.readiness.no_text', ['template' => $empty->name]);
            if (ContractTemplateResource::canEdit($empty)) {
                $gap['url'] = ContractTemplateResource::getUrl('view', ['record' => $empty]);
                $gap['action'] = __('contract_domain.readiness.add_text');
            }

            return $gap;
        }

        $gap['problem'] = __('contract_domain.readiness.no_template');
        if (ContractTemplateResource::canCreate()) {
            $gap['url'] = ContractTemplateResource::getUrl('create', ['contract_type' => $type->value]);
            $gap['action'] = __('contract_domain.readiness.create_template');
        }

        return $gap;
    }
}
