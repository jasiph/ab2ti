# AB2TI 2.0 — Phase 3B Public Membership Application

**Status:** Implemented  
**Date:** 2026-08-21  
**Does not start:** Phase 3C admin review redesign, Phase 3D card/numbering, Member Portal, Koperasi, Community, applicant revision.

This phase completes the **existing** public intake as a V1 product. It does not add a second registration flow, table, or model.

---

## 1. Inherited Phase 2A public implementation

Phase 2A already provided:

- `GET /keanggotaan` informational landing
- `GET/POST /keanggotaan/daftar` form + `SubmitMembershipApplication`
- `GET /keanggotaan/daftar/terkirim` confirmation (session `membership_reference_code`)
- Throttle `membership-intake` (6/minute/IP)
- Optional private documents on disk `membership_documents`
- POST → redirect → GET
- No User / Member on submit

Phase 3A hardened lifecycle, member creation, and identity split. Phase 3B treats that as locked.

---

## 2. Audit findings

| Finding | Treatment |
|---|---|
| Form copy did not say submit ≠ approval, and Koperasi distinction lived only on the landing | Added on landing, form, and receipt |
| `BaseInput` did not forward `autocomplete` onto the control | Standardized the primitive (attrs go to the input) |
| File inputs were page-local, no selected filename, weak tap target | Added `BaseFileInput` (first domain need, HCI contract) |
| Double-click could create two applications (email is not unique) | Session intake nonce + cache lock; email remains non-unique |
| Files written inside a DB transaction were not deleted if metadata insert failed | Cleanup stored keys on exception |
| Original filenames were stored/downloaded raw | Sanitized at store and admin download |
| Confirmation did not say awaiting review, keep the code, or “not a tracker” | Receipt copy completed; query-string codes ignored |
| Client could not learn mime/size limits except by failing submit | Server still authoritative; constraints passed as UX hints |
| `revision_requested` / edit-after-submit | Not implemented; no parallel token |
| Church/Presbi wording | None found on these pages |

---

## 3. Canonical public applicant journey

1. **Inform** — `/keanggotaan`: association membership, not Koperasi, no account, not automatic approval, three steps.
2. **Fill** — `/keanggotaan/daftar`: contact fields (required) + optional notes + optional documents. No login.
3. **Send** — POST same URL through `StorePublicMembershipApplicationRequest` → `SubmitMembershipApplication` → lifecycle `submitted`.
4. **Receipt** — redirect to `/keanggotaan/daftar/terkirim` with the reference code in session. Refresh does not resubmit.

Public side cannot edit, track status, or open documents after submit.

---

## 4. Field contract

Unchanged Phase 1F minimum. Nothing added for NIK, KTP number, birth data, password, or Koperasi.

| Field | Required | Notes |
|---|---|---|
| `legal_name` | Yes | Trimmed, max 255 |
| `email` | Yes | Trimmed, lowercased for contact hygiene, **not unique** |
| `phone` | Yes | Trimmed, max 32; must contain ≥8 digits; allows `+ - ( )` space |
| `address` | Yes | Trimmed, max 2000 |
| `applicant_notes` | No | Empty → null |
| `applicant_photo` / `identity_document` / `payment_proof` | No | Optional; types/size from `config/membership.php` |

---

## 5. Validation

Server `FormRequest` is authoritative. The form uses `novalidate` so browser English HTML5 messages are not the UX. Indonesian messages; attributes are everyday words, not schema names.

Protected workflow fields (`status`, `reference_code`, staff notes, actor ids) are not fillable from the applicant payload.

---

## 6. Document handling

Unchanged architecture: private disk, UUID object keys, metadata on `membership_documents`, not File Manager.

- MIME/size validated before the Action runs
- Storage path uses application id + type + UUID, not the original name
- Original filename sanitized (control chars, quotes, length)
- If metadata insert fails after bytes were written, keys are deleted and the application transaction rolls back
- Public cannot download; staff still need `membership-documents.view`
- No staff upload UI in 3B

---

## 7. Reference-code behavior

Generated server-side (`APP-` + random / ULID fallback). Unique. Not the numeric id.

Shown on the receipt from **session only**. Query `?reference_code=` is ignored. Possession does not authorize show/edit/download. It is not a Secure Capability Grant.

---

## 8. Submission integrity

- DB transaction around application + transition + document rows
- Filesystem cleanup on failure
- Inertia `form.processing` disables the button and announces sending
- One-time `membership_intake_token` (session) + 5-minute cache lock
- Reusing the same token after success redirects to the receipt instead of inserting another row
- A **new** GET form issues a new token, so a later reapply with the same email is still allowed
- Throttle `membership-intake` 6/minute/IP unchanged

Lifecycle: controller does not set `status`. Submit still calls `MembershipApplicationLifecycle::initialize(..., Submitted)`.

---

## 9. Duplicate-handling decision

**Business-level duplicate policy: deferred.** Email/phone are not unique. Accidental repeat POST of the same form instance is blocked technically (nonce/lock). Intentional second applications remain possible.

---

## 10. Success / receipt behavior

POST → redirect → GET. Refresh of `/terkirim` does not create another row.

The page says: received; awaiting staff review; not approved; keep the code; code is not login/edit/tracker; next step is staff review; no promised SLA, email, or WhatsApp.

Without a session code (direct visit / after flush): honest empty state, no lookup.

---

## 11. Security / privacy

CSRF on the web stack; throttle; mass-assignment limited to applicant fields; documents not publicly URL-guessable; confirmation `noindex`; SEO props have no PII; no public application id; no CAPTCHA; no edit token.

---

## 12. Accessibility / HCI decisions

- Labels associated; required marked with `*` plus a sentence, not color alone
- `autocomplete` / `inputmode` on the actual controls
- Error summary + `role="alert"` + focus first invalid control
- `aria-busy` / status text while sending
- Mobile-first single column; `min-h-control` on inputs, file control, and submit
- File selected name shown; type/size as helper text
- Everyday Indonesian public copy
- Reused `BaseButton`, `BaseInput`, `BaseTextarea`; added `BaseFileInput` as the missing primitive

---

## 13. Lifecycle integration

Submit → `submitted` + transition `applicant`. No public path to `revision_requested`, `approved`, or `rejected`.

---

## 14. Tests

- Extended `tests/Feature/Membership/PublicMembershipApplicationTest.php`
- Trait `Tests\Concerns\SubmitsPublicMembershipApplication`
- Privacy test posts through the same nonce helper
- Phase 3A domain/lifecycle tests kept

---

## 15. Intentionally deferred

Same unresolved product questions as Phase 3A, plus:

- Business duplicate policy (same person / same email already in review)
- Mandatory documents
- Notifications
- Applicant revision (needs Secure Capability persistence + 3C review action)
- Public status lookup

---

## 16. Inputs for Phase 3C

The public flow already feeds `submitted` applications into the existing staff review Actions/UI from Phase 2A.

Phase 3C should product-complete **staff review**, not rebuild intake. Needed decisions: rejection-reason required or not; whether to introduce `revision_requested` (only together with platform grants); queue UX; do not create Members except via `ApproveMembershipApplication`.
