# Phase K2H — PSN and legacy Cooperative import hardening

Implemented 2026-08-26. Phase K1 remains closed. Phase K2 remains closed (PASS WITH DOCUMENTED DEVIATION: Member has no `nik`). Tag `ab2ti-2.0-rc` (commit `0037d7ce7451b57d805afa04a2ee064e8e326fb5`) remains the historical pre-Cooperative baseline and must not be moved. This phase does **not** import real Cooperative spreadsheet data, add Member NIK, activate savings products, or start final RC hardening.

## Decision

**PASS.** Isolated MySQL migrate and focused Cooperative import/ledger tests passed on a throwaway schema (`ab2ti_k2h_isolated`), not the active development database. True two-request parallel races were not executed; commit uses `lockForUpdate`, nested savepoints, and unique indexes.

**LEGACY IMPORT READINESS:** READY FOR CONTROLLED LEGACY IMPORT, only after a separate explicit operational authorization. Real import was **not** executed in K2H.

## Financial semantics

Interpretation of historical source evidence (not a legal conclusion):

Simpanan Pokok + Simpanan Sukarela ≈ funds converted into PSN participation, with 1 Saham PSN = Rp1.000.000.

The application preserves:

- A. historical source funds (`cooperative_fund_entries` openings)
- B. resulting PSN participation (`participation_entries` ledger)

They are **not** two independent current assets. Staff, Member workspace, and public copy treat historical funds as **sumber dana historis penyertaan** / historical source of participation. PSN current value is `current_units × current_unit_value` only. Historical fund amounts are never added to that figure.

Blank pokok/sukarela with a present Total does **not** manufacture fund openings; participation units may still be inferred when the importer rule is exact.

## Invariants

Service: `App\Support\Cooperative\FinancialInvariants`.

- Opening units and unit value must be positive integers; `amount = units × unit_value`; no fractional-share rounding.
- Adjustments may be signed; reason/provenance required; resulting aggregate units must not be negative.
- Historical fund openings must be positive integer IDR; zero/blank sources do not create fund rows.

Additive DB (migration `2026_08_26_140000_harden_cooperative_psn_import.php`):

- unique participation per membership/business (already K2)
- unique `(import_batch_id, legacy_source_row_id, entry_type)` on participation entries
- unique `(import_batch_id, legacy_source_row_id, fund_type)` on fund entries
- unique `reverses_entry_id` (one compensating row per reversed opening)
- `source_fingerprint` on staging rows (indexed, not unique — two uncommitted uploads may share a fingerprint)
- `matched_by` / `matched_at` for manual matching provenance

Unique indexes do not block later independent **adjustments**.

## Import state, idempotency, concurrency

Batch: `uploaded → parsed / review_required / ready → committed`. Failed parse is `failed` (never ready/committed). `refreshBatchStatus` never downgrades `committed` or `reversed`.

Row: ready rows commit once (`committed_at`). A committed batch cannot return to ready. A reversed batch is not a fresh uncommitted batch.

Idempotency:

- A. same file twice → duplicate file hash warning
- B. renamed same bytes → same hash warning
- C. same source identity in another file → `source_fingerprint` + `duplicate_committed_source` review
- D/E. committed batch/row cannot commit again
- F. same Member/source opening in another batch → fingerprint + existing opening guards

Conflicts become review/failure, not silent success.

Concurrency: batch `lockForUpdate`, ready rows `lockForUpdate`, membership/participation locks, nested per-row savepoints, uniqueness. Sequential double-commit is covered on SQLite and isolated MySQL. Two HTTP requests at the same instant were not live-raced.

## Reversal

Authorized `cooperative.manage` only. Compensating PSN adjustments (`reverses_entry_id`); fund openings marked `reversed_at` (not deleted); original ledger rows immutable; staging remains committed. Second reverse is blocked. Later independent adjustments **block automatic reversal**. Public aggregates and Member workspace follow current units (reversed position returns to zero).

## Private data

- Source files: disk `cooperative_imports` → `storage/app/private/cooperative-imports`. UUID storage name. Original filename is metadata only (`basename`). No public URL, no `public/storage`. No retrieval route. Streamed write + `hash_file`. Logs record batch id and hash, not spreadsheet rows.
- Staging `raw_data` / `normalized_data`: `EncryptedJsonCast` (JSON wrapper `{_encrypted}` so MySQL json columns remain valid; plaintext legacy readable). Not SQL-queryable. List Inertia props send summaries only; inspect loads one authorized row; NIK digits are classified on inspect and omitted from list. Public/Member props never receive staging JSON.
- Member has no `nik`. Valid NIK is evidence during staff reconciliation only. Matching paths: AB2TI identifier, email, phone, manual verification. Name-only never auto-binds. Conflicting signals → NEEDS REVIEW.

## Parsers

Canonical `HeaderMapper` (BOM strip, whitespace collapse, aliases including `tanggal terdaftar anggota`). Duplicate canonical headers or missing core (name + total|principal|share) → invalid file, no partial guess. Extra non-empty worksheets rejected **in K2H**. A later compatibility hotfix inspects worksheets and requires operator selection instead of failing the workbook. Max 10 240 KB and 5 000 data rows (config). OpenSpout streaming. Corrupt/renamed files → controlled `failed` batch, no stack to operator, no canonical writes.

Money: Indonesian grouping, `Rp`, trailing `,00`, US grouping integers, `0`, `-`, blank. Negatives and mixed decimals rejected. Integer IDR only.

Dates: raw preserved; `d/m/Y` and `d-d/m/Y`; ISO; Excel DateTime cells. Ambiguous/invalid → review, not guessed.

Shares: reference vs explicit `(n)` vs inferred vs invalid (multiple/malformed parentheses).

## Limits

| Limit | Value |
|---|---|
| Extensions | csv, xlsx |
| Max size | 10 240 KB |
| Max data rows | 5 000 |
| Worksheets | first sheet only; extra data sheets rejected |
| Empty workbook | failed |

## MySQL / performance

PHPUnit default remains in-memory SQLite. K2H also migrated and ran focused Cooperative import/ledger tests against an isolated MySQL schema (`ab2ti_k2h_isolated` on the local MariaDB/MySQL host). That schema is not the active development database `ab2ti`. Isolated SQLite `php artisan migrate --force` also passed (K2H migration included).

Synthetic 500-row parse (sanitized fixtures) completed in the Cooperative test group without catastrophic memory growth (suite peak observed ~70 MB). This is a sanity observation, not a load-test claim.

## Operator UI

Preview shows filename, file hash, duplicate warning, row counts, matched/ready/review/invalid, memberships to create, historical principal/voluntary amounts, PSN participants/units, explicit vs inferred units. Copy: “Preview does not change Cooperative/PSN canonical data.” Commit requires explicit confirmation (`confirm` accepted). Review rows highlight unmatched identity, identifier conflict, total/share mismatch, invalid date, ambiguous units, NIK-not-a-match-key. Inspect is a separate authorized payload.

## Real import

Not executed. Controlled import still requires backup, authorized file, and an explicit operational task after this phase.

## Dependencies

OpenSpout remains locked at **v4.32.0**. `composer validate` passed. `composer audit` reported advisories on other locked Laravel/Guzzle/Symfony packages; none named OpenSpout. Those packages were not upgraded in K2H. The spreadsheet import path does not use Guzzle to parse XLSX/CSV.
