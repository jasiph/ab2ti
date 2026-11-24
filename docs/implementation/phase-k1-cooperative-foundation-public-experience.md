# Phase K1 — Cooperative foundation and public experience

Implemented 2026-08-24. Cooperative membership is now required product scope before the final production RC. This document does not rewrite historical Phase 4 / 5A / 5B records. Tag `ab2ti-2.0-rc` remains a historical pre-Cooperative baseline and must not be moved.

## Business boundary

Koperasi Pusat AB2TI already exists operationally. AB2TI membership and Cooperative membership are separate. A person must already be an AB2TI Member. Not every AB2TI Member belongs to the Cooperative. K1 does not implement savings, loans, or PSN share accounting.

```text
Member 1 — 0..1 CooperativeMembership
```

There is no standalone Cooperative person or User. Name, email, phone, address, and credentials stay on Member/User.

## Schema

Table `cooperative_memberships` (additive):

- `id`
- `member_id` (FK to `members`, unique, restrict on delete)
- `status` (`active` | `inactive`)
- `joined_at` nullable
- `ended_at` nullable
- `notes` nullable (staff-only)
- timestamps
- index `(status, joined_at)`

No official Cooperative membership number is stored: none was supplied as source data.

Publications gain `is_cooperative_update` (boolean, default false), applicable across publication types.

## Statuses and Badge Koperasi

Active membership means the person currently belongs to Koperasi Pusat AB2TI. Inactive means the Cooperative relationship ended or is not currently in force. Badge Koperasi is derived from `Member::isActiveCooperativeMember()` (active Cooperative row), never from a duplicated boolean on `members`.

## ACL

Canonical two-segment names:

- `cooperative.view`
- `cooperative.manage`

`membership-admin` receives both. `editor` does not. Super-admin remains `Gate::before`. Admin nav hiding is UX only; policies and `permission:` middleware are authoritative. Do not use `acl:sync --clean` for ordinary verification.

## Admin workflow

Staff IA: **Koperasi → Anggota Koperasi**.

Staff with `cooperative.manage` may search existing AB2TI Members who do not already have a Cooperative row, establish membership, edit metadata, and activate/deactivate. Creating a Cooperative row never creates a Member or User. Duplicate `member_id` is rejected by validation and by the unique index.

## Public landing

`GET /koperasi` (`cooperative.index`) is a dedicated domain page. It remains available even if no CMS page exists. `koperasi` is a reserved CMS first segment.

Composition:

1. Hero and accurate join CTAs (learn membership / contact the board — no fake enrolment)
2. Live aggregates from `CooperativePublicStats`
3. About copy (i18n sources, official name untranslated)
4. PSN rice-milling introduction without fabricated share totals
5. Latest Cooperative publications via `Publication::published()->cooperativeUpdates()`
6. Current vs planned benefits (savings/loan called planned, not live)

## Statistics and privacy

Public props are aggregates only:

- `total_ab2ti_members` — count of `members` (canonical AB2TI member records)
- `active_cooperative_members` — Cooperative rows with status `active`
- `participation_percentage` — active Cooperative / AB2TI members × 100, 0 when the denominator is 0

PSN totals are omitted until K2 has an authoritative ledger. Public Inertia must not include names, notes, contacts, holdings, or membership history.

## Publishing

`is_cooperative_update` is an optional marker on any publication type. Drafts remain unpublished. Public cards reuse `PublicationViews::publicCard` and existing publication URLs.

## i18n, SEO, sitemap

Indonesian/English source files under `lang/*/sources/cooperative.json` plus nav/landing/publishing keys. Official institution name **Koperasi Pusat AB2TI** stays Indonesian. `/koperasi` uses `PublicMetadataResolver::forPublicInformationalPage` and is listed in the public sitemap. Admin Cooperative URLs are not.

## Member workspace

Authenticated linked members may see a read-only Cooperative status on Lapak Empty/Overview. They cannot change Cooperative membership.

## Tests

See `tests/Feature/Cooperative/*` for domain uniqueness, admin ACL, badge derivation, public aggregates/privacy, publishing eligibility, sitemap, and workspace read-only behaviour.

## K2 handoff

K2 can extend `CooperativePublicStats` with PSN aggregates and add a participation ledger keyed to `cooperative_memberships.id` / `member_id` without redesigning K1 membership.

## Deferred / out of K1

Savings, loans, installments, PSN holdings ledger, online share purchase, member self-enrolment, second membership card, Cooperative User accounts, SHU/accounting.
