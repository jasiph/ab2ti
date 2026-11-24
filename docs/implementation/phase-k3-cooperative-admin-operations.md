# Phase K3 — Cooperative admin operations & safe PSN management

Implemented: 2026-08-27

Prior phases closed: K1, K2, K2H, controlled legacy import, post-import reconciliation support.

Tag `ab2ti-2.0-rc` remains a historical baseline and must not move.

## Scope

Staff operational workspace at `/admin/koperasi` for Cooperative officers:

- Dashboard with resolved ledger metrics and attention queue
- Searchable/filterable Cooperative member list (mobile cards via existing BaseList)
- Unified Member detail: membership, PSN position/history, historical fund provenance, review badges
- Safe PSN share adjustment via desired-total UX → signed `ParticipationEntry` adjustment
- Two-step confirmation and mobile-safe dedicated edit page

## Non-goals

Member merge, `Member.nik`, loans/savings, SHU, GL, online purchase, separate Cooperative User accounts, final RC, production deploy.

## Domain action

`App\Actions\Cooperative\AdjustPsnParticipation`

- Input: desired units, effective date, reason (`PsnAdjustmentReason`), optional note
- Computes delta = desired − current (`SUM(entries.units)`)
- Rejects no-op and resulting total &lt; 0
- Writes `entry_type = adjustment`, `source_type = staff`, provenance with before/after/operator
- Never mutates opening or prior entries

## ACL

Existing `cooperative.view` / `cooperative.manage` only.

- View: dashboard, lists, detail, PSN edit page (read)
- Manage: membership write + PSN adjustment POST

## Routes

| Method | Path | Name |
|--------|------|------|
| GET | `/admin/koperasi` | `admin.cooperative.dashboard` |
| GET | `/admin/koperasi/anggota/{membership}/psn/kelola` | `admin.cooperative.memberships.psn.edit` |
| POST | `/admin/koperasi/anggota/{membership}/psn/adjust` | `admin.cooperative.memberships.psn.adjust` |

## Metrics labeling

While `PSN_LEGACY_RECONCILIATION_COMPLETE=false`, dashboard labels resolved ledger figures as non-final organization totals.

While `AB2TI_MEMBER_REGISTRY_COMPLETE=false`, org-wide participation ratio remains hidden.

## Correction philosophy

Wrong adjustment → another auditable adjustment. No history deletion. No per-edit SQL backup.

## Tests

`tests/Feature/Cooperative/CooperativeAdminOperationsK3Test.php`
