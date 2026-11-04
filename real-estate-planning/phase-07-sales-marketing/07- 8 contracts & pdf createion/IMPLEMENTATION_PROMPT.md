# Task: Finish Madar Contract PDF & Signatures (Phase 07.7)

Implement against **CONTRACT_DOMAIN.md** and the **existing codebase**. Do **not** read planning/roadmap files. Inspect only relevant models, migrations, services, Filament resources, permissions, and tests; reuse the current contract domain. Keep changes minimal.

1. Render `contract_template_versions.body` placeholders using actual `contract_versions`, parties, terms, unit/project snapshots and company branding. Provide **Preview PDF** (DRAFT, no persistence) and test Arabic/RTL with existing DomPDF.
2. Add **Generate Official PDF** action, only for Approved versions and permission `generate_contract_pdf`. Store via `documents`/`document_versions`, classify as `official_approved_pdf`, and guarantee one official PDF per ContractVersion even under concurrent requests. No automatic generation on approval.
3. Support separate `signed_final_pdf` without overwriting official PDF. Reuse existing `contract_signatures`: authorized company signing uses saved executive signature and/or company stamp from settings; other parties use current signature recording/upload. No automatic signing. Preserve signature evidence and association to the correct official document version.
4. **Company settings cleanup:** rename actual old `lease`-prefixed contract-related columns/fields to `contract` equivalents. Remove unnecessary old-domain contract fields now superseded by the new Contract domain. Audit references before removal, preserve relevant data with safe migrations, update models/forms/services/views/tests, and avoid breaking legacy lease functionality.
5. Add `documents.document_type` only if no equivalent exists. Ensure Submitted content/template immutability, current revision/approval rules, tenant isolation, permissions and focused tests.

Do not add unrelated features, new engines, extra tables, or modify planning documents. Run relevant tests and report only changed files, tests, and any blockers.
