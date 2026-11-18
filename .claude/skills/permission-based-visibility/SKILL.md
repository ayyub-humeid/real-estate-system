---
name: permission-based-visibility
description: Gate widgets, pages and actions by permissions, never by hardcoded role names. Use when writing canView()/canAccess()/visible() logic or dashboard widget queries in this multi-tenant app.
---

# Permissions, not role names

Roles are tenant-defined: each company can create/rename its own roles. `hasAnyRole(['company_admin', ...])` hides things from custom roles and leaks the assumption that role names are global.

## Rules
- `canView()` for widgets: `$user->isSuperAdmin() || $user->canAny(['view_any_<resource>', ...])`. Always guard `auth()->check()` first.
- Use the exact Shield string (`view_any_financial::commitment`, `view_any_actual::cost`). Custom/relation permissions use underscores (see AGENTS.md naming convention).
- Don't duplicate gating in `Dashboard::getWidgets()`; list the widgets and let each `canView()` decide.
- Widget queries that use `withoutGlobalScopes()` MUST re-apply `company_id` manually unless the user is super admin, and cache keys MUST include the tenant (`...v1_{all|company_id}`).
- Status values inside these queries come from enums (see `status-enums-not-strings`).

## Verify
- Test with a user that has the permission but a custom role name, and one with a role name like `company_admin` but no permission.
- Run `php artisan optimize:clear && php artisan filament:cache-components`.
