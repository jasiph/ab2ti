# Hotfix — Cooperative workbook compatibility and trusted legacy Member bootstrap

Implemented 2026-08-26. Phases K1, K2, and K2H remain closed. Tag `ab2ti-2.0-rc` (`0037d7ce7451b57d805afa04a2ee064e8e326fb5`) is not moved. This hotfix does **not** canonical-commit the real Cooperative workbook.

## Why

AB2TI Member records are not yet populated in the new database, but every person in the current Cooperative workbook is already a legitimate historical AB2TI Member. Treating unmatched rows as identity errors blocked a valid migration. The real workbook also has multiple worksheets and a header on row 4.

## Member creation contract

A Member may only be created through:

1. an approved Membership Application (`CreateAb2tiMemberFromApplication`), or
2. an authorized auditable Cooperative legacy import commit (`BootstrapLegacyMember`).

There is no public registration, no generic admin Create Member, no fabricated `MembershipApplication`, and no automatic User/login.

## Behaviour

- Inspect worksheets after upload; operator selects one source sheet (current intended sheet: `A.Anggota OK`). Hidden sheets are listed, never combined, never imported implicitly.
- Header detection scans the first 20 rows. Footer labels (Laki-laki / Perempuan / Total) and trailing formatted rows are not staged as members.
- Trusted source membership: a numbered valid person row may propose legacy Member create with only a usable name. Missing email/phone/address are profile warnings, not identity failures. Placeholders are forbidden; Member contact columns are nullable for migration.
- Staging resolution: `existing` | `proposed_legacy_create` | `manual` | `ambiguous` | `invalid`. Name-only never auto-binds. Source-internal duplicates (NIK staging / AB2TI identifier / email / phone) become identity conflict review. NIK is never stored on Member.
- Component readiness: `member`, `cooperative_membership`, `funds`, `psn`. Unresolved PSN does not invalidate Member/CooperativeMembership/funds readiness.
- Preview writes `ImportBatch` / `ImportRows` only. Commit may create Member → CooperativeMembership → historical funds without guessing PSN. Later PSN commit is idempotent. Component timestamps track what committed.
- Reversal offsets batch ledger components; blocks when migration-created Members already have site access/independent records. Members are not deleted by default.
- Public flags: `AB2TI_MEMBER_REGISTRY_COMPLETE` and `PSN_LEGACY_RECONCILIATION_COMPLETE` default false. Incomplete PSN reconciliation hides public PSN headline aggregates.

## Schema

Additive migrations:

- `2026_08_26_170000_allow_legacy_member_bootstrap.php`
- `2026_08_26_180000_allow_legacy_member_profile_gaps.php`

## Authorization

Staff only, `cooperative.manage` for upload/select/commit. `BootstrapLegacyMember` is not routed publicly.

Operator runbook: `docs/operations/cooperative-legacy-import.md`.
