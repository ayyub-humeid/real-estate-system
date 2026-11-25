<?php

namespace App\Support;

/**
 * Ready-made company roles, so an admin does not tick hundreds of permissions by hand.
 * The list is explicit on purpose: every name is a real permission string checked by a
 * policy (a test keeps it that way). Accounting is covered by the existing
 * `financial_manager` role.
 */
class StandardRoles
{
    private const CRM_BASE = [
        'view_any_lead', 'view_lead', 'create_lead', 'update_lead', 'convert_lead_to_party',
        'view_any_lead_inquiry', 'view_lead_inquiry', 'create_lead_inquiry', 'update_lead_inquiry',
        'view_any_opportunity', 'view_opportunity', 'create_opportunity', 'update_opportunity',
        'move_opportunity_stage', 'close_won_opportunity', 'close_lost_opportunity',
        'view_own_crm_follow_up_tasks', 'create_crm_follow_up_task', 'update_crm_follow_up_task',
        'complete_crm_follow_up_task', 'cancel_crm_follow_up_task',
        'create_crm_activity', 'view_crm_activity',
        'view_own_crm_offer', 'manage_own_crm_offer', 'view_any_crm::offer', 'view_crm::offer',
        'create_crm::offer', 'update_crm::offer', 'create_offer_revision',
        'finalize_offer_revision', 'confirm_offer_agreement', 'accept_offer', 'withdraw_offer', 'close_offer',
        'convert_offer_to_reservation',
        'view_any_unit::reservation', 'view_unit::reservation', 'create_unit::reservation', 'send_reservation_for_approval',
        'view_any_party', 'view_party', 'create_party',
        'view_any_unit', 'view_unit', 'view_any_property', 'view_property', 'view_any_listing', 'view_listing',
        'view_any_contract', 'view_contract',
    ];

    private const SALES_MANAGER_EXTRA = [
        'assign_lead', 'assign_opportunity', 'assign_offer', 'assign_crm_follow_up_task',
        'view_team_crm_follow_up_tasks', 'complete_any_crm_follow_up_task',
        'request_offer_approval', 'approve_offer_revision', 'reject_offer_revision', 'expire_offer',
        'approve_and_activate_reservation', 'reject_reservation_request', 'cancel_unit_reservation',
        'extend_unit_reservation', 'expire_unit_reservation', 'update_unit::reservation',
        'update_party',
        'create_contract', 'create_contract_from_reservation', 'update_contract',
        'export_lead', 'export_contract', 'export_unit',
    ];

    private const PROJECT_MANAGER = [
        'view_any_project', 'view_project', 'create_project', 'update_project', 'manage_project_members',
        'view_any_project_building', 'view_project_building', 'create_project_building', 'update_project_building',
        'view_any_project_planned_unit', 'view_project_planned_unit', 'create_project_planned_unit', 'update_project_planned_unit',
        'view_any_property::acquisition', 'view_property::acquisition',
        'view_any_project::design::package', 'view_project::design::package', 'create_project::design::package',
        'update_project::design::package', 'upload_design_version', 'decide_design_version',
        'view_any_project::construction', 'view_project::construction', 'create_project::construction', 'update_project::construction',
        'view_any_project::budget', 'view_project::budget',
        'view_any_financial::commitment', 'view_financial::commitment',
        'view_any_actual::cost', 'view_actual::cost',
        'export_project', 'export_unit',
        'view_any_property', 'view_property', 'view_any_unit', 'view_unit', 'view_any_party', 'view_party',
    ];

    /** @return array<string, string[]> role name => permission names */
    public static function all(): array
    {
        return [
            'sales_agent' => self::CRM_BASE,
            'sales_manager' => [...self::CRM_BASE, ...self::SALES_MANAGER_EXTRA],
            'project_manager' => self::PROJECT_MANAGER,
        ];
    }

    /** @return string[] */
    public static function names(): array
    {
        return array_keys(self::all());
    }
}
