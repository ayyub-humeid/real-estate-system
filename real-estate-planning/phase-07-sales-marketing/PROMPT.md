# Phase 07 — Agent Execution Prompt

You are working on Phase 07 — Sales & Marketing Engine of an existing multi-tenant Real Estate SaaS.

Before implementing any Phase 07 sub-phase:

1. Read this Phase 07 `SPEC.md` completely.
2. Read the project's global planning/conventions files.
3. Read the active sub-phase `SPEC.md` and `PROMPT.md`.
4. Inspect the existing repository before changing architecture or schema.

## Critical Rules

- Do not implement the whole of Phase 07 at once.
- Implement only the active sub-phase.
- Do not create tables or abstractions for later sub-phases unless the active sub-phase genuinely requires them.
- Reuse existing multi-tenancy, `HasCompany`, permissions, audit, enums, Filament patterns, Unit/Planned Unit relations, and `company_settings`.
- New `status` and `type` fields must use PHP Backed Enums.
- Workflow-controlled statuses must change only through explicit domain/service actions, not generic CRUD status edits.
- Validate company ownership in the application/domain layer, not only in the UI.
- Do not add `reserved`, `sold`, `rented`, or `occupied` to physical Unit status or Listing lifecycle.
- Preserve historical commercial truth. Do not silently rewrite Offers, Reservations, Snapshots, or Contracts because a Unit or Listing changed.
- Planned and Actual representations connected through `units.planned_unit_id` must be treated as the same real-world unit for commercial availability/exclusivity where applicable.
- Social-channel publication is separate from Listing lifecycle and belongs to Phase 07.7.
- Contracts and payment obligations remain Phase 08 responsibility.

## Database / Quality

- Follow existing migration conventions.
- Add company settings when the active sub-phase needs them; do not postpone necessary settings.
- Avoid speculative generic infrastructure.
- Add focused automated tests.
- Run affected tests against both MySQL and PostgreSQL.
- Do not consider a sub-phase complete while relevant existing tests are failing.

## End-of-Work Report

At the end of every sub-phase, report:

1. files added/changed
2. migrations added
3. enums added/reused
4. workflow rules implemented
5. permissions/settings added
6. tests added
7. MySQL results
8. PostgreSQL results
9. any repository detail that required adapting the planning specification
