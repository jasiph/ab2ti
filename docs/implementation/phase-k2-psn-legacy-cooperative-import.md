# Phase K2 — PSN participation, historical Cooperative funds, and legacy import

Implemented 2026-08-26. Phase K1 remains the Cooperative membership foundation. Tag `ab2ti-2.0-rc` (commit `0037d7ce7451b57d805afa04a2ee064e8e326fb5`) remains a historical pre-Cooperative baseline and must not be moved. This phase does **not** activate savings-and-loan operations, online share purchase, or payments. Real legacy member data was **not** imported.

## PSN domain

PSN is an existing Cooperative business, represented as `CooperativeBusiness`:

| Field | Canonical PSN |
|---|---|
| `code` | `PSN` |
| `name` | Penggilingan Padi |
| public label | PSN — Penggilingan Padi |
| `participation_unit_name` | Saham PSN |
| `current_unit_value` | integer IDR on the business row (seeded `1000000`) |

Application code reads the current unit value from the business record. It does not hardcode Rp1.000.000. Historical ledger rows store the `unit_value` used at the time of the entry.

```text
CooperativeMembership  1 — 0..1 per business  BusinessParticipation  1 — many  ParticipationEntry
```

Uniqueness: `(cooperative_membership_id, cooperative_business_id)`. Share counts, values, and `share_reference` are **not** stored on `cooperative_memberships`.

Current holding is derived:

```text
current_units = SUM(participation_entries.units)
```

Current staff/member value uses `current_units × business.current_unit_value`. Ledger rows keep their own historical `unit_value`.

## Participation ledger

`participation_entries` is the auditable ledger. K2 entry types:

- `opening_balance`
- `adjustment`

Purchase, sale, dividend, and redemption are not invented. `source_type` includes `legacy_import`, `staff`, and `import_reversal`. Provenance JSON records import batch, source row, operator, timestamps, and whether units were **explicit** or **inferred**.

## Historical Cooperative funds

`cooperative_fund_entries` preserves imported Simpanan Pokok (`principal_saving`) and Simpanan Sukarela (`voluntary_saving`) as `opening_balance` records only.

These are **historical imported positions**, not a live savings product and not treated as currently withdrawable balances.

## Legacy source interpretation

The spreadsheet strongly suggests:

```text
Simpanan Pokok + Simpanan Sukarela = Total ≈ PSN units × current unit value
```

K2 preserves **both** the source of funds and the resulting PSN participation. That conversion is a documented business interpretation supported by source data, not a legal assumption that erases savings history.

`Total` is a reconciliation field. If `source_total ≠ pokok + sukarela`, the row is **NEEDS REVIEW**. Discrepancies are never overwritten.

`No. Saham` examples:

| Source | share_reference | explicit_units |
|---|---|---|
| `32-1-000181 (50)` | `32-1-000181` | 50 |
| `33-000233 (2)` | `33-000233` | 2 |
| `33-000231` | `33-000231` | none (may be inferred from amount) |

The importer distinguishes **explicit source fact** from **inferred value** (`units_origin`). Amounts that do not divide exactly by the unit value, or that disagree with explicit units, go to review. No rounding.

Dates may be a single day (`26/09/2023`) or a range (`26-27/09/2023`). Canonical rows use `effective_date_start` / `effective_date_end`. Uncertain dates stay in staging and need review.

`Ket` / `Keterangan` stay as `legacy_label` / `legacy_notes` on staging. They are not normalized into payment method, staff, or channel.

The NIK column may contain a 16-digit NIK, an AB2TI identifier such as `AB2TI/08/I/0005`, or other text. **Member has no `nik` column** (Phase 1F / membership contract). Valid NIK is classified and kept on staging only. It is never written onto Member. Exact NIK auto-match is therefore not available; matching continues with identifier / email / phone.

Money is stored as integer rupiah (`unsignedBigInteger` / `bigInteger`). Indonesian grouping (`1.000.000`), blank, and `-` parse to integer amounts. Floating point is not used.

## Staging architecture

```text
Excel/CSV → CooperativeImportBatch → CooperativeImportRow
  → parse → validate → match Member → preview
  → READY / NEEDS REVIEW / INVALID
  → deliberate commit → canonical records
```

Never write spreadsheet rows directly into production domain tables.

Batch statuses: `uploaded`, `parsed`, `review_required`, `ready`, `committed`, `failed`, `reversed`.

Staging rows store `raw_data`, `normalized_data`, match method, validation status/errors, and `committed_at`. Staging is staff-only.

Dependency: `openspout/openspout` reads XLSX and CSV. CSV is also accepted. XLSX ZIP/XML is not hand-parsed.

## Matching rules (conservative)

Priority for automatic binding:

1. Exact known AB2TI membership / legacy identifier (`membership_number`)
2. Exact email
3. Exact normalized phone
4. Name **plus** a supporting unique identifier that already agrees (never name-only)
5. Manual review

Valid NIK cannot auto-bind (no Member.nik). Multiple candidates → `ambiguous` → NEEDS REVIEW. Unmatched persons are not auto-created as User or Member. A matched AB2TI Member without Cooperative membership may be **proposed** for `CREATE CooperativeMembership` and is created only at commit of a ready row, using a parseable source date when present.

Match methods recorded: `member_identifier_exact`, `email_exact`, `phone_exact`, `name_with_support`, `manual`, `unmatched`, `ambiguous`.

## Dry-run / preview

Upload and parse do not change canonical Cooperative/PSN data. The batch show page reports totals, matched, ready, needs review, invalid, proposed memberships, proposed fund openings, proposed PSN participants, and proposed total PSN units. Commit is a separate authorized action.

## Review / commit / idempotency

Operators can inspect raw vs normalized data, assign a Member manually, and commit only ready rows. Ambiguous money, dates, totals, shares, or identities are not auto-converted to reach 100% import.

Each ready row commits in its own DB transaction: ensure Member exists, refuse duplicate canonical import, create Cooperative membership if needed, write fund openings, create PSN participation + opening ledger row, store provenance, mark the staging row committed. A failed row leaves no half-imported canonical data.

Idempotency: file SHA-256, source row number, committed-row guard, and refusal to write a second PSN/fund opening for the same membership. Uploading the same bytes sets `duplicate_of_batch_id` and warns. A committed identical file cannot be committed again. A committed staging row cannot be committed twice.

## Provenance / reversal

Canonical financial and participation rows store `source_type`, `import_batch_id`, `legacy_source_row_id`, and provenance JSON (operator, timestamp, file hash, explicit vs inferred units, source amounts). Staging rows remain after commit.

Controlled reversal is implemented for a **fully committed** batch that has **no later participation entries** on the affected positions: reversing `adjustment` rows are written; fund openings are marked `reversed_at` (not deleted); staging remains committed. This is not a generic financial rollback.

## Member and public surfaces

Active Cooperative members see their own PSN units, current nominal, participation value, read-only history, and historical pokok/sukarela, labeled as imported/historical. No mutation. No other member’s holdings. No staging JSON.

`CooperativePublicStats` adds `total_psn_shares` and `psn_participants` from committed ledger rows for **active** Cooperative memberships with current units > 0. Staging and review rows are excluded. `/koperasi` shows those aggregates plus existing K1 cards. No names, balances, share references, or transactions.

## Privacy / ACL

Staging, import, and PSN admin are staff-only (`cooperative.view` / `cooperative.manage`). No new permission names. `membership-admin` already has both; `editor` does not. Backend policies remain authoritative.

## Limitations / future boundary

- Savings-and-loan, withdrawals, interest, SHU, dividends, GL, bank reconciliation: out of K2
- Online PSN purchase, cart, checkout, payments, invoices: out of K2
- Member self-enrolment: out of K2
- Additional Cooperative businesses: registry exists; only PSN is seeded
- Exact NIK match awaits a separate, constitution-compatible identity decision; NIK is not stored on Member
- Actual production spreadsheet import is a later controlled operational execution (`docs/operations/cooperative-legacy-import.md`)
