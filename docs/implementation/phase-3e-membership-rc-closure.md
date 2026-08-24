# AB2TI 2.0 — Phase 3E Membership Operational Closure for RC

**Status:** Implemented  
**Date:** 2026-08-22  
**Does not start:** Kartu Anggota, QR, Member Portal, User↔Member linkage, Koperasi, finance, legacy mass import, expanded Member statuses.

Phase 3E closes the Membership domain at the **minimum operational state** required for AB2TI 2.0 Release Candidate:

```
public applicant → application → staff review / revision → approval → AB2TI Member → staff-readable / maintainable Member record
```

It does **not** mean every future Membership feature is implemented.

---

## 1. Inherited post-approval Member state

Phase 2A already shipped `/admin/membership/members` list + show and a PATCH that only toggled `card_issued`. Phase 3A locked Member creation to `ApproveMembershipApplication` → `CreateAb2tiMemberFromApplication`. Phase 3B/3C completed intake and review. Phase 3D-A documented Kartu Anggota; **implementation is deferred**.

Staff could already list and open Members and see the source application. Gaps for RC:

- Member vs Application mixed on one detail card
- `membership_number` presented like a product card number
- Card checkbox implied an in-app Kartu Anggota workflow
- `membership-members.update` existed but did not correct contact data
- List serialized `card_issued` without using it

---

## 2. RC operational requirements

Authorized staff can:

- list approved Members
- open a Member as association identity
- see source application provenance (link only with `membership-applications.view`)
- correct contact fields when they have `membership-members.update`

They cannot: issue cards, open a Member Portal, create Members except via approval, or browse a public directory.

---

## 3. Member registry

Hardened existing Index, not rebuilt.

Shows: legal name, email (operational contact), technical `membership_number`, status, `recognized_at`. Does **not** list phone, address, documents, or `card_issued`. Search: name / email / record identifier. Ordered by `recognized_at` desc. Intro copy states this is not the application queue and not Kartu Anggota.

---

## 4. Member detail

Three blocks:

1. **Member data** — record identifier, recognized_at, recording staff (immutable provenance)
2. **Contact** — name, email, phone, address (editable with update permission)
3. **Source membership application** — reference code + application status; open-application button only if staff can view applications

Documents stay on the Application authorization path. No card controls.

---

## 5. Application → Member provenance

`source_application_id` remains unique NOT NULL. Contact edits do not change it, `recognized_at`, `created_by`, `status`, or `membership_number`. Application and Member remain separate rows.

---

## 6. `membership_number` semantics

Still the schema unique string from `MembershipNumberGenerator` (`MBR-YYYYMMDD-…`). Staff UI labels it **record identifier** and states it is **not** the approved Kartu Anggota number. Values were not migrated. Product numbering remains the Phase 3D-A **REQUIRES BUSINESS DECISION**.

---

## 7. Member update behavior

New Action `UpdateMemberContact`. FormRequest validates the four contact fields only. Extra request keys (`membership_number`, `status`, `card_issued`, `source_application_id`) are ignored. `card_issued` was removed from `$fillable`.

`UpdateMemberCardIssued` remains in code for schema compatibility and is **not** called from HTTP.

View-only staff cannot PATCH.

---

## 8. Authorization

Unchanged names: `membership-members.view` / `membership-members.update`. `editor` / `platform-admin` still do not receive Membership. No public Member permissions. Member identity ≠ staff User.

---

## 9. Privacy / security

Admin Member pages are noindex transactional surfaces. No public `/anggota` directory. List omits phone/address. Member Inertia payload omits document storage and card flags. Approval still creates no User and no Spatie `member` role.

---

## 10. Card deferral

Kartu Anggota, QR, issuance, portrait-for-card, PDF, and physical-card migration were **not** implemented. The `card_issued` column is unchanged. The staff checkbox was removed so RC does not present a fake card workflow. Phase 3D-A remains the future contract.

---

## 11. Member Portal deferral

No Member login, dashboard, self-service, or User↔Member link.

---

## 12. Legacy migration deferral

No invented records. `source_application_id` stays required. Physical-card process stays outside the app. Import of historical members is post-RC.

---

## 13. Tests

Added `MembershipMemberAdministrationTest`. Extended privacy noindex to Member detail. Domain foundation mass-assignment now also guards `card_issued`. Phase 3A–3C tests preserved.

---

## 14. Remaining post-RC Membership backlog

- Official membership-number format and Kartu Anggota (Phase 3D-A)
- Member Portal / User linkage
- Member statuses beyond `active`
- Manual / imported Members without an Application
- Notifications
- Mandatory intake documents / extra applicant fields
- editor/platform-admin Membership access
- Dues / Rp 50.000 / payments

---

## 15. Phase 3 closure decision

**PHASE 3 MEMBERSHIP: CLOSED FOR RC**

The association membership slice required for the first Release Candidate is operational. Future Membership products (cards, portal, lifecycle expansion, legacy import) are out of this closure.

---

## 16. Inputs for Phase 4 public-site work

Phase 4 may proceed on the main public site / landing without waiting on Kartu Anggota.

Membership public IA already exists (`/keanggotaan` + intake). Phase 4 should compose public nav/homepage with that family as-is. Do not turn the landing into a Member directory or card verifier. Do not reopen Membership approval or Secure Capability.
