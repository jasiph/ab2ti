# Cooperative legacy import runbook

Operator procedure for Koperasi Pusat AB2TI historical spreadsheet import. Do not import real member data until this runbook is followed on an authorized file, with a backup, after Phase K2H has passed **and** a separate operational import task is authorized. This document contains no real member data.

K2H (2026-08-26) hardened parsers, invariants, privacy, reversal, and idempotency. It did **not** execute the real import.

The later workbook-compatibility and trusted-legacy-Member-bootstrap hotfix allows unmatched historical Cooperative people to be proposed as AB2TI Members at **commit**, without fake Membership Applications and without User accounts. Contact/profile gaps are warnings, not membership invalidation. Component-level readiness separates Member / CooperativeMembership / historical funds / PSN so unresolved shares do not erase historical membership. Real canonical import of the production workbook remains unauthorized until a separate operational task.

PSN participation and historical Simpanan Pokok / Simpanan Sukarela are recorded together. Historical fund rows are **sumber dana historis penyertaan**, not a live savings product, not withdrawable balances, and not a second current asset to add to PSN value. This is **not** online share purchase.

## Semantics operators must not confuse

Do **not** communicate:

Rp50m historical savings + Rp50m PSN participation = Rp100m member assets

when the source evidence indicates the Rp50m funds were converted into the Rp50m PSN participation.

Use language equivalent to: Historical source of participation / Legacy Cooperative fund record / Sumber dana historis penyertaan.

## 1. Source file preparation

1. Use CSV (UTF-8) or XLSX. Multi-sheet workbooks are accepted. After upload, inspect the worksheet list and **select one source sheet**. Extra sheets are not combined and are not imported automatically. Hidden sheets are listed but not imported unless chosen explicitly.
2. Keep recognizable headers: Nama, NIK, Email, Telepon/HP, Simpanan Pokok, Simpanan Sukarela, Total, No. Saham, Tanggal (spacing, line breaks, and `(Rp)` suffixes are normalized). The header need not be row 1; the importer scans the first 20 rows.
3. Required core: Nama plus at least one of Total, Simpanan Pokok, or No. Saham. If those cannot be identified on the selected sheet, that sheet is invalid — do not guess columns.
4. Do not pre-round money. Do not collapse date ranges such as `26-27/09/2023` into a single invented day.
5. Do not replace an AB2TI identifier (example pattern `AB2TI/08/I/0005`) with a guessed NIK.
6. Practical limits: 10 240 KB, 5 000 data rows, `.csv` / `.xlsx` only. Trailing formatted rows and summary footers (for example Laki-laki / Perempuan / Total) are ignored and must not be treated as members.

## 2. Backup requirement

Before any commit on a live database:

1. Take a verified database backup you can restore.
2. Record the application git revision.
3. Confirm you are **not** on a disposable local copy unless that is intentional.
4. Prefer a MySQL/MariaDB staging database (production engine). Isolated MySQL migrate and focused import tests passed in K2H; still take a backup before a real commit.

Do not commit an import without a backup.

## 3. Upload

Staff: **Koperasi → Import Data Lama**.

1. Upload the file. Storage is private (`storage/app/private/cooperative-imports`), with an unpredictable name. The original filename is metadata only.
2. The importer inspects worksheets first. If more than one compatible source sheet is visible, choose one worksheet before preview rows are staged. If exactly one visible compatible sheet exists, it is selected automatically; confirm the worksheet name before commit.
3. Parsing the selected sheet does **not** change canonical Cooperative/PSN tables. Preview copy states this.
4. Duplicate protection is worksheet-aware (file hash + worksheet name). The same workbook can later be authorized for a different sheet. The same sheet from the same file still warns and cannot be committed twice.
5. Unreadable or corrupt files, a workbook with no selectable source sheet, or a selected sheet with no recognizable required header become **failed** batches. Extra worksheets alone do not fail the workbook. Failed batches write no canonical rows.
6. Individual rows that need financial review (for example a bare share reference whose Total is not an exact multiple of the PSN unit value) do **not** fail the whole batch. The batch becomes **review_required**. Do not round, floor, or invent units.

## 4. Preview

Open the batch. Confirm:

- filename, file hash / duplicate warning, **selected worksheet**, visibility, detected header row
- ignored footer/summary row count
- rows total
- existing AB2TI Members matched / new AB2TI Members proposed from historical data
- identity review / financial review / share review
- ready / needs review / invalid
- proposed Cooperative memberships
- historical principal amount and historical voluntary amount (Ready rows only)
- proposed PSN participants, total units, explicit-unit count, inferred-unit count

Preview copy states that this is a migration of people who are already historical AB2TI Members, not new registration, and that **no User/login is created**. Proposed totals ignore needs-review rows. If the proposed totals are unexpected, **do not commit**.

## 5. Review exceptions

Use SOURCE vs NORMALIZED vs MATCHED MEMBER vs RECONCILIATION. Typical NEEDS REVIEW / INVALID reasons:

- no unique existing Member, incomplete contact for legacy bootstrap, or several candidates
- matching signals conflict (for example unique email vs a different unique name)
- name collision: a Member with this name exists; name alone never binds and never auto-creates
- identity column is not a 16-digit NIK and not a known AB2TI identifier
- NIK is present but **is not a match key** (Member has no `nik` field)
- malformed money or negative amounts
- uncertain dates
- Total ≠ Pokok + Sukarela
- explicit share units disagree with amount, or amount is not divisible by the PSN unit value (never round; preserve historical funds; do not create PSN participation until units are resolved)
- a bare share reference whose Total is not an exact PSN-unit multiple (NEEDS REVIEW, not batch failure)
- unknown/ambiguous share format
- Member already has imported PSN or fund openings
- source fingerprint already committed

Identity review and financial/share review are listed separately. A row may be identity-ready and still blocked from commit by share review. Do not auto-force these to Ready just to finish the file.

Blank pokok/sukarela with Total present: do not invent fund amounts. Participation may still be inferred when the division is exact.

## 6. Member matching and trusted legacy bootstrap

For the authorized Cooperative legacy worksheet, a numbered valid person row represents a historical AB2TI Member. Missing email, phone, or address is a **profile completeness warning**, not an identity failure and not `INVALID`.

Automatic match order against existing Members: AB2TI identifier / membership number, email, normalized phone. Name alone never binds. Source-internal duplicates (NIK in staging, AB2TI identifier, email, phone) become identity conflict review. NIK is never written to `Member`.

If no existing Member matches and the source row has a usable name without identity conflict: preview **proposes** a trusted legacy AB2TI Member. Contact fields may be null; placeholders are forbidden.

Component readiness is independent:

- `member` / `cooperative_membership` / `funds` / `psn`

Unresolved PSN units do **not** invalidate Member or CooperativeMembership readiness. Historical pokok/sukarela remain source facts when parseable even if PSN units are unresolved.

Commit may create Member → CooperativeMembership → historical funds without creating guessed PSN entries. A later PSN-resolution commit must not duplicate earlier components. Reversal offsets ledger components from the batch and must not delete Members that already have site access or independent records.

Provenance answers: batch, worksheet, source row, operator, time, resolution method. `members.source_type` is `cooperative_legacy_import` or `membership_application`.

Public `/koperasi` keeps `AB2TI_MEMBER_REGISTRY_COMPLETE=false` and `PSN_LEGACY_RECONCILIATION_COMPLETE=false` until operators explicitly mark those complete. Incomplete PSN reconciliation hides public PSN headline aggregates.

## 7. Reconciliation

For each ready row the importer already compared:

- source Total vs Pokok + Sukarela (only when fund fields were provided)
- explicit units in `No. Saham (n)` vs amount ÷ current PSN unit value
- inferred units only when the source did not state units and the division is exact (provenance: inferred)

If you disagree with an inference, keep the row in review.

## 8. Commit

Commit is a separate button with an explicit confirmation. Default mode is **append** (`Tambahkan ke data yang ada`).

Optional destructive mode: checkbox **Hapus data import lama sebelum mengimpor** (`replace_legacy`). Defaults to unchecked and is never remembered from a prior import. Replace mode also requires a second acknowledgement that prior legacy-import data will be replaced.

Replace mode cleans only provenance-scoped Cooperative legacy-import records (legacy fund/PSN openings, eligible migration-created Members without independent dependencies, related memberships when safe). It does **not** truncate tables, delete Users, MembershipApplications, or non-legacy Members. Prior batches are marked `replaced` with `replaced_by_batch_id` audit links.

In replace mode, source-internal suspected duplicates become warnings (`possible_duplicate_nik`, `shared_email`, …) and do **not** suppress source rows. Unresolved PSN units still stay pending — replace never fabricates shares.

Commit processes rows that still have **Ready** uncommitted components. Component timestamps record what committed. A later PSN-resolution commit must not duplicate earlier components.

## 9. Authorized production import record (2026-08-27)

Controlled canonical import executed on target database `ab2ti` (MySQL).

| Field | Value |
|-------|-------|
| Import date | 2026-08-27 (UTC+7) |
| Batch ID | 10 |
| Worksheet | A.Anggota OK |
| Import mode | replace_legacy |
| Source rows | 139 |
| Header row | 4 |
| Ignored footer rows | 54 |
| Members created | 139 |
| Cooperative memberships | 139 |
| Principal historical total | Rp 139.000.000 |
| Voluntary historical total | Rp 438.930.000 |
| PSN resolved participants | 118 |
| PSN resolved units | 554 |
| PSN review rows | 21 |
| Profile warning rows | 85 |
| Duplicate warning rows | 40 |
| Batch status | completed_with_review |
| Prior legacy removed | 98 Members, 98 memberships, 135 fund entries, 87 PSN entries |
| Replaced batch | 8 → 10 |
| Users created | 0 |
| MembershipApplications created | 0 |
| Backup | `storage/app/private/backups/ab2ti_pre_replace_legacy_20260827T001712Z.sql.gz` |

No raw NIK, email, phone, or address recorded in this runbook entry.

## 10. Verification

Staff: **Koperasi → PSN** and **Anggota Koperasi**.

Check:

- participant count (active Cooperative members with current units > 0)
- total current units and derived value (historical funds **not** added)
- a sample member’s history, share reference, batch/row provenance
- public `/koperasi` aggregates (names, NIK, balances, and staging must **not** appear)

Have the member open Lapak Saya (workspace) and confirm only their own figures, labeled as Data Historis Sumber Penyertaan — not saldo tabungan.

## 10. Failed / leftover rows

Invalid and needs-review rows are left behind. Fix source data, rematch, or document why they will not be imported. Do not invent values.

If a single row fails at commit, that row is returned to review; other ready rows may have committed. Re-read the batch summary.

## 11. Duplicate import protection

- Same file bytes **and** the same worksheet: warning on upload/select; second commit of an already-committed identical worksheet is rejected.
- A later authorized import of a **different** worksheet from the same workbook is not blocked solely by file hash.
- Same source row identity (fingerprint) already committed: needs review, then commit blocked.
- Same Member already holding an imported PSN opening or fund opening: review, not silent duplicate.
- Committed staging rows cannot be committed twice.

## 12. Post-import totals

Compare staff PSN totals with the source spreadsheet’s intended unit total. Public `/koperasi` shows aggregates for **active** Cooperative members with current units > 0. Total AB2TI Member count and Cooperative participation percentage stay **hidden** until `AB2TI_MEMBER_REGISTRY_COMPLETE=true` (config `cooperative.ab2ti_member_registry_complete`). Importing the Cooperative workbook does **not** complete the organizational Member registry. Do not publish a false 100% participation ratio. Do not publish total financial investment unless separately approved.

## 13. Recovery / reversal

There is no generic financial rollback.

If the batch is fully committed **and** nobody has added later independent PSN ledger rows on those participations, staff with `cooperative.manage` may use **Reverse unmodified batch**:

- PSN openings are offset with adjustment entries linked by `reverses_entry_id` (not deleted)
- fund openings are marked reversed (not deleted)
- staging rows remain committed (they will not import again)
- reversal cannot be performed twice

If later independent ledger activity exists, automatic reversal is **blocked**. Restore from the pre-commit backup, or add a documented staff adjustment after business review.

Never DELETE participation or fund history by hand.

## 14. Private data

Do not download or copy source spreadsheets into public folders. Staging JSON is encrypted at rest and must not be pasted into tickets or logs. Do not echo full NIK/address in notes.

## 15. Real import prerequisites

1. K2H passed (this runbook).
2. Isolated MySQL/MariaDB rehearsal recommended before production engine commit (K2H already ran focused tests on an isolated schema).
3. Verified backup.
4. Explicit operational authorization to import the real Cooperative spreadsheet.
5. Operator confirmation of preview Ready totals against the intended unit count.

Until that authorization exists, use only sanitized fixtures. A real-workbook **dry-run** (upload → select worksheet → parse → preview) is allowed after this hotfix; it must **not** canonical-commit Members or Cooperative data.

## 16. Post-import reconciliation

After a successful import with review warnings (`completed_with_review`), operators reconcile imported data through **Koperasi → Import Data Lama → Batch → Perlu Validasi**. This is quality control after migration — not a re-import and not automatic Member merge.

### What reconciliation covers

| Category | Meaning | Operator actions |
|----------|---------|------------------|
| Profil belum lengkap | Missing or unparseable contact fields | Update Member via domain action, or accept as incomplete |
| Email / telepon digunakan bersama | Shared contact across source rows | Confirm distinct, accept shared contact, or requires follow-up — **no merge** |
| Dugaan NIK ganda | Same source NIK on multiple rows (staging only) | Confirm distinct, mark possible same person, or follow-up — **no merge/delete** |
| PSN belum terkonfirmasi | Share count could not be inferred safely | Manual unit resolution, confirm no participation, or follow-up |
| Data lain perlu diperiksa | Genuinely unrelated legacy warnings (e.g. malformed date) that do **not** belong to profile, shared contact, duplicate identity, or PSN | Source verified or follow-up |

PSN arithmetic codes (`amount_not_divisible_by_unit_value`, `non_integral_legacy_total`, and other share codes) are classified as **PSN belum terkonfirmasi only**. They must not appear again under “Data lain perlu diperiksa”. A single PSN source row may carry multiple reason codes but is reconciled once through the PSN workflow.

Dashboard shows **unique rows with review** separately from **total review flags** because warnings overlap on the same source row.

### Profile warnings

Missing email/phone are not membership defects. Operators may enter a known value or mark `accepted_as_is`. Do not fabricate placeholders.

### Shared contact and duplicate identity

Shared email/phone is a warning, not proof of duplicate identity. Even `confirmed_same_person` records the finding only — it does **not** merge or delete canonical Member records in this phase.

### PSN manual resolution

For unresolved PSN rows, authorized staff provide integer unit count (and optional share reference correction). Resolution creates only the missing PSN `ParticipationEntry` with `units_origin = manual_reconciliation`. Historical fund records remain independent provenance. Second resolution on the same row is blocked (idempotent). Zero-unit disposition uses `confirmed_no_psn_participation` without inventing ledger rows.

### Audit

Each decision records batch, import row, category, operator, timestamp, outcome, and optional note. Original importer warnings are preserved; resolution appends audit actions rather than deleting detection history.

### Completeness gates (not auto-flipped)

- `PSN_LEGACY_RECONCILIATION_COMPLETE` becomes true only when every PSN-relevant source row has canonical participation **or** explicit no-participation disposition. Review clicks alone do not flip config.
- `AB2TI_MEMBER_REGISTRY_COMPLETE` is unaffected by batch reconciliation; org-wide Member totals stay hidden.
- Public PSN headline totals stay hidden while PSN legacy reconciliation is incomplete.

See also: `docs/implementation/cooperative-post-import-reconciliation.md`.

## 17. Cooperative admin operations (Phase K3)

Staff workspace: **Koperasi → Dashboard** (`/admin/koperasi`).

| Area | Purpose |
|------|---------|
| Dashboard | Resolved ledger metrics, attention queue, quick links, recent activity |
| Anggota Koperasi | Search/filter; mobile cards; open unified detail |
| Kelola Saham PSN | Desired-total edit → review → confirm; writes ledger adjustment only |
| Perlu Validasi | Post-import reconciliation queues |
| Import Data Lama | Legacy spreadsheet import |

PSN correction rules:

1. Never overwrite current share totals or opening entries.
2. Reason required (`correction_from_document`, `administrative_correction`, …).
3. Two-step confirmation before commit. Stale confirmations are rejected; the same confirmation token cannot write a second ledger row.
4. Wrong correction → another adjustment, not deletion (`correction_of` in provenance). Ordinary adjustment is blocked for unresolved PSN and inactive membership.
5. No per-edit SQL backup; rely on immutable ledger + provenance + compensating corrections + routine DB backups.

See: `docs/implementation/phase-k3-cooperative-admin-operations.md`, `docs/implementation/phase-k3h-cooperative-admin-hardening.md`, and `docs/operations/cooperative-admin-psn-adjustments.md`.
