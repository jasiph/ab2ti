# AB2TI 2.0 — Phase 3C Staff Review, Decision & Applicant Revision

**Status:** Implemented  
**Date:** 2026-08-21  
**Does not start:** Phase 3D Kartu Anggota / product membership-number format, Member Portal, User↔Member linkage, Koperasi, notifications, Community.

Phase 3C product-completes the existing staff Membership review slice and activates `revision_requested` with a **platform** Secure Capability Grant. It does not rebuild Membership admin, does not add a second intake, and does not create a Membership-specific edit-token table.

---

## 1. Inherited Phase 2A staff review

Phase 2A already shipped:

| Area | What existed |
|---|---|
| Queue | `/admin/membership/applications` with status filter + name/email/reference search |
| Detail | Applicant data, current documents, start-review notes, approve/reject, transition history |
| Actions | `StartMembershipApplicationReview`, `ApproveMembershipApplication`, `RejectMembershipApplication` |
| Member | `CreateAb2tiMemberFromApplication` only from Approve |
| ACL | `membership-applications.{view,review,approve,reject}` plus members/documents |
| Documents | Private disk download via `membership-documents.view` |
| UI | `ConfirmDialog` for approve/reject; `MembershipStatusBadge` → `BaseStatusBadge` |

Phase 3A centralized lifecycle writes and reserved `revision_requested` / `revision_reason` without an Action. Phase 3B completed public intake only.

---

## 2. Audit findings

The 2A staff UI was a working vertical slice, not a finished operational queue.

| Finding | 3C response |
|---|---|
| `revision_requested` unused | New Action + grant issuance |
| No request-revision permission | Added `membership-applications.request-revision` |
| Queue ordered only by `submitted_at` | Actionable statuses first, then recent |
| Detail mixed notes/actions | Separate applicant data, documents, workflow, staff actions, history |
| Approve/reject ConfirmDialog present | Kept; added confirm for request-revision |
| Stale concurrent decisions | Lifecycle + `lockForUpdate` remain authoritative; conflict flash, not silent overwrite |
| No loginless revision | Platform `secure_capability_grants` |
| Notifications absent | Staff copy-once revision URL; no email/WhatsApp claim |
| List already minimized | Kept name, email, reference, status, submitted_at |

Did **not** replace correct 2A controllers, policies, or Member creation.

---

## 3. Canonical staff queue

Same list route. Filters remain status + search (name / email / reference_code).

Ordering: `submitted` → `under_review` → `revision_requested` → terminal, then `submitted_at` desc.

List does not show phone, address, notes, or documents. Status uses existing `MembershipStatusBadge`.

---

## 4. Application detail structure

Staff detail is grouped as:

A. Applicant-submitted data (legal name, contact, address, applicant notes)  
B. Private documents (download still requires `membership-documents.view`)  
C. Workflow state (status, timestamps, actors)  
D. Staff actions allowed for the current status  
E. Chronological transition history

Human-facing staff reference is `reference_code`, not the numeric id (id remains the route key).

`staff_notes` stay internal. `revision_reason` is the applicant-visible instruction. `rejection_reason` is optional and treated as applicant-safe wording, not an internal dump.

---

## 5. Lifecycle transitions

Phase 3C **corrects** the 1F/3A resubmit landing so staff must start review again:

```
submitted            → under_review          StartMembershipApplicationReview
under_review         → approved              ApproveMembershipApplication
under_review         → rejected              RejectMembershipApplication
under_review         → revision_requested    RequestMembershipApplicationRevision
revision_requested   → submitted             ResubmitMembershipApplication (capability actor)
```

Enforcement: `MembershipApplicationStatus::allowedTransitions()` + `MembershipApplicationLifecycle`. Controllers do not write `status`. Invalid transitions throw `InvalidMembershipApplicationTransition` and flash an error.

`StartMembershipApplicationReview` still requires `submitted` (idempotent if already `under_review`). It does **not** treat `revision_requested` as start-review.

Approved and rejected remain terminal. No reopen.

This differs from Phase 1F’s `revision_requested → under_review` on resubmit. The Phase 3C task is authoritative: do not fake start-review on behalf of staff.

---

## 6. Approval boundary

Unchanged: `ApproveMembershipApplication` → `CreateAb2tiMemberFromApplication`.

- Exactly one Member per approved application (`source_application_id` unique)
- No User, no Spatie `member` role, no Koperasi, no card generation
- `membership_number` remains the technical unique key from 3A; not the 3D product format

---

## 7. Rejection behavior

`under_review → rejected` via `RejectMembershipApplication`.

`rejection_reason` remains **optional** (`nullable`, max 5000). No mandatory business-reason policy was invented. UI copy distinguishes it from internal notes. Rejected stays terminal.

---

## 8. Request-revision behavior

`RequestMembershipApplicationRevision`:

- Requires `membership-applications.request-revision`
- Requires current status `under_review` for the first request
- Requires a public-safe instruction (`revision_reason`, min 10 / max 5000)
- Persists instruction on `membership_applications.revision_reason` (existing column; not a JSON dump; not `staff_notes`)
- Transitions `under_review → revision_requested` through the lifecycle
- Issues a Secure Capability Grant for purpose `membership-application-revision`

If status is already `revision_requested`, the same Action **reissues**: updates the instruction, revokes live sibling grants, issues a new grant. No second status hop.

---

## 9. Secure Capability platform implementation

First real consumer of Phase 1E-A.

Platform owns: secret generation, HMAC digest, purpose registry, target allow-list, expiry / revoke / consume, sibling revoke on reissue, session binding by grant id.

Membership owns: purpose name, allowed fields, domain state `revision_requested`, resubmit completion.

The grant is **not** a User, login, Spatie role/permission, ACL bypass, or reference-code authentication. Sanctum PATs are unused.

---

## 10. Capability persistence / schema

Table `secure_capability_grants`:

- `id`, `purpose`, `target_type`, `target_id`
- `secret_digest` **unique** (only uniqueness invariant)
- `issued_by` → users `nullOnDelete`, `issued_at`, `expires_at`
- `revoked_at`, `consumed_at`, `last_used_at`
- indexes `(target_type, target_id, purpose)`, `expires_at`
- **no** plaintext secret, **no** FK to the target, **no** open metadata JSON, **no** Spatie abilities

Registry: `config/secure_capability.php`. Core V1 purpose `membership-application-revision` → target type `membership_application`, TTL 168 hours.

---

## 11. Secret handling

- `random_bytes(32)` encoded URL-safe
- Digest: `hash_hmac('sha256', $secret, app.key)`
- Lookup by digest; compare with `hash_equals` via recomputed digest
- Raw secret exists only in the issued revision URL flash
- Not logged; not stored; not returned on later admin GETs

---

## 12. Issuance / expiry / revocation / consumption

Issued **only** when staff requests revision (`under_review → revision_requested`) or deliberately reissues while already `revision_requested`.

Not issued for submitted / under_review / approved / rejected except the under_review transition above.

Usage policy: **reusable until consumed, revoked, or expired**. Redeem establishes a capability session (grant id only). Resubmit **consumes** the grant. Reissue **revokes** prior live grants for the same purpose+target. Expired/revoked/consumed grants fail identically (generic 404).

Operational consequence: the revision URL is copy-once. If staff lose it, they must reissue (old URL dies). Plaintext is not retained to make “copy later” easy. Automatic email/WhatsApp is **not** implemented.

---

## 13. Applicant revision workspace

Routes (CMS-reserved first segment `akses`):

- `GET /akses/{secret}` redeem (throttle), then redirect to a clean URL
- `GET|POST /akses/revisi` workspace (capability session)
- `GET /akses/revisi/terkirim` confirmation after consume

Bearer secret in the path, then session. Not login, not reference code, not numeric id lookup.

Editable fields = Phase 3B contract only. Documents optional replacements: previous current file is `replaced_at`, bytes retained for history, new bytes on the private disk. Filenames of current documents are shown; grant does **not** download identity files (minimization). Staff notes are never serialized.

---

## 14. Resubmission behavior

`ResubmitMembershipApplication`: lock row, update applicant fields, optional document replace, lifecycle `revision_requested → submitted` with `actor_context = applicant` and null User, set `resubmitted_at`, consume grant, forget capability session.

Application returns to the staff queue as `submitted`. Staff must `StartMembershipApplicationReview` again. Approve from `submitted` still fails.

---

## 15. ACL

Nine Membership capabilities (still exactly one dot):

- `membership-applications.{view,review,approve,reject,request-revision}`
- `membership-members.{view,update}`
- `membership-documents.{view,manage}`

`membership-admin` receives `request-revision`. `editor` / `platform-admin` are not expanded. No public applicant Spatie permissions. Grant authorization is separate from RBAC.

---

## 16. Audit history

Staff detail lists transitions: from/to status, timestamp, actor name or “Applicant”, optional reason (public instruction or rejection wording when stored as transition reason). Raw payloads are not dumped.

Applicant workspace has no staff audit history.

---

## 17. Concurrency handling

Staff decision Actions keep `lockForUpdate`. Lifecycle rejects illegal hops. Stale approve after revision request flashes an error and creates no Member. Applicant resubmit vs staff decision: one row lock wins; the other fails safely.

No event-sourcing framework.

---

## 18. Security / privacy

- Staff routes remain permission-gated
- Capability failures are generic 404 (no enumeration of grant vs application)
- Rate limits: redeem 10/min/IP; revision 20/min/grant-or-IP
- CSRF on POST workspace (web session after redeem)
- `Referrer-Policy: no-referrer` and `X-Robots-Tag: noindex, nofollow` on `/akses/*`
- Transactional SEO noindex on revision pages
- Documents stay on the private disk; grant cannot call Approve/Reject or open other applications
- Mass assignment unchanged; Member identity columns guarded
- Intake nonce is not a revision grant

---

## 19. HCI decisions

- Allowed actions only (`available_actions` is UI convenience; server Actions remain the boundary)
- ConfirmDialog for approve, reject, and request-revision
- Distinct labels/variants; not color-only
- Copy-once revision URL with explicit “no automatic notification” copy
- Reissue path when status is already `revision_requested`
- Applicant form reuses Base inputs/file control; processing/disabled convergence on submit
- Keyboard: validation error focus same pattern as 3B intake

---

## 20. Tests

New: `MembershipRevisionWorkflowTest`, `SecureCapabilityGrantTest`, CMS reserved `akses`. Existing 3A/3B/2A Membership tests preserved. Lifecycle unit tests now include `revision_requested → submitted` and reject `revision_requested → under_review`.

---

## 21. Deferred decisions

- Additional applicant fields / mandatory documents
- Business duplicate policy
- Product membership-number / Kartu Anggota format (Phase 3D)
- Notification architecture (email/WhatsApp)
- Member Portal and Member↔User linkage
- Manual Member creation; Member statuses beyond `active`
- editor/platform-admin Membership access
- Mandatory rejection-reason policy (still optional)
- Applicant download of previously uploaded identity documents through the grant (3C shows filename only)

---

## 22. Phase 3D inputs

Approved Members exist through the canonical approval boundary. `membership_number` is a technical unique key. `card_issued` remains a flag only.

Phase 3D may introduce the product Kartu Anggota AB2TI (numbering policy, card artifact, QR) **without** changing intake, revision grants, or Member creation. Do not treat 3C `membership_number` as the final public card number.
