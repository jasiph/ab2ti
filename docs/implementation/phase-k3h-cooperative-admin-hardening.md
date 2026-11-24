# Phase K3H — Cooperative admin operational hardening

Implemented: 2026-09-11

Prior phases remain closed: K1, K2 (PASS WITH DOCUMENTED DEVIATION), K2H, controlled legacy import, post-import reconciliation, K3.

This phase does **not** start Member Account Experience, savings/loans/SHU, online share purchase, or final RC. Tag `ab2ti-2.0-rc` remains historical and must not move.

## Decision

Harden the existing Cooperative staff workspace so ordinary administration stays easy and PSN financial mutation is deliberate:

```text
READ → UNDERSTAND → ENTER EDIT MODE → REVIEW CHANGE → EXPLICITLY CONFIRM → LEDGER ENTRY → AUDIT
```

Member detail stays read-only. PSN shares become editable only after **Kelola Saham PSN**. There are no inline share fields, +/- controls, editable table cells, or tap-to-increment behavior.

## Stale-data protection

Confirmation carries `expected_current_units` from the page that was reviewed.

At mutation time, inside a transaction:

1. `lockForUpdate()` on the Cooperative membership and PSN `BusinessParticipation`
2. Recalculate canonical current units as `SUM(participation_entries.units)` from the database (not from the request)
3. Compare with `expected_current_units`
4. If they differ, reject. Do not silently recalculate and continue.

Operator message (id/en): the share data changed since the page was opened; review the latest count before confirming. The edit page shows the reviewed count vs the latest count.

## Concurrency / locking

Final PSN mutation uses a database transaction and MySQL-safe `lockForUpdate()` on membership and participation rows. Current units are always derived from the ledger after the lock. Resulting `SUM(units)` is verified before commit. Resulting units may not be negative.

SQLite PHPUnit does not prove InnoDB lock behavior. Isolated MySQL verification is documented below.

## Double-submit / idempotency

Each confirmation has a server-issued `confirmation_token` (UUID). A successful adjustment stores it uniquely on `participation_entries.confirmation_token`.

Replaying the **same** confirmed request (same token, same membership, same desired units) returns the original ledger row and does **not** insert a second `ParticipationEntry`.

A reused token with a **different** desired total is rejected. Desired == current is **not** used as the idempotency mechanism.

Frontend loading/disabled controls are UX only.

After success the controller redirects to Member detail (PRG). Refresh/Back does not reuse the financial POST as a live mutation.

## PSN adjustment invariants

Authoritative values are server-derived:

```text
current units = SUM(participation_entries.units)
delta         = desired units - current units
result        = current units + delta
value         = result × current PSN unit value (snapshotted on the new entry)
```

Frontend current/delta/previous/result/amount are ignored.

Desired units must be a non-negative integer. `1.5`, `1,5`, `1e3`, and `-1` are rejected server-side. `desired == current` creates no ledger row and returns “Tidak ada perubahan jumlah saham.”

## Share decrease safety

Reductions are allowed. Step 2 shows **PENGURANGAN SAHAM** / SHARE REDUCTION with before / signed change / after. A valid reason, a meaningful note, and an explicit reduction acknowledgement are required. There is no business prohibition against reductions.

## Effective date / unit value

`effective_date_start` is the operator-chosen effective date. `created_at` is the recording timestamp. Application timezone is **Asia/Jakarta**.

Ordinary administrative adjustments may not use a future effective date (`config/cooperative.php` `disallow_future_psn_adjustment_dates = true`). Historical effective dates on old rows are never rewritten.

Each new adjustment stores `unit_value` and `amount = delta × unit_value` on that row so later changes to `CooperativeBusiness.current_unit_value` cannot rewrite history.

## Ledger immutability

There is no ordinary staff route that UPDATE/DELETE an existing `ParticipationEntry`, rewrite a legacy opening, or rewrite a previous administrative adjustment. Import reversal and replace-legacy remain import-scoped compensating/cleanup paths, not day-to-day share editing.

## Correction / recovery

A wrong adjustment is corrected by a **new** compensating `ParticipationEntry` (example: +3 then −3) with reason `administrative_correction`. Optional `correction_of` in provenance points at the previous entry. `reverses_entry_id` is not used here; that column remains the narrower legacy-import reversal link.

Operational protection is: immutable ledger + audit provenance + compensating corrections + routine database backups. K3H does **not** create a SQL dump per share change.

## Inactive membership

Ordinary **Kelola Saham PSN** is unavailable while Cooperative membership is inactive. The membership is not silently reactivated. Historical PSN remains visible. Restoring ordinary adjustment requires an explicit membership reactivation through the membership edit workflow first. A future dedicated historical-correction workflow is out of scope.

## Unresolved PSN

Members with pending `psn_unconfirmed` review cannot use ordinary adjustment. The UI offers **PSN perlu direkonsiliasi → Selesaikan Rekonsiliasi**. Unresolved PSN is not displayed as `0 saham`. Confirmed no-participation (`confirmed_none`) is a distinct state from unresolved.

## ACL / tampering / CSRF

Existing permissions only: `cooperative.view` (read dashboard/list/detail/PSN/reconciliation) and `cooperative.manage` (membership write, PSN adjustment, reconciliation mutation). Financial POST is server-authorized. Forged `membership_id` / `member_id` / `participation_id` values that do not match the route membership are rejected. Mutation remains a session/CSRF-protected POST under `/admin`. There is no GET mutation and no unprotected API shortcut.

## Mobile / two-step flow

```text
Member Detail → Kelola Saham PSN → Input → Review → Confirm
```

Step 1 and Step 2 never mutate. Only **KONFIRMASI PERUBAHAN SAHAM** POSTs. Enter in the numeric field continues to review, not to the ledger. Drafts are not persisted; returning to the page loads canonical current units and a new confirmation token.

## Dashboard / search / filters

Dashboard resolved stats use grouped `SUM(units)` over active memberships (no load of every ledger row). While `PSN_LEGACY_RECONCILIATION_COMPLETE=false`, recorded value is labeled **Nilai Penyertaan Terdata** / Recorded participation value — not a final organization PSN capital total.

Recent activity distinguishes legacy opening, manual reconciliation, administrative adjustment, correction, membership activation/deactivation, review outcomes, and legacy import/replacement. Imported history is labeled as imported. NIK, address, spreadsheet rows, and private notes are not shown.

Member search: partial name, case, collapsed whitespace, membership number, email, normalized phone. Legacy NIK is not a search key.

Filters: PSN participant = resolved units > 0; PSN pending = pending PSN reconciliation; review pending / profile incomplete use pending review items only.

## Tests

`tests/Feature/Cooperative/CooperativeAdminHardeningK3hTest.php` (SQLite PHPUnit)

`tests/Feature/Cooperative/CooperativeAdminHardeningK3hMysqlTest.php` (skipped unless `DB_CONNECTION=mysql` on an isolated schema)

## MySQL verification

Isolated schema `ab2ti_k3h_isolated` on MySQL **8.0.44** (not database `ab2ti`).

```text
vendor/bin/phpunit -c <isolated mysql phpunit xml> --filter CooperativeAdminHardeningK3h
OK (25 tests, 224 assertions)
```

Covered on InnoDB: stale rejection, confirmation-token idempotency, `lockForUpdate` blocking a second connection (`innodb_lock_wait_timeout = 1`), sequential competing adjustments whose final `SUM(units)` matches accepted operations.

True two-HTTP-request wall-clock races were not live-raced. Locking correctness is demonstrated by InnoDB lock-wait plus unique `confirmation_token`.

The additive migration was **not** applied to the active development database during K3H. Apply `2026_09_11_100000_add_psn_adjustment_confirmation_token` on each environment before using the hardened confirmation flow there.
