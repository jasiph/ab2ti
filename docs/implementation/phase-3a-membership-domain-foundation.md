# AB2TI 2.0 — Phase 3A Membership Domain Foundation

**Status:** Implemented  
**Date:** 2026-08-21  
**Does not start:** Phase 3B public form, Phase 3C admin review UI, Phase 3D card/numbering, Member Portal, Koperasi, Community.

Phase 3A establishes (and hardens) the canonical Membership **backend/domain** foundation. It does not reopen Phase 1 or Phase 2. Public and admin Membership HTTP/UI already shipped in Phase 2A; this phase does not rebuild, expand, or remove them.

---

## 1. Previous state

Phase 2A already implemented the Membership vertical slice against Phase 1C/1E/1F/1G:

| Area | What existed |
|---|---|
| Tables | `membership_applications`, `membership_application_transitions`, `membership_documents`, `members` |
| Models | `MembershipApplication`, `MembershipApplicationTransition`, `MembershipDocument`, `Member` |
| Lifecycle | Enum `MembershipApplicationStatus` with Phase 1F transitions; Actions for submit / start review / approve / reject |
| Identity | User is Fortify/Jetstream only; approval does not create a User or Spatie `member` role |
| HTTP/UI | Public `/keanggotaan` intake and admin application/member screens (Phase 2A, **not** Phase 3A work) |
| ACL | Canonical two-segment names in `config/acl.php` |
| Gaps vs 3A brief | Status checks were duplicated inside Actions instead of one lifecycle boundary; Member identity columns were mass-assignable; no documented Secure Capability purpose hook in Membership config |

Church/Presbi remnants are not part of this domain (ADR 0005 / Phase 2C.1). No leftover `members` collision in canonical migrations.

Phase 3A **did not** treat the 2A slice as disposable. Where 2A already matched Phase 1, it was confirmed. Where the domain boundary was weaker than the 3A contract, it was tightened.

---

## 2. Canonical domain model

AB2TI Member is the Eloquent model `Member` (table `members`), matching Phase 1F naming. There is no second `Ab2tiMember` type.

```
User / Account          authentication identity (platform)
Membership Applicant    party attributes on the Application (no Applicant table)
MembershipApplication   intake/process aggregate
Member                  association membership identity (AB2TI Member)
```

Applicant-writable fields (Phase 1F minimum, unchanged): `legal_name`, `email`, `phone`, `address`, optional `applicant_notes`, optional documents.

Staff-writable: `staff_notes`, `rejection_reason`, `revision_reason` (column reserved; no request-revision Action in 3A).

System-written: `reference_code`, `status`, lifecycle timestamps and actor FKs.

**Not modeled:** NIK/KTP columns, religion, congregation, gender, birth date, region, member class, dues, cooperative shares, User FK on Member.

---

## 3. Schema

No new migrations in Phase 3A. Canonical tables remain the Phase 2A set.

### `membership_applications`

- PK `id`; unique opaque `reference_code` (support identifier, not authorization)
- Applicant snapshot columns; `status`; lifecycle timestamps; `reviewed_by` / `approved_by` / `rejected_by` → `users` nullOnDelete
- Indexes: `status`, `submitted_at`, `email` (non-unique — reapply is a new Application)
- No `user_id`, no durable edit token, no NIK

### `membership_application_transitions`

- Append-only history; FK to application `restrictOnDelete`
- `from_status` nullable (first submit); `to_status`; optional `actor_user_id`; `actor_context`; optional `reason`; `created_at`
- MySQL portability: explicit constraint/index names (`mat_application_fk`, `mat_actor_user_fk`, `mat_application_created_idx`). Laravel’s auto `{table}_{column}_foreign` / composite `_index` names exceed MySQL’s 64-character identifier limit. Semantics unchanged. PHPUnit uses in-memory SQLite and does not catch this.

### `membership_documents`

- Metadata for private-disk bytes (`membership_documents` disk)
- Types: `applicant_photo`, `identity_document`, `payment_proof`
- No `member_id` (documents stay on the source Application)
- Composite index name `md_application_type_idx` (auto name exceeds MySQL 64 characters)

### `members`

- Unique `membership_number` (technical uniqueness key, **not** a product card format)
- Unique NOT NULL `source_application_id`
- `status` = `active` only; contact snapshot; `recognized_at`; `card_issued` / `card_issued_at`; `created_by`
- Index on `email` (contact search, not uniqueness, not User linkage)
- No `user_id`; no Koperasi columns

---

## 4. Relationships

| From | To | Cardinality | Rule |
|---|---|---|---|
| Application | Documents | 1:n | Current row = `replaced_at` null |
| Application | Member | 1:0..1 | Unique `source_application_id` |
| Application | Transitions | 1:n | Append-only |
| Member | User | none | Reserved by Phase 1F; not created |
| User | Application / Member | none as identity | Staff actor FKs only (`reviewed_by`, `approved_by`, `rejected_by`, `created_by`) |

Historical duplication of contact fields onto Member at approval is **intentional** (Phase 1F snapshot). Application remains the historical packet including documents and staff notes.

---

## 5. State / lifecycle model

Status lives on **Membership Application**, not on Member. Member V1 status is `active` only.

```
(public submit) → submitted
submitted → under_review                staff StartMembershipApplicationReview
under_review → revision_requested       reserved (no Action in 3A)
under_review → rejected                 staff RejectMembershipApplication
under_review → approved                 staff ApproveMembershipApplication → creates Member
revision_requested → under_review       reserved for future applicant resubmit (not staff start-review)
approved / rejected → (none)
```

Enforcement:

- `MembershipApplicationStatus::allowedTransitions()` / `canTransitionTo()`
- `MembershipApplicationLifecycle` is the only writer of status + transition rows for initialize and staff transitions
- Actions may short-circuit **idempotent** same-status cases (already under review / already approved / already rejected) without calling the lifecycle
- `StartMembershipApplicationReview` additionally requires `submitted` so `revision_requested → under_review` cannot be used as a hidden resubmit
- Invalid transitions throw `InvalidMembershipApplicationTransition`

Not in V1: `draft`, `withdrawn`, `expired`.

---

## 6. User vs Applicant vs Member boundary

- Submitting an application does **not** create a User.
- Approving an application does **not** create a User and does **not** assign a Spatie `member` role.
- Member email is contact data copied at approval. It is not login identity and is not unique.
- `User` has no `member()` relation; `Member` has no `user()` relation.
- Staff Users appear only as audit actors.

---

## 7. Member creation boundary

HTTP must not insert `members` directly.

```
Controller → ApproveMembershipApplication
               → lock application
               → CreateAb2tiMemberFromApplication (snapshot + persist)
               → MembershipApplicationLifecycle → approved
```

`CreateAb2tiMemberFromApplication` does not create Users, roles, or Koperasi records. Eligibility (must be `under_review`, one Member per application) remains on `ApproveMembershipApplication` inside a transaction with `lockForUpdate` and unique `source_application_id`.

Card-issued flag updates stay on `UpdateMemberCardIssued` (Phase 2A). No card PDF/QR in 3A.

---

## 8. ACL implications

No new permissions in Phase 3A. Canonical two-segment names from Phase 2A / 1G remain:

- `membership-applications.{view,review,approve,reject}`
- `membership-members.{view,update}`
- `membership-documents.{view,manage}`

Not added: `membership.applications.approve`, `membership-applications.revise`, or any grant purpose as a Spatie name.

Policies still authorize **Users** (staff). They do not treat Member as an authenticatable.

`membership-documents.manage` remains registered without a staff upload UI (Phase 2A deviation, unchanged).

---

## 9. Secure Capability integration point

Platform Secure Capability Grant tables/services are **not** implemented (Phase 1E-A contract; still platform future work). Phase 3A does not invent a parallel token.

Readiness:

- Application has **no** durable edit token column
- `revision_requested` exists on the status enum
- Config `membership.revision_capability_purpose` = `membership-application-revision`
- That string is **not** an ACL permission
- Future grant binds purpose + target type + application id (digest at rest); domain state still wins (writes only while `revision_requested`)

Public application revision is **not** implemented in 3A.

---

## 10. Privacy / data-minimization decisions

- Applicant PII lives on the Application; Member holds a contact snapshot at approval only
- Documents stay on the Application (private disk), not File Manager / Spatie Media, not copied onto Member
- No NIK/KTP columns (not justified by Phase 1F)
- Public confirmation uses session `reference_code`, not a public status lookup (Phase 2A)
- Transactional admin/intake pages remain non-indexable (Phase 2A)

---

## 11. Legacy code handled

- Phase 2A Membership code was the legitimate AB2TI implementation, not Presbi. It was **hardened**, not replaced.
- Church/congregation fields were never on these tables; none were added.
- Koperasi copy on the public informational page (`PublicMembershipController`) remains informational only — no Koperasi schema/ACL/UI.
- No leftover three-part permission names.

---

## 12. Tests

Added:

- `tests/Unit/Membership/MembershipApplicationLifecycleTest.php`
- `tests/Feature/Membership/MembershipDomainFoundationTest.php`

Existing Phase 2A Membership tests remain the HTTP/authorization suite.

---

## 13. Unresolved business decisions intentionally deferred

These were not invented:

1. Full applicant field set beyond the Phase 1F minimum.
2. Whether any document type is mandatory on submit.
3. Product-approved `membership_number` / Kartu Anggota display format (schema stores a unique string; generator is technical).
4. Applicant revision / issuing Secure Capability Grants (platform grant persistence still absent).
5. Whether rejection reason is required.
6. Notifications.
7. Member Portal / optional Member↔User link (Phase 1F reserved column).
8. Manual Member without a source application.
9. Deactivation / Member status beyond `active`.
10. Whether `editor` / `platform-admin` should include Membership (1G: ADD LATER).

---

## 14. Inputs required for Phase 3B

Phase 2A already shipped a public intake form. If Phase 3B is a **product** pass on that form rather than a greenfield build, it needs:

1. Confirmation that the Phase 1F field set is the public form contract (or an explicit field list to add via migration).
2. Decision on mandatory documents.
3. Decision on confirmation UX (`reference_code` in session vs other support lookup).
4. Copy/IA changes only — do not collapse User/Application/Member, and do not add Koperasi fields.
5. Do not implement revision grants in 3B unless that phase explicitly owns them.

Phase 3A domain foundation is ready for a public membership application workflow. The existing 2A routes/pages are the current public workflow; 3B should treat them as the starting UI, not as a second intake system.
