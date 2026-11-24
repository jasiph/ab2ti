# Cooperative post-import reconciliation

Implementation reference for the post-import review workflow introduced after batch #10 canonical replace-legacy import.

## Data model

- `cooperative_import_review_items` — one row per `(batch, import_row, category, warning_code)` detected at sync time
- `cooperative_import_review_actions` — append-only audit trail per operator decision

Review item lifecycle: `pending` → `resolved` or `accepted_as_is`.

Outcomes include: `corrected`, `accepted_as_is`, `confirmed_distinct`, `confirmed_same_person`, `accepted_shared_contact`, `confirmed_no_psn_participation`, `manual_reconciliation`, `unresolved_requires_followup`, `source_verified`.

## Services

| Class | Role |
|-------|------|
| `ImportReviewSynchronizer` | Idempotent materialization from import row warnings; prunes misclassified PSN→`other_legacy` duplicates without audit |
| `ImportReviewInventory` | Dashboard counts (unique rows vs total flags) |
| `ImportReviewResolver` | Profile/shared/duplicate item resolution |
| `PsnManualReconciler` | PSN-only ledger commit with manual provenance |
| `PsnReconciliationReadiness` | Batch PSN disposition completeness (does not flip config) |

## Routes

- `GET admin/koperasi/import/{batch}/reconciliation` — dashboard + list (`cooperative.view`)
- `POST .../reconciliation/items/{item}/resolve` — non-PSN decisions (`cooperative.manage`)
- `POST .../reconciliation/psn/{row}/resolve` — PSN manual resolution (`cooperative.manage`)

## Non-goals (this phase)

- No Member merge engine
- No `Member.nik`
- No fabricated contact or PSN data
- No automatic `AB2TI_MEMBER_REGISTRY_COMPLETE` or `PSN_LEGACY_RECONCILIATION_COMPLETE` from UI actions

## Tests

`tests/Feature/Cooperative/CooperativePostImportReconciliationTest.php` covers audit preservation, profile accept/correct, shared contact without merge, duplicate NIK without merge/delete, PSN manual/idempotent/no-participation flows, public stat gates, and ACL.
