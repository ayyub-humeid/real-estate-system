# Madar — Contract Domain (Phase 07.7)

## Existing domain (reuse, do not rebuild)
- `contract_templates` and immutable-in-use `contract_template_versions`: `body` is legal HTML with placeholders; `clauses` are default clauses.
- `contract_versions`: versioned contract data and pinned `template_version_id`; `contract_parties` and `contract_terms` provide actual parties and negotiated terms.
- Existing approval, revision, document, signature and activation workflows remain authoritative.
- `documents` + `document_versions` store contract-version files; `contract_signatures` records who signed, when, and the exact document version/hash.

## PDF lifecycle
1. Draft / Revision Required: **Preview PDF** renders `template.body` + frozen/current contract-version data + actual `contract_terms` + company branding. Preview is temporary, watermarked DRAFT, never stored.
2. Submitted: freeze review content, parties, terms, unit data and attachments. Referenced template version must not be edited or deleted. Revision Required can be corrected and resubmitted according to existing workflow; Approved content is immutable.
3. Approved: authorized **Generate Official PDF** action (`generate_contract_pdf`) creates exactly one `official_approved_pdf` for a `ContractVersion`; no automatic issuance on approval. Store using existing document infrastructure. Enforce idempotency/concurrency safety.
4. Signing: official PDF remains unchanged. `contract_signatures` documents explicit authorized signature events tied to the correct official document version. Produce a separate `signed_final_pdf` after required signatures, without replacing official PDF. Follow existing activation rules.

## Signature and settings scope
- `company_settings` holds reusable authorized executive/manager signature image and company stamp/signature image; neither is automatically applied upon PDF issuance.
- For company signing, authorized action may use those saved assets; other parties use the existing signature upload/recording workflow. Snapshot applied images/evidence so later settings edits cannot change past signed documents.
- Refactor old **lease-specific** contract-related settings names to **contract** names, based on actual schema. Remove obsolete old-domain contract settings only after verifying usages; preserve data needed by the new domain. Update all references and migrations safely, including any legacy lease PDF compatibility.

## Document classification and safeguards
- Use `documents.document_type` (or existing equivalent if present): `supporting`, `official_approved_pdf`, `signed_final_pdf`.
- Preserve authorization, tenant isolation, immutable approved content, accurate Arabic/RTL rendering, and test coverage.
- No new contract/signature engine, unnecessary tables, or unrelated planning changes.
