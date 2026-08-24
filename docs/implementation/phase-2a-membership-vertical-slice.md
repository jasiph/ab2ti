# AB2TI 2.0 — Phase 2A Membership Vertical Slice

**Status:** Implemented  
**Date:** 2026-08-20  
**Does not reopen:** Phase 1A–1G, Pre-Phase 2A, Pre-Phase 2A-B

This is the first AB2TI business-domain vertical slice: public Membership Application → staff review → approve/reject → exactly one AB2TI Member.

---

## 1. Files / schema introduced

### Schema

| Table | Role |
|---|---|
| `membership_applications` | Applicant packet + lifecycle + decision metadata |
| `membership_application_transitions` | Append-only status history (Phase 1F audit) |
| `membership_documents` | Private document metadata (bytes on private disk) |
| `members` | AB2TI Member domain record created only by Approve |

Migrations:

- `database/migrations/2026_08_20_140000_create_membership_applications_table.php`
- `database/migrations/2026_08_20_140001_create_membership_application_transitions_table.php`
- `database/migrations/2026_08_20_140002_create_membership_documents_table.php`
- `database/migrations/2026_08_20_140003_create_members_table.php`

### Principal application files

- Models: `MembershipApplication`, `MembershipApplicationTransition`, `MembershipDocument`, `Member`
- Enums: `MembershipApplicationStatus`, `MembershipDocumentType`, `MemberStatus`
- Actions: `SubmitMembershipApplication`, `StartMembershipApplicationReview`, `ApproveMembershipApplication`, `RejectMembershipApplication`, `UpdateMemberCardIssued`
- FormRequests under `app/Http/Requests/Membership/`
- Policies: `MembershipApplicationPolicy`, `MemberPolicy`, `MembershipDocumentPolicy`
- Controllers: `Membership\PublicMembershipController`, `Admin\MembershipApplicationController`, `Admin\MemberController`, `Admin\MembershipDocumentController`
- Vue: `Pages/Frontend/Membership/*`, `Pages/Admin/Membership/**`
- Config: `config/membership.php`; ACL group in `config/acl.php`; reserved CMS segments; disk `membership_documents`

---

## 2. Membership lifecycle actually implemented

Status lives on **Membership Application**, not on Member.

```
(public submit) → submitted
submitted → under_review          (staff start review)
under_review → approved           (staff approve → creates Member)
under_review → rejected           (staff reject → no Member)
approved / rejected → (none)
```

The enum **also stores** `revision_requested` because Phase 1F defines it. Phase 2A **does not** implement request-revision, applicant resubmit, or Secure Capability Grants. A staff POST cannot move an application into `revision_requested`.

Member V1 status is `active` only. No deactivate.

---

## 3. Authorization mapping

Canonical names (`<domain>.<action>`, exactly one dot):

| Permission | Use |
|---|---|
| `membership-applications.view` | Admin list/detail |
| `membership-applications.review` | Start review / notes |
| `membership-applications.approve` | Approve (creates Member) |
| `membership-applications.reject` | Reject |
| `membership-members.view` | Member list/detail |
| `membership-members.update` | Card-issued flag |
| `membership-documents.view` | Download private bytes |
| `membership-documents.manage` | Seeded; **no staff upload UI in 2A** |

Public submit is not a Spatie permission.

Role `membership-admin` is seeded as the bundle of the eight capabilities. `editor` and `platform-admin` are **not** seeded (Phase 1G still “ADD LATER”). Tests prove an `editor` role with CMS/media permissions cannot administer Membership. `super-admin` retains `Gate::before` bypass. No catch-all `admin` role.

UI `can()` / `available_actions` are presentation only.

---

## 4. Public / admin routes

| Method | Path | Name |
|---|---|---|
| GET | `/keanggotaan` | `membership.index` |
| GET | `/keanggotaan/daftar` | `membership.apply` |
| POST | `/keanggotaan/daftar` | `membership.apply.store` (throttle `membership-intake`) |
| GET | `/keanggotaan/daftar/terkirim` | `membership.apply.submitted` |
| GET | `/admin/membership/applications` | `admin.membership.applications.index` |
| GET | `/admin/membership/applications/{application}` | `admin.membership.applications.show` |
| POST | `.../review` `.../approve` `.../reject` | corresponding names |
| GET | `/admin/membership/members` | `admin.membership.members.index` |
| GET | `/admin/membership/members/{member}` | `admin.membership.members.show` |
| PATCH | `/admin/membership/members/{member}` | `admin.membership.members.update` |
| GET | `/admin/membership/documents/{document}` | `admin.membership.documents.show` |

CMS catch-all cannot claim `keanggotaan` or `anggota`.

---

## 5. Approval transaction

`ApproveMembershipApplication`:

1. `lockForUpdate` on the application row
2. If already `approved`, return the existing Member (idempotent)
3. Else require `under_review`
4. If a Member already exists for `source_application_id`, mark approved and return it
5. Else insert Member + set application `approved` + write transition in the same transaction
6. Unique `members.source_application_id` is the duplicate barrier; unique-constraint races reload the existing Member

The Action does not create a `User`, assign Spatie `member`, infer User↔Member from email, or touch Koperasi.

---

## 6. Privacy / document boundary

- Document types are the Phase 1F set only: `applicant_photo`, `identity_document`, `payment_proof`
- Bytes live on private disk `membership_documents`, not File Manager / Spatie `media`
- Download requires `membership-documents.view`; `media.view` is insufficient
- Inertia document props: id, type, original filename, mime, size, uploaded_at — **not** `disk` / `object_key`
- Confirmation page stores `reference_code` in session; it is not a public status lookup
- Transactional pages (`/keanggotaan/daftar`, confirmation, admin) pass `PublicMetadataResolver::forTransactionalPage` (`indexable: false`, generic title/description, default OG, no PII)
- Informational `/keanggotaan` uses `forPublicInformationalPage` (`indexable: true`, no PII)

---

## 7. UI / component reuse

**Reused:** `FrontendLayout`, `AppLayout`, `BaseButton`, `BaseInput`, `BaseSelect`, `BaseCard`, `BaseList`, `BasePagination`, `ConfirmDialog` / `useConfirm`

**Generic primitives added because Phase 2A needed them (HCI contract: add when first domain needs them):**

- `BaseTextarea` — labeled textarea with accessible errors
- `BaseStatusBadge` — tone + text (color is never the only status carrier)

**Feature:** `MembershipStatusBadge` maps domain status onto `BaseStatusBadge`

Public form is a full page (not a modal). Approve/reject use the existing confirm dialog. **Pre-Phase deviation remains:** `BaseModal` has no complete focus trap.

Admin queues use `BaseList` (stacked on small screens), not a dense table.

---

## 8. Tests

- `tests/Feature/Membership/PublicMembershipApplicationTest.php`
- `tests/Feature/Membership/MembershipAuthorizationTest.php`
- `tests/Feature/Membership/MembershipWorkflowTest.php`
- `tests/Feature/Membership/MembershipPrivacyTest.php`
- `tests/Feature/Membership/MembershipAclNamingTest.php`
- `tests/Unit/Membership/MembershipApplicationStatusTest.php`

---

## 9. Deviations

1. **Pre-Phase 2A-B:** `BaseModal` still has no complete focus trap. Membership uses it only via `ConfirmDialog` for irreversible approve/reject. The application form itself is a full page.
2. **`revision_requested` exists in the status enum** (Phase 1F) but has no staff/applicant action in Phase 2A.
3. **`membership-documents.manage` is registered** but staff attach/replace UI is not built. Applicant optional upload + authorized staff download establish the private-document boundary.
4. **`membership_number` format** is a technical unique string (`MBR-YYYYMMDD-…`), not a product-approved card number.
5. **Reserved first segment `keanggotaan`** would make shared `forRequest()` treat the family as private; informational landing **overrides** with explicit indexable metadata.
6. **Base `Controller` now uses `AuthorizesRequests`** so Membership Policies can call `$this->authorize()`. Other admin modules still rely on route `permission:` middleware.
7. **Tests call `withoutVite()`** in `tests/TestCase` so Inertia feature tests do not depend on `public/build/manifest.json`. Production `npx vite build` remains required for real browsers.

---

## 10. Unresolved business questions

These were not invented:

1. Full applicant field set beyond Phase 1F minimum (legal name, email, phone, address, optional notes).
2. Whether any of the three document types is **mandatory** on submit (2A treats them as optional).
3. Product-approved `membership_number` display format.
4. Applicant revision / Secure Capability Grant flow.
5. Whether rejection reason is required.
6. Notifications.
7. Member portal / User linkage / Koperasi.
8. Whether `editor` / `platform-admin` should be seeded now (1G: ADD LATER). 2A seeds only `membership-admin` as a capability bundle, with no default user.

No genuine Phase 1 contradiction blocked implementation.
