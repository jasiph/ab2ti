# AB2TI 2.0 — Phase 1E-A Secure Capability Access Addendum

**Status:** Architecture addendum to Phase 1E. Not schema. Not implementation.  
**Date:** 2026-08-20  
**Parent:** `docs/discovery/phase-1e-technical-architecture-contract.md`

This addendum defines a reusable **platform** capability for **temporary, scoped, loginless authorization** via secret capability links/tokens. It does not implement tokens, sessions, routes, or storage.

---

## 1. Executive Summary

Staff authorization remains **Identity → Role/Permission → Policy**. That path is insufficient for Core V1 **loginless** membership revision: the Applicant is a party, not a User.

**Secure Capability Access** is therefore a platform kernel capability alongside Identity, ACL, CMS, Tiptap, and Media. A **Secure Capability Grant** answers: *what narrowly scoped temporary authority does the holder of this secret have?*

Possession of a valid capability:

- does **not** establish identity or membership;
- does **not** create a User or assign Spatie roles/permissions;
- is **not** a second general-purpose ACL.

Business contexts **consume** grants (Membership first). The **domain always wins**: valid token + invalid application state = **DENY**.

Core V1 intended consumer: **membership-application-revision** (persisted grant + capability session). Laravel `URL::temporarySignedRoute` exists in the framework (used by skipped Fortify email-verification tests) and is appropriate for **simple, short, non-revocable** actions—not the revision workflow.

A bearer link **cannot cryptographically prove who the human is**. Forwarding the link forwards the authority.

Capability-based self-service ≠ Member Portal.

---

## 2. Relationship to Phase 1E

Phase 1E defined module boundaries, Approve as an Action, private membership documents, reserved CMS segments, and staff ACL. It left Applicant revision as **DECISION REQUIRED** (staff-mediated vs tokenized form).

This addendum **does not overturn** that product decision. It specifies the **architecture if** a secure loginless link is used.

| Phase 1E topic | This addendum |
|---|---|
| Identity ≠ Applicant ≠ Member ≠ Role | Adds **≠ Secure Capability Grant** |
| Platform: Identity, ACL, CMS, Tiptap, Media | Adds **Secure Capability Access** |
| Membership → private documents | Capability may allow **purpose-specific** upload/view, never File Manager |
| Public intake FormRequest | Revision uses **purpose-specific** allowed fields |
| Approve Action | Capability **must not** invoke Approve |
| Sanctum personal access tokens | **User API identity**, not capability grants — do not reuse for this |
| Session (database, 120 min) | Suitable **host** for a **capability session**, isolated from `auth` User |

No contradiction requiring code changes. 1E is not rewritten; a short pointer is added there.

---

## 3. Architectural Invariants

```
User / Account
  != Membership Applicant
  != AB2TI Member
  != Authorization Role
  != Secure Capability Grant
```

| Concept | Question |
|---|---|
| User / Account | Who can authenticate? |
| Membership Applicant | Who participates in an application process? |
| AB2TI Member | Who does AB2TI recognize as a Member? |
| Authorization Role | Which staff capabilities does an authenticated User have? |
| Secure Capability Grant | What temporary, purpose-scoped authority does this secret confer? |

Additional invariants:

- Capability is **not** identity.
- Capability is **not** Spatie permission assignment.
- Domain lifecycle **overrides** a still-valid token.
- Target binding is **resource-type + resource identity + purpose**.
- Administrative fields and Approve remain **staff ACL only**.

---

## 4. Platform Capability Boundary

```
PLATFORM / KERNEL
  Identity
  ACL
  Secure Capability Access
  CMS
  Tiptap
  Media
```

| Layer | Owns |
|---|---|
| **Secure Capability Access** | Secret generation/validation, digest storage strategy, expiry/revocation/consumed checks, target binding, session establishment, rate-limit hooks, generic audit of grant events |
| **Domain (e.g. Membership)** | Purposes, allowed operations/fields, valid domain states, what “complete” means, document access overlay |

```
Membership  -->  Secure Capability Access
Community   -->  Secure Capability Access   (future)
Koperasi    -->  Secure Capability Access   (future)
```

Platform **must not** import Membership state machines. Membership **must not** reimplement token crypto.

**Forbidden:** `token → arbitrary Spatie permission`. Sanctum `personal_access_tokens` stay User API tokens.

---

## 5. Identity Authorization vs Capability Authorization

### Identity-based (staff)

```
Authenticated User → Role / Permission → Policy / Business Rule → Resource
```

### Capability-based (loginless, scoped)

```
Secret Capability → Validate grant → Scoped resource + purpose → Domain rule → Allowed operation
```

They **complement**. Staff still approve via Identity. Applicants revise via Capability (if product chooses links). Evaluating both on one request must be **explicit**: staff `can('approve')` does not enlarge a revision grant; a revision grant does not grant `membership.applications.approve`.

Capability Access is **not** a second ACL catalog.

---

## 6. Secure Capability Grant Concept

Conceptual responsibilities (not columns):

| Concept | Responsibility |
|---|---|
| Grant identity | Stable identifier for audit/revocation (not the secret) |
| Secret / token | High-entropy bearer presented once (URL) then preferably session |
| Token digest/hash | Persisted verifier; raw secret not stored when avoidable |
| Purpose | Closed set of named intents (not a permission string) |
| Target resource | Type + identity the grant is bound to |
| Allowed operations | Interpreted by the domain for that purpose |
| Expiry | After this, fail |
| Revocation | Explicit invalidation before expiry |
| Consumed | Single-use completion (if policy says so) |
| Usage policy | Single-use vs reusable-until-expiry/consume |
| Issuer | Typically a staff User (membership-admin) |
| Issued at | Audit |
| Audit metadata | Events without logging the secret |

Phase 1F decides persistence. Stateless signed URLs may store **nothing** in a grant table.

---

## 7. Purpose-Based Model

Each purpose is owned by a domain. Platform only stores/validates the **name** and generic grant mechanics.

| Purpose | Core V1? | Target | Allowed | Forbidden | Valid domain state | Use | Completion |
|---|---|---|---|---|---|---|---|
| **membership-application-revision** | **Primary example** | One Membership Application | View permitted data; edit revision-eligible fields; upload requested docs; resubmit | Approve/reject; set status; other applications; generic document browse; staff notes | `revision_requested` | **Reusable until consumed/revoked/expired** (assumption) | Resubmit → consume/revoke **DECISION REQUIRED** |
| membership-document-upload | Optional subset of revision | One application (+ optional document slot) | Upload specified artifact | List all docs; File Manager | `revision_requested` | Often single-use per slot | Upload complete |
| member-data-confirmation | **Not Core V1** | One Member | Confirm/limited fields | Status, card, identity docs dump | Product-defined | TBD | Confirm |
| community-submission-edit | **Deferred** | One Submission | Edit submission payload | Publish; CMS Page | Submission in revision | TBD | Resubmit |
| koperasi-document-request | **Deferred** | One cooperative application/record | Requested files only | Association membership; finance | Domain-defined | TBD | Submit |

Do not implement a generic “attach any Spatie permission to a token.”

---

## 8. Stateless Signed URLs vs Persisted Grants

### Repository fact

Laravel supports `URL::temporarySignedRoute` / signature middleware. The app uses it only in **skipped** Fortify email-verification tests. Jetstream/Sanctum tokens authenticate **Users**, not capabilities.

### A. Stateless signed / temporary URL

**Fit:** one-shot confirm, short authorized download, low-state action.  
**Weak for:** revocation, multi-step forms, usage tracking, reissue, consume-on-complete, “invalidate this shared link now.”

### B. Persisted Capability Grant

**Fit:** membership revision (multi-step, audit, revoke, reissue, bind to application, consume).  
**Cost:** persistence (Phase 1F) + session pattern.

**Rule:** do **not** force every link into one abstraction. Core V1 revision → **B**. Trivial future confirm/download → **A** is allowed.

---

## 9. Token / Secret Security Contract

No custom crypto unless the framework cannot meet the need. Prefer Laravel hash/HMAC/signed-URL primitives.

1. High-entropy secrets.  
2. Grant ids and secrets not guessable (no sequential tokens).  
3. Do not persist raw bearer secrets when a digest suffices.  
4. Verify with a secure digest comparison.  
5. Timing-safe compare.  
6. Expired → fail.  
7. Revoked → fail.  
8. Consumed single-use → fail.  
9. Unknown/malformed → generic deny; **do not** reveal whether the application exists.  
10. Authority ≤ purpose (no ACL elevation).  
11. URLs should not embed names, emails, KTP numbers.  
12. Do not log token query strings or session copies of the raw secret.  
13. Rate-limit redeem and mutation endpoints.  
14. Server-side validation always; Vue is UX.

---

## 10. Capability Session Contract

Recommended pattern (fits existing **web + database session** kernel):

```
secure URL with secret
  → validate grant
  → establish capability session
  → regenerate session id (anti-fixation)
  → redirect to clean URL (no secret in query)
  → later requests authorize via session-bound grant id
```

| Responsibility | Note |
|---|---|
| Establish session | After successful redeem |
| Regenerate id | Prevent fixation |
| Store grant identifier | Not the raw secret |
| Re-check expiry/revocation/consumed | **Every** sensitive operation, not only at redeem |
| Clear capability | Explicit logout/end + regeneration |
| Isolation from User | Do **not** `Auth::login` the Applicant. Do not copy staff permissions into the capability session |

Session lifetime (kernel example: database driver, 120 minutes, `SESSION_ENCRYPT=false`) must not **extend** a grant past `expires_at`. Grant expiry wins.

### Authenticated User + capability (same browser)

Opening a capability while already authenticated **must not** silently merge the two authorization sources.

| Principle | Rule |
|---|---|
| Capability scope stays limited | Staff `membership.*` / `cms.*` / `media.*` are **not** granted to the capability holder |
| Staff ACL stays separate | Elevated actions (approve, reject, File Manager, other applications) continue **only** through Identity authorization |
| No role union | Do not combine User permissions with grant purpose into a wider effective ACL |
| Explicit evaluation | The request may **observe** both sources, but each operation names **which** source authorizes it |
| No privilege escalation | A logged-in `editor` who opens a revision link does not gain applicant field-edit via staff identity, and does not gain Approve via the grant |

**Recommended default (DECISION REQUIRED to confirm):** keep the capability session isolated; do not treat `Auth::user()` as the Applicant. If a staff User is present, the product may warn and still apply **only** the grant’s purpose to capability routes.

Do not implement this now.

---

## 11. Resource Binding

A grant is bound to **purpose + resource type + resource identity**. Changing `/applications/123` to `/applications/124` **must fail** even with a valid session.

| Situation | Behavior |
|---|---|
| Resource deleted | Deny (generic) |
| Resource archived / terminal | Deny via **domain state** even if grant active |
| State no longer `revision_requested` | Deny |
| Grant reissued | Previous grant **assumed invalidated** (see §18; **DECISION REQUIRED** to confirm) |
| Duplicate Member/Application | Binding is to the **application id** on the grant, not “same email” |
| Wrong resource | Deny; no enumeration |

---

## 12. Field-Level Authorization

Capability may be **narrower** than staff `update`.

**Architectural rule:** purpose-specific validation / DTO / FormRequest at implementation time. **Do not** build a generic dynamic field-ACL engine.

Example for `membership-application-revision` (illustrative, not a schema):

| May allow (if requested in revision) | Must forbid |
|---|---|
| Phone, address, other applicant-contact fields | `status`, `approved_at`, `approved_by` |
| Requested supporting field | `review_notes`, membership number |
| Requested document upload | Administrative flags, card issued, reviewer |

Exact revision-eligible fields = **product** per request-revision action, not a global editable-field list on the Applicant.

---

## 13. Domain State Enforcement

```
Valid Capability + Invalid Domain State = DENY
```

Example: grant still unexpired, application already **approved** or **rejected** → no edits, no uploads, no resubmit.

Enforcement: **Membership domain operation** after capability infrastructure says “grant is live and bound to this application.” Capability middleware **never** bypasses the state machine, protected fields, or Approve invariants.

---

## 14. Grant Lifecycle

Conceptual events (not necessarily stored enums):

| Event | Meaning |
|---|---|
| Issued | Staff creates grant; secret delivered out-of-band (email/WhatsApp) |
| Active | Valid: not expired, revoked, or consumed |
| Access / redeem | Secret accepted; session established |
| Repeated use | Allowed only if usage policy is multi-step reusable |
| Completed | Domain success (e.g. resubmit) |
| Consumed | Single-use or post-completion invalid |
| Revoked | Staff or system invalidates |
| Expired | `expires_at` passed (may be **derived**, not a stored state) |
| Reissued | New grant; previous handling **DECISION REQUIRED** (default assumption: invalidate prior grants for same application + purpose) |

Expiry can be derived from timestamp. Consumed/revoked should be explicit for persisted grants.

---

## 15. Membership Revision Reference Workflow

```
Application status = revision_requested
        ↓
staff issues Secure Capability Grant (purpose: membership-application-revision)
        ↓
Applicant opens link (no User)
        ↓
validate grant → capability session → redirect (no secret in URL)
        ↓
Applicant may: view permitted data; edit revision-eligible fields;
              upload requested documents; resubmit
        ↓
domain: revision_requested → under_review
        ↓
grant consumed/revoked/expired per policy
```

**Forbidden regardless of token:**

- set `approved` or any review status except via **resubmit** transition;
- change administrative notes;
- view unrelated applications;
- call Approve / Reject / Start review;
- browse all membership documents / File Manager;
- access another id by URL tampering;
- create User or receive a role.

This is **not** a Member dashboard.

---

## 16. Secure Membership Document Interaction

Aligns with Phase 1E §9.

Capability **may** allow purpose-specific **upload** (and, if product allows, **view of documents the applicant just provided or that staff marked as shareable for revision**).

Capability **must not**:

- treat documents as generic Media;
- open File Manager / `files.json`;
- directory-browse;
- reveal `/storage` paths;
- bypass private storage;
- grant **all** documents on the application/member by default.

**DECISION REQUIRED:** may the applicant download previously uploaded identity documents through the revision grant?

---

## 17. Auditability

Log **events**, never bearer secrets.

| Event | Why |
|---|---|
| Issued / reissued / revoked | Staff control, incident response |
| Redeemed / rejected (expired, unknown) | Abuse, support |
| Revision resubmitted | Domain + grant completion |
| Document uploaded via capability | PII handling |

Reasonable metadata: grant id, purpose, target type/id, issuer User id, timestamps, completion flag; IP/user-agent **if** security policy justifies (not decided). Retention **DECISION REQUIRED**.

---

## 18. Revocation / Reissue

Persisted grants exist so operations can:

- revoke one grant;
- revoke all grants for an application + purpose;
- issue a replacement;
- kill an accidentally forwarded link;
- invalidate on domain completion (approved/rejected/resubmitted);
- invalidate after suspected abuse.

Signed URLs **cannot** do this without rotating app key (too blunt) or short TTL only.

**Assumption:** reissue for the same workflow **invalidates** previous grants. Confirm before implementation.

---

## 19. Threat Model

**Limitation:** a bearer capability **authorizes the holder of the secret**, not a proven human identity. Forwarding the link **is** transferring authority unless step-up verification is added.

| Threat | Severity | Mitigation (architecture) |
|---|---|---|
| Token guessing | **CRITICAL** | Entropy, rate-limit, generic 404/403 |
| Link forwarding | **HIGH** (inherent) | Short TTL, revoke, optional step-up; communicate to staff |
| History / copied URL | **HIGH** | Session pattern; redirect off token URL |
| Token in logs | **HIGH** | Do not log query secret |
| Referer leakage | **MEDIUM** | Clean URL after redeem; Referrer-Policy later |
| Expired reuse | **HIGH** | Expiry check every op |
| Replay after consume | **HIGH** | Consume + session re-check |
| IDOR / id substitution | **CRITICAL** | Bind grant to resource |
| Session fixation | **HIGH** | Regenerate session on redeem |
| CSRF after session | **HIGH** | Existing web CSRF on mutations |
| Brute force | **HIGH** | Rate-limit |
| Privilege escalation (merge with staff User) | **CRITICAL** | Isolate sessions; no role union |
| Stale grant after approved | **CRITICAL** | Domain state wins |
| Extra fields | **HIGH** | Purpose FormRequest |
| PII in Inertia | **HIGH** | Minimal props (1E) |
| Capability as Spatie ACL | **CRITICAL** | Purpose model only |

---

## 20. Optional Step-Up Verification

Not required globally.

| Sensitivity | Typical bar |
|---|---|
| Phone/address correction | Secure capability may suffice |
| Replace KTP / legal identity | **DECISION REQUIRED** — may need OTP or staff-only |
| Broad record dump / cooperative finance | Not a capability; staff or stronger identity |

Future: OTP, knowledge check, or require User login. Mark as product policy per purpose.

---

## 21. Cross-Domain Reuse

| Context | Example (not designed now) |
|---|---|
| Community | Contributor edit link |
| Koperasi | Document request |
| Publishing | External draft review |

**Rules:** platform = grant mechanics; domain = purpose, validation, transitions, fields. No circular imports. Publishing must not own the grant table; grants must not call `PublishPublication`.

---

## 22. Authorization Enforcement Map

Extends Phase 1E §7.

| Concern | Enforcement layer |
|---|---|
| Capability token exists / well-formed | Capability infrastructure |
| Not expired / revoked / consumed | Capability infrastructure |
| Targets this resource | Capability infrastructure |
| Purpose is revision (etc.) | Grant purpose + domain authorization |
| Application is `revision_requested` | **Membership domain operation** |
| Which fields may change | Purpose-specific validation |
| Can application be approved | **Staff ACL only** |
| Document browse / File Manager | Membership document auth — **deny** for capability |
| Vue shows Save/Resubmit | UX only |
| Staff User also in browser | Explicit separate evaluation; no merge |

---

## 23. Route Boundary

Do **not** change `config/cms.php` now.

Capability HTTP must:

- be **explicit routes above** CMS catch-all;
- use a **reserved first segment**;
- not be ordinary published content;
- redeem then **redirect** to a token-free path;
- avoid PII in the path.

**Recommendation:** reserve **`access`** (e.g. conceptual `/access/…` redeem + `/access/application/…` after session). Alternatives `secure` / `grant` are acceptable; pick one in implementation ADR.

Also reserve that segment in `config/cms.php` **when** routes are added (same class as `login`). Collision with a CMS page named `access` must be prevented.

Not `/admin` (staff). Not `/user` (Jetstream). Not Sanctum `/api`.

---

## 24. Technical Test Contract

Do not write tests now.

| Area | Tests | Class |
|---|---|---|
| Token | Unknown, malformed, expired, revoked, consumed denied; valid only for target | **SECURITY** / **FEATURE** |
| Isolation | Token A cannot open application B; id swap fails | **SECURITY** / **ARCHITECTURE/BOUNDARY** |
| Domain | Revision works only in `revision_requested`; approved/rejected deny | **FEATURE** / **SECURITY** |
| Fields | Cannot set status, approval fields, review notes | **SECURITY** |
| Session | Redeem regenerates session; secret not required after; revoke kills later POST | **SECURITY** |
| Fixation | Session id changes on redeem | **SECURITY** |
| Separation | Cannot approve; no User; no roles; staff login does not widen grant | **SECURITY** / **ARCHITECTURE/BOUNDARY** |
| Documents | No File Manager; no public GET of private file | **SECURITY** |

UNIT: digest compare, expiry derivation, purpose allow-list.

---

## 25. Phase 1F Input Contract

Schema phase should **evaluate** (not invent columns here):

Persisted grants (revision): grant identity, token digest, purpose, target type/id, issuer, expires_at, revoked_at, consumed_at, usage policy, timestamps, audit association, cleanup/retention.

**May not persist:** one-shot Laravel signed URLs (signature + expiry in URL).

Also: uniqueness of “one active grant per application+purpose” if reissue invalidates old; session payload is **not** a table.

Do not design FKs in this addendum.

---

## 26. Decision Register

### CONFIRMED DECISIONS

- Secure Capability Access is a **platform** capability, distinct from Identity and ACL.
- It must **not** assign arbitrary Spatie permissions.
- Membership **may** consume it for loginless revision; product still chooses link vs staff-mediated.
- Domain rules remain authoritative (`Valid capability + invalid state = DENY`).
- Capability URLs are **bearer** authorization, not proof of human identity.
- Revision workflow uses **persisted grants** + capability session, not only signed URLs.
- Capability ≠ Member Portal / Member login.
- Sanctum PAT ≠ capability grant.
- Approve remains staff-only.

### ASSUMPTIONS

- If links are used in V1, purpose `membership-application-revision` is the first consumer.
- Revision grants are **reusable until** consume/revoke/expiry (multi-step form).
- Reissue **invalidates** previous grants for the same application + purpose.
- Session pattern is used so the secret does not stay in the URL.
- Reserved path prefix **`access`**.
- No step-up verification for phone/address-level revision.

### DECISIONS REQUIRED BEFORE IMPLEMENTATION

- Whether V1 uses capability links at all vs staff-only revision.  
- Default expiry duration.  
- Single-use vs multi-use; auto-consume on resubmit.  
- Reissue invalidates previous (confirm assumption).  
- Rate limits.  
- Applicant download of existing identity documents.  
- Step-up for legal-identity document replacement.  
- Audit retention; log IP/UA?  
- Exact reserved segment (`access` vs `secure` vs `grant`).  
- Behavior if a **staff User** is already logged in when redeeming (recommended: keep capability scope; do not union with `editor`/`membership-admin`; prefer separate browser/profile or explicit “continue as applicant” that does not grant staff ops via the grant).

### DEFERRED

- Community / Koperasi / Publishing purposes.  
- Member Portal and permanent Member login.  
- High-assurance verification, OTP.  
- Signed-URL downloads as a later Media/document feature.

---

## 27. Recommended Next Step

**Phase 1F — Schema / Data Contract**, now including:

- Membership Application / Member / documents (from 1E);
- **whether and how** persisted capability grants are recorded (digest, purpose, target, expiry, revoke, consume);

still **without** implementing tokens, routes, or migrations as application features.

Do not implement capability redeem or session logic in 1F.

---

End of Phase 1E-A.
