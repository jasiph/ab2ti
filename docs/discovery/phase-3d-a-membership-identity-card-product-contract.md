# AB2TI 2.0 — Phase 3D-A Membership Identity & Kartu Anggota Product Contract

**Status:** Discovery / product-domain contract. **Not schema. Not implementation.**  
**Date:** 2026-08-22  
**Does not start:** Phase 3D-B card implementation, numbering generator replacement, QR routes, PDF, Member Portal, Koperasi, Badge Koperasi.

This document is the authoritative product/domain contract for official AB2TI membership identity and Kartu Anggota AB2TI **before** any Phase 3D implementation.

It does **not** treat the current `membership_number` generator or the `card_issued` checkbox as approved product semantics.

---

## 1. Executive summary

AB2TI 2.0 already creates an **AB2TI Member** only through:

```
ApproveMembershipApplication → CreateAb2tiMemberFromApplication
```

That boundary stays locked. The Member is the official association identity. A Kartu Anggota AB2TI is **evidence** of that identity, not a second membership and not a Koperasi card.

Four identities must stay distinct:

| Identity | What it answers |
|---|---|
| Database identity | Which `members` row? (`members.id`) |
| Official membership identity | Which AB2TI member is this, durably, in the association? |
| Card identity | Which issued card instance/version is this? |
| Verification identity | What may a scanner learn, without authentication? |

**What is already true in code (not product policy):**

- `membership_number` is a unique technical string (`MBR-YYYYMMDD-…` or `MBR-{ULID}`).
- `card_issued` / `card_issued_at` is a staff checkbox meaning “a physical card has been noted as given.”
- Applicant photo is optional on intake and lives on the **Application**, not on Member.
- Member status is `active` only.
- There is no card table, QR, public verification route, PDF, or card visual template in this repository.

**What this phase does not invent:** the exact official number format, whether cards expire, whether a photo is mandatory for membership, and how historical paper members are numbered. Those are **REQUIRES BUSINESS DECISION**.

**Recommended direction for Phase 3D-B (not yet requirements):**

- Official membership number is assigned when the Member is created and does not change for card reprints.
- Kartu Anggota is one logical issued card with digital and printed representations.
- Replacement (lost/stolen/damaged/data change) keeps the membership number and rotates card/verification identity.
- QR is an opaque public verification identifier. It is not a login, not ACL, and not a Secure Capability Grant.
- Card issuance history belongs on a dedicated issuance record, not as more flags on `members`.

---

## 2. Existing Member identity state

Inspected: Phase 1C/1E/1F, Phase 3A–3C implementation records, `members` migration, `Member` model, `MembershipNumberGenerator`, `CreateAb2tiMemberFromApplication`, `UpdateMemberCardIssued`, admin Member show/index, public brand assets.

### 2.1 Aggregates (locked)

```
User / Account              ≠  MembershipApplication  ≠  Member
Secure Capability Grant     ≠  Member                 ≠  Kartu Anggota
```

- **User:** staff authentication.
- **MembershipApplication:** intake packet and review history. `reference_code` is support identity, **not** authorization and **not** membership.
- **Member:** official AB2TI association member after approval.
- Koperasi remains a separate bounded context. There is no cooperative membership card in this contract. Future **Badge Koperasi** is out of scope.

### 2.2 Current Member columns (reality)

| Column | Current meaning | Product meaning |
|---|---|---|
| `id` | Internal PK / route key | Database identity only. Must never be the public membership number or QR payload |
| `membership_number` | Unique string written at Member creation by `MembershipNumberGenerator` | Technical uniqueness key. **Not** the approved Kartu Anggota number format |
| `source_application_id` | Unique NOT NULL FK | V1 Members always come from an approved application. Manual/imported members remain a later decision |
| `status` | `active` only | Member lifecycle beyond active is still deferred |
| `legal_name`, `email`, `phone`, `address` | Snapshot at approval | Contact master as of approval. Email is contact, not login |
| `recognized_at` | Same instant as application approval | Candidate “membership start” for the card; not a card-issue date |
| `card_issued` / `card_issued_at` | Staff-toggled flag | Operational note only. Not issuance history, not QR, not print |
| `created_by` | Approving User | Audit of Member creation |

`membership_number` generation today: `MBR-{Ymd}-{8 random}` with ULID fallback. Prefix `MBR` is config, not organizational policy. Uniqueness is enforced; format is **not**.

### 2.3 Photo and documents (reality)

`applicant_photo` is an optional private Membership document on the **Application**. Documents have no `member_id`. Approval does not copy photo bytes onto Member. Staff may download with `membership-documents.view`. The applicant revision grant does not download identity files (filename only).

### 2.4 Legacy AB2TI practice (audit, not authoritative)

Phase 1A recorded from the public WordPress site:

- Physical card + **Rp 50.000** print/shipping fee.
- Fee paid to a **Koperasi AB2TI** bank account (process accident/policy, not identity).
- Intake attachments: photo, KTP, transfer proof.
- **No** public evidence of digital card, member login, numbering scheme, expiry, or secretariat card workflow.

This repository contains **no** sample Kartu Anggota PDF/image, **no** imported member-number list, and **no** historical card renderer. The only visual marks found are the in-app SVG wordmark (`ApplicationLogo`) and a site-wide OG placeholder JPEG. Those are **not** a card design.

Do not treat the legacy fee, Koperasi bank account, or WordPress form as 3D-A requirements. Payments/dues remain out of scope.

### 2.5 Admin/profile concepts (reality)

- Staff: `/admin/membership/members` list/show; search includes `membership_number`; checkbox updates `card_issued`.
- Permissions: `membership-members.view` / `membership-members.update`.
- Jetstream `/user` profile is **staff User** profile, not a Member portal.
- Public site: `/keanggotaan` informational + intake. No member card page.

---

## 3. Identity taxonomy

These four must not be conflated.

### 3.1 Database identity

- **Carrier:** `members.id` (and internally `membership_card_issuance.id` if 3D-B adds that record).
- **Audience:** application, staff URLs, foreign keys.
- **Forbidden uses:** printing on the card as “Nomor Anggota”; embedding in QR; public verification URLs; sequential guessing of other members.

Staff admin routes may continue to bind `{member}` by id because they are authenticated and ACL-gated. That does not make `id` a membership number.

### 3.2 Official membership identity

- **Carrier:** the future **official AB2TI membership number** (durable association identity).
- **Audience:** member, staff, printed/digital card, public verification display.
- **Lifetime:** follows the **Member**, not a particular piece of plastic or PNG.
- **Today’s column:** `members.membership_number` is the persistence slot (unique string). Its **current generator format is not this identity’s approved shape**.

Also not membership identity: application `reference_code`, User id, email, phone, NIK (not even stored).

### 3.3 Card identity

- **Carrier:** a particular **issued card / issuance version** (logical record).
- **Audience:** staff operations (issue, reprint, replace, revoke), audit, print jobs.
- **Lifetime:** shorter than the Member. A member may have many issuances over time; at most one should be **current/valid** for verification.
- **Today:** does not exist. `card_issued` is not a card identity.

### 3.4 Card verification identity

- **Carrier:** opaque **public verification identifier** (or equivalent) bound to one issuance.
- **Audience:** anyone who scans the QR or opens the public URL.
- **Power:** **verification only** — “does AB2TI currently recognize this card as a valid Kartu Anggota for this Member?”
- **Not:** authentication, authorization, Secure Capability Grant, staff signed URL, applicant revision token, Member Portal session.

```
members.id
    != official membership number
    != card issuance identity
    != QR / public verification identifier
    != application reference_code
    != User id
    != Secure Capability Grant
```

---

## 4. Membership-number requirements

**Final format: REQUIRES BUSINESS DECISION.**

No document, asset, or data in this repository proves an organizational numbering rule (no “AB2TI-2024-000123”, no regional code, no checksum alphabet). Inventing a pretty format would freeze a guess onto every future Member.

Until AB2TI states the rule, Phase 3D-B must **not** treat `MBR-YYYYMMDD-…` as the public Kartu Anggota number.

### 4.1 Technical constraints the eventual format must satisfy

| Concern | Constraint |
|---|---|
| Uniqueness | Globally unique among Members. Database UNIQUE on the official number. Never reuse a retired number for a different person |
| Stability | The number is assigned once to a Member and **does not change** because a card is reprinted, replaced, or redesigned |
| Human readability | Printable on a card; distinguishable in speech/support (avoid easy confusion with `members.id` or `APP-…` reference codes) |
| Changes | **RECOMMENDED:** never after the number has been shown on a card or verification page. Data-entry correction **before first issue** may be allowed as a controlled staff operation |
| Chronological | Not required. Encoding approval date/year is a **business** choice, not an architecture need |
| Year encoded | **REQUIRES BUSINESS DECISION.** If yes, define which year (approval year vs calendar year vs fiscal) and what happens at year rollover |
| Region encoded | **RECOMMENDED: no** for V1. Regional structure is unknown (Phase 1B/1C). Encoding a placeholder region will create false identity |
| Collision | Generator + UNIQUE + row lock/retry. Sequential schemes need an allocation lock, **not** `members.id` |
| Legacy / imported | If a historical official number exists and is unique, **preserve it**. If it collides, stop and resolve with AB2TI; do not silently mint a new public identity |
| Manual vs automatic | **RECOMMENDED:** automatic at Member creation for new approvals. Manual assignment only for proven legacy numbers |
| Scale | Variable-length **string**, not integer identity, not zero-padded `id`. Must survive tens of thousands of members without format breakage |
| Privacy | Must not embed NIK/KTP, date of birth, phone, or home region. A public-verification page will display this number |
| Not sequential DB id | Locked. `members.id` is never the public number |

### 4.2 Current generator

Keep as a **temporary uniqueness key** so approval can create Members today. When the official format is locked, Phase 3D-B replaces the generator and decides how already-created technical numbers are rewritten (only safe if those numbers were never printed/published).

---

## 5. Number assignment lifecycle

Canonical product sequence:

```
approved application
    → Member created
    → official number assigned
    → membership identity active
    → card may be issued
```

The card is optional relative to membership identity. A Member can exist, be active, and **not** yet have a Kartu Anggota.

### Option A — Atomic with Member creation (current code shape)

`CreateAb2tiMemberFromApplication` writes `membership_number` in the same transaction as the Member insert.

| For | Against |
|---|---|
| Every Member immediately has a unique identity | Official format is not known; today’s string would be the “official” number if printed too early |
| Matches uniqueness/not-null schema | Sequential/year schemes are harder inside the approval transaction if they need a separate allocator |
| Card issuance cannot proceed without a number | Secretariat cannot “hold” a Member while choosing a ceremonial number |

### Option B — Explicit post-approval identity issuance Action

Approval creates the Member with a **non-public** technical key; a later staff Action assigns the official number.

| For | Against |
|---|---|
| Secretariat can apply a ceremonial/sequential policy | Two-phase identity: Member exists but is not “fully numbered” |
| Format decision can land without rewriting approval | Extra state (`unnumbered` vs `numbered`) on an aggregate that V1 currently treats as complete at approval |
| | Risk of staff issuing a card before the official number exists |

### Recommendation (not a requirement)

**RECOMMENDED: Option A** — official number assigned **atomically with Member creation**, independent of card issuance.

Rationale: membership identity must not wait on print shops, photos, or fees. Phase 1E already requires one Member per approval in one transaction. The **generator algorithm** is what changes in 3D-B once format is locked; the **assignment point** should stay at creation.

If AB2TI later requires dense sequential numbers with no gaps, that is still Option A with a locked allocator inside the approval transaction — not a separate “identity issuance” product step.

**Do not** assign the official number only when the card is issued. That would make unprinted Members second-class identities.

---

## 6. Card data contract

Kartu Anggota AB2TI displays a **minimized public face** of the Member. Do **not** add Member columns merely because a card mockup looks empty.

Classification is for **what may appear on the card / public verification**, not for what staff may see in admin.

| Field | Class | Notes |
|---|---|---|
| Organization name (“AB2TI” / legal organization name) | **REQUIRED** | Branding. Exact legal line **REQUIRES BUSINESS DECISION** (association vs longer formal name) |
| Organization logo | **REQUIRED** (on a produced card) | No approved card artwork in-repo. Use an AB2TI-approved mark in 3D-B, not the OG placeholder |
| Member legal name | **REQUIRED** | From Member `legal_name` |
| Official membership number | **REQUIRED** | Official identity, not `members.id` |
| Member photo | **OPTIONAL** on membership; **UNRESOLVED** as card-mandatory — see §7 | Must not be File Manager public media |
| Membership start date | **OPTIONAL** | Candidate: `recognized_at` (approval). Whether to print it **REQUIRES BUSINESS DECISION** |
| Card issue date | **OPTIONAL** | Date of **this issuance**, not membership start |
| Card expiry date | **UNRESOLVED** | Depends on §9. Do not print a date until expiry policy exists |
| Regional / organizational unit | **NOT RECOMMENDED** for V1 | Structure unknown; do not invent wilayah codes |
| QR code | **RECOMMENDED** for V1 if any public verification is in scope | See §12–13 |
| Signature / wet stamp | **UNRESOLVED** | Organizational ceremony, not domain data |
| Official title/copy (“Kartu Anggota AB2TI”, validity sentence) | **REQUIRED** as template copy | Exact wording **REQUIRES BUSINESS DECISION** |
| Address | **NOT RECOMMENDED / PRIVATE** | Home location on a scannable card is unnecessary |
| Phone | **NOT RECOMMENDED / PRIVATE** | |
| Email | **NOT RECOMMENDED / PRIVATE** | Contact, not identity |
| NIK / KTP | **NOT RECOMMENDED / PRIVATE** | Not stored on Member; must not appear on card or QR |
| Date of birth | **NOT RECOMMENDED / PRIVATE** | Not stored; do not add for the card |

Payment proof, staff notes, application reference code, User account, and Koperasi status **must not** appear on the card.

---

## 7. Photo decision

| Question | Result |
|---|---|
| Mandatory for **membership** (approval)? | **RECOMMENDED: no.** Phase 3B intake photo is optional. Do not retroactively require it |
| Mandatory for **card issuance**? | **REQUIRES BUSINESS DECISION.** Legacy practice collected a photo; that is not a locked V1 rule |
| Optional everywhere? | Possible, but a photo-less card is a weaker identity document |
| Unresolved? | Yes, for card issuance only |

**RECOMMENDED product split (not implemented):**

- Membership remains valid without a photo.
- If AB2TI wants a portrait on Kartu Anggota, treat photo as a **post-approval card-completion** requirement: staff (or a later non-portal channel) attach a current portrait before **issue**, without changing the public apply form.
- Source of truth for the card portrait should be an explicit **current member portrait for issuance**, not an implicit reuse of a possibly stale `applicant_photo`. Reusing the application photo as a **default candidate** is acceptable; copying KTP scans onto the card is not.

Do not add photo upload UI in 3D-A. Do not store card portraits in File Manager.

---

## 8. Member vs card lifecycle

These are different machines. Card expiry or replacement **must not** terminate membership.

### 8.1 Member status (V1)

Phase 1C/1F: `active` only on create. Inactive / suspended / resigned / deceased remain **DEFERRED** unless AB2TI later defines policy.

Kartu Anggota V1 does **not** require expanding Member status. If a Member is not `active` in a future phase, verification must fail even if a card artifact still exists.

**Do not** adopt unused example states (`inactive`, `suspended`) in 3D-B.

### 8.2 Card / issuance status — minimum V1

Do **not** adopt a large CRM lifecycle. The minimum that replacement + QR actually need:

| State | Meaning |
|---|---|
| **not_issued** | Member exists; no current valid Kartu Anggota |
| **issued** | There is a current valid issuance (digital and/or printed representations of **that** issuance) |
| **replaced** | A newer issuance superseded this one (lost, damaged, data/photo change, template redesign that invalidates old cards) |
| **revoked** | Staff invalidated this issuance without necessarily issuing a replacement (theft reported, error, abuse) |

**Not in V1 unless expiry is decided:** `expired` as a stored state. If cards expire, **derive** expiry from `expires_at` + now, and treat derived-expired like revoked for verification (do not invent a second membership death).

**Not needed:** `draft` as a public state. Staff preview is a rendering concern, not a domain status.

`card_issued = true` today roughly means `issued` **without** history. It cannot express replaced/revoked.

---

## 9. Card validity

**REQUIRES BUSINESS DECISION.** The public site and this repository do not state a term, renewal, or lifetime.

| Option | Technical consequence |
|---|---|
| **No expiry** | Issuance has no `expires_at`. Valid until replaced or revoked. Simplest V1. Verification does not depend on a clock |
| **Periodic expiry** (e.g. N years from issue or from `recognized_at`) | Store `expires_at` on the **issuance** (not on Member). Renewal = new issuance or extend policy — **do not** change membership number. Do not set Member to inactive when the card date passes |
| **Other organizational rule** (board term, dues year, event pass) | Out of scope until stated. Do not encode dues into the card |

**RECOMMENDED default if AB2TI has no rule:** **no expiry** for V1, because inventing a 1- or 5-year term would mislead members and force a renewal product that does not exist.

Print copy must not say “berlaku sampai …” until this decision is made.

---

## 10. Issuance and reissue model

**LOCKED principle:** membership identity persists independently of physical/digital card instances.

| Event | Membership number | Card identity | Verification identity |
|---|---|---|---|
| First issue | Unchanged (already assigned) | Create first issuance | Create first opaque verifier |
| Reprint (same data, same issuance, extra copy) | Unchanged | **Same** issuance | **Same** verifier **RECOMMENDED** |
| Replacement — lost / stolen | Unchanged | New issuance; old → replaced or revoked | **Rotate** (old QR dies) |
| Replacement — damaged | Unchanged | New issuance; old → replaced | **Rotate** (old QR may still be on fragments) |
| Changed photo or legal name | Unchanged | New issuance; old → replaced | **Rotate** |
| Template redesign | Unchanged | **REQUIRES BUSINESS DECISION:** new issuance vs reprint. If old cards must stop verifying, rotate |

Replacement **RECOMMENDED** always invalidates the previous verification identity for lost/stolen. Reprint of an extra copy of an **uncompromised** current card may keep the same verifier so two pieces of plastic are not two members.

Staff actor and timestamps belong on the issuance record.

Fees, shipping, and print vendors are **process**, not identity. The legacy Rp 50.000 charge is **DEFERRED** (payments).

---

## 11. Digital vs printed semantics

**RECOMMENDED:** digital display and printed output are **two representations of one logical issued card**, not two memberships and not two issuances by default.

```
Member (1)
  └── current issuance (0..1)
        ├── digital representation (screen / print CSS)
        └── printed representation (same data, print stylesheet / later PDF)
```

The Member must not acquire two association identities because both exist.

Print-specific (not identity):

- Physical stock, lamination, postage.
- Color/gamut, bleed, and printer margins.
- Whether a wet stamp is applied after print.

If AB2TI later needs “digital-only members” vs “plastic mailed,” that is a **fulfillment flag on the same issuance**, not a second Member.

Phase 1B listed digital card as a later enhancement of the physical process. 3D-A **RECOMMENDED** that V1 may still render a **screen-faithful** card for staff (and later members) without requiring a User account, as long as it is the same issuance.

---

## 12. QR / public verification contract

### 12.1 What the QR is

A pointer to a **public verification endpoint** carrying only a **purpose-built opaque verification identifier**.

It must **not** contain or authorize through:

- sequential `members.id`
- application `reference_code`
- User id
- login / session / Sanctum token
- Secure Capability Grant (revision grants are loginless **write** authority; QR is not that)
- plaintext name, address, email, phone, NIK
- arbitrary signed staff authority (`URL::temporarySignedRoute` is the wrong tool: hard to revoke, wrong threat model)

**RECOMMENDED shape (conceptual):** `https://{public-host}/{reserved-segment}/{opaque-id}`

The path segment should be reserved in CMS (candidate names: `verifikasi` or `kartu`) in 3D-B. Do not implement the route now.

Opaque id: high-entropy, non-sequential. Persist a **digest** if the raw secret is treated as a bearer of verification (same digest discipline as grants, **different purpose table/column**, never reusable as a revision grant). If the identifier is a public lookup key rather than a secret, it must still be unguessable and bound to one issuance.

### 12.2 Minimum public response (valid current card)

| Include | Exclude |
|---|---|
| AB2TI identity / branding | Address, email, phone |
| Member legal name | Documents, photo bytes **unless** AB2TI explicitly wants a public portrait — **RECOMMENDED exclude photo from the public page** |
| Official membership number | MembershipApplication, staff notes |
| Membership/card validity in plain language (e.g. “Kartu Anggota AB2TI berlaku”) | User/account, Spatie roles |
| Limited card identity: issue date optional; expiry only if policy exists | Other members, enumeration hints |

### 12.3 Outcomes

| Situation | Response |
|---|---|
| Valid current issuance | Branded success with the minimized fields above |
| Replaced / revoked / derived-expired | Generic **not valid** (same family of branded error). Do **not** explain “this used to be member X” |
| Unknown identifier | **Same generic not valid / not found** as replaced. Do not distinguish “never existed” vs “revoked” |

Avoid enumeration: no `id+1` scanning, no different status codes that leak existence if that would help an attacker map members. Prefer one branded failure page.

Rate-limit the public endpoint in 3D-B. `noindex`. No capability session.

---

## 13. Security / privacy model

```
verification identifier  !=  authentication
verification identifier  !=  authorization
verification identifier  !=  Secure Capability Grant
```

A scanned card **must not** allow: editing Member, Member Portal, private documents, membership state changes, applicant revision, approval, or Koperasi access.

Additional rules:

- Staff card operations remain Identity → Role → Policy (`membership-members.*` and any later issue/revoke action).
- Public verification is a **read of public-safe card facts**, not an ACL bypass.
- Do not log raw verification secrets in application logs.
- Do not put PII in the QR payload; the QR is an identifier, and the server decides what to show after status checks.
- Card portraits, if stored, stay on the private membership document disk (or equivalent), never `/storage` File Manager.
- Forwarding a verification URL only discloses the same public-safe facts as holding the card. That is inherent to a public membership card. Rotation on loss is the mitigation.

---

## 14. Recommended card domain model

### 14.1 Options

**A. Fields only on `members`**  
Add more columns (`card_status`, `card_expires_at`, `verification_digest`, …).

Too weak once replacement exists: one row cannot hold current + previous verifiers, issue history, or revoke-without-deleting-membership without JSON dumps (forbidden as a dumping ground).

**B. Dedicated issuance record** (recommended)

A Membership-owned **card issuance** conceptual entity, many-to-one with Member:

- `member_id`
- status: issued / replaced / revoked
- issued_at, issued_by
- replaced_at / revoked_at, actor
- verification identifier digest (or public opaque id + digest)
- optional expires_at
- optional template_version
- optional link to the portrait document used for that issuance

**C. Other**  
Event-sourcing or a generic “artifacts” table: rejected. Too much platform.

### 14.2 Recommendation

**RECOMMENDED: Option B** — dedicated issuance record.

Not for elegance: V1 genuinely needs **history, replacement, QR rotation, and audit** without overloading `members`. Membership number and contact master stay on Member. Card lifecycle stays on issuances.

Do not implement the table in 3D-A.

---

## 15. Existing `card_issued` treatment

| Question | Answer |
|---|---|
| Remains usable as canonical lifecycle? | **No** |
| Derived? | **RECOMMENDED yes:** `card_issued` ⇔ Member has a current issuance in `issued` |
| Redundant? | After 3D-B backfill, the checkbox is a denormalized convenience or should be removed |
| 3D-A change? | **None** |

**3D-B migration strategy (conceptual):**

1. Introduce issuance records; do not drop `card_issued` in the first migration.
2. If any production rows have `card_issued = true`, backfill a single `issued` issuance **without** inventing a verification QR until staff re-issue (or generate verifiers in a controlled cutover — **REQUIRES BUSINESS DECISION** because those physical cards have no QR today).
3. Staff UI: replace the checkbox with issue / replace / revoke.
4. Optionally keep `card_issued` updated as a derived cache, then deprecate.

`UpdateMemberCardIssued` is a stopgap Action. 3D-B should stop treating it as product issuance.

---

## 16. Legacy-member implications

This application has **no imported AB2TI member file** and **no** historical numbers. `source_application_id` is NOT NULL: V1 cannot legally insert a Member without an Application.

Before any migration of paper members:

1. Does an official numbering book / spreadsheet exist? Photograph of existing Kartu Anggota?
2. Are those numbers still considered valid?
3. Collision with `MBR-…` technical strings already in this database?
4. Members without a digital MembershipApplication — product still **DEFERRED** (1F). Import would need an explicit later decision to relax `source_application_id` or to create synthetic applications (do not do this casually).
5. Physical cards in the field have **no QR**; issuing a 2.0 card is a **replacement**, not a silent overlay.
6. Duplicate humans (same name/email) — business duplicate policy is still deferred; do not merge by email.

Do not invent legacy rows. Do not run a migration in 3D-A.

---

## 17. Admin operation requirements

Minimum staff operations justified by this V1 lifecycle. Not a CRM.

| Operation | Why |
|---|---|
| View Member identity | Already exists |
| See official membership number | Already exists (today: technical string) |
| Complete card-required portrait (if photo-for-card is chosen) | Post-approval completion only |
| Issue first Kartu Anggota | Creates issuance + verifier |
| Reprint current card | Same issuance |
| Replace card | New issuance, rotate verifier |
| Revoke current card | Invalidates verifier; Member remains |
| View issuance history | Operational + audit |

**Assign/confirm official number:** only needed if AB2TI rejects Option A or needs legacy override. Not a default V1 screen for every approval.

**Out of V1 admin:** dues, shipping tracker, print-vendor portal, member directory, Koperasi badge, deactivate Member.

### ACL

Do not freeze a new permission catalog in 3D-A. Existing `membership-members.view` / `update` already cover today’s checkbox.

If 3D-B splits issue/revoke from contact update, use canonical two-segment names such as `membership-members.issue-card` / `membership-members.revoke-card` — **RECOMMENDED to consider**, not locked. Do **not** create `membership.cards.issue`.

No public applicant/member Spatie permissions for verification.

---

## 18. Member Portal boundary

**LOCKED:** Kartu Anggota must **not** require a User account.

A Member may exist and hold an official card with zero platform authentication. Staff may display/print the digital representation in admin.

Future self-service “my card” **DEFERRED** until Member↔User linkage. Do not use Secure Capability Grants as a fake Member Portal.

---

## 19. Rendering / print requirements

Separate **domain data** (this contract) from **visual template** (a later design artifact).

Phase 3D-B should support conceptually:

| Concern | Contract |
|---|---|
| Browser / digital representation | Staff (and later member) view of the **current issuance** using the data contract |
| Printable output | Print stylesheet of the same view; optional PDF later if operations need a file. PDF is not required to start |
| Stable dimensions | Pick one print size in 3D-B from AB2TI stock (**REQUIRES BUSINESS DECISION**: e.g. ID-1 CR80 vs A7). Do not invent artwork now |
| Front / back | **RECOMMENDED:** front = identity + photo + number; back = QR + verification URL + short validity sentence. Confirm with design |
| Logo / photo | Approved logo asset; portrait from private storage, not File Manager |
| Typography | Print-safe (no ultra-light weights, sufficient contrast). Site typeface Inter is for UI; card type may differ — design later |

Do **not** design the visual card in 3D-A. No PDF, no generated images.

---

## 20. Audit requirements

Minimum persistent history (on Member + issuance rows), **not** event sourcing.

| Event | Where |
|---|---|
| Official number assigned | Member create (`created_by`, `recognized_at`, number column) |
| First card issued | Issuance row: actor, issued_at |
| Card replaced | Old issuance replaced_*; new issuance issued_* |
| Card revoked | revoked_at, actor, optional staff-only reason (not printed) |
| Verification identifier rotated | Same as replace; old digest unusable |
| Template version used | Optional on issuance |

Do not store raw QR secrets. Do not audit every public scan in V1 unless abuse later requires it (**DEFERRED**).

---

## 21. Product decision register

| # | Issue | Classification |
|---|---|---|
| 1 | Membership number **format** | **REQUIRES BUSINESS DECISION**. Constraints in §4 are **LOCKED** (unique string, not `id`, not NIK, stable) |
| 2 | Assignment **timing** | **RECOMMENDED:** atomic with Member creation. **LOCKED:** not delayed until card print |
| 3 | Photo requirement | **RECOMMENDED:** not mandatory for membership. **REQUIRES BUSINESS DECISION:** mandatory for card issue? |
| 4 | Fields printed on card | **LOCKED:** name + official number + org identity; no address/email/phone/NIK/DOB. **REQUIRES BUSINESS DECISION:** start date, signature, exact legal org line. Photo depends on #3 |
| 5 | Card expiration | **REQUIRES BUSINESS DECISION**. **RECOMMENDED:** no expiry in V1 if no rule exists |
| 6 | Card lifecycle | **RECOMMENDED:** not_issued / issued / replaced / revoked. Member status stays `active` only |
| 7 | Reissue semantics | **RECOMMENDED:** number persists; lost/stolen/data-change rotates card + verifier; extra reprint of current card may keep verifier |
| 8 | Digital vs printed | **RECOMMENDED:** one logical issuance, two representations |
| 9 | QR verification semantics | **LOCKED:** opaque public verifier ≠ auth ≠ ACL ≠ Secure Capability. **RECOMMENDED:** reserved public path + digest at rest |
| 10 | Public verification fields | **LOCKED** minimization: brand, name, official number, validity language. **RECOMMENDED:** no photo, no contact PII |
| 11 | Dedicated card entity vs Member fields | **RECOMMENDED:** dedicated issuance record. `card_issued` derived then deprecated |
| 12 | Legacy membership-number handling | **REQUIRES BUSINESS DECISION** after discovery. **LOCKED:** do not invent numbers or import in 3D-A |

Additional locked architecture (not in the numbered list but binding):

- Approval → `CreateAb2tiMemberFromApplication` remains the only Member factory.
- No Koperasi card; no Badge Koperasi in 3D.
- No Member Portal / User link required for a card.

---

## 22. Exact unresolved business questions

Only questions that cannot be closed from architecture:

1. What is the official AB2TI membership-number **format** (pattern, year, checksum, language of prefix)?
2. May a number ever be **changed** after it has appeared on a card?
3. Is a **portrait mandatory** before Kartu Anggota may be issued?
4. Do cards **expire**? If yes, from which date and for how long? What does expiry do to membership?
5. Exact **printed legal name** of the organization and whether a signature/stamp is required.
6. Physical **card size** / printer stock, and whether V1 includes mailed plastic or staff-printed/digital only.
7. For **already-distributed** paper cards with no QR: must 2.0 issue be treated as replacement of those cards?
8. Is there an existing **number register** for historical members? Who owns it?
9. Should public verification show **membership start date**?
10. Is extra **reprint** (second copy) allowed without rotating the QR?

Questions **not** asked here because they are deferred products: dues vs Rp 50.000, Koperasi bank account, Member Portal, Badge Koperasi, regional units, Member statuses beyond `active`.

---

## 23. Phase 3D-B implementation inputs

3D-B may implement Kartu Anggota **only** against this contract. It must **not**:

- change approval / revision / Secure Capability intake;
- use `members.id` or `reference_code` in QR;
- treat current `MBR-…` as blessed public format unless question 1 is answered by adopting it explicitly (not recommended);
- require User login for a Member to exist or for staff to issue a card;
- introduce Koperasi membership.

### Suggested 3D-B slices (when unblocked)

1. Replace or wrap `MembershipNumberGenerator` **after** format is locked (or document a temporary freeze: technical numbers stay internal until format exists — **do not print them as Kartu Anggota numbers**).
2. Add issuance record + opaque verification identifier (digest at rest).
3. Staff issue / replace / revoke UI; retire checkbox as source of truth.
4. Card-completion portrait if question 3 is yes.
5. Digital/print view from domain data; visual template from a separate design deliverable.
6. Public verification route + CMS reserved segment; generic failure; noindex; rate limit.
7. Derive `card_issued` or stop writing it except via issuance.

### What can wait vs what must be answered first

| 3D-B work | Blocked on |
|---|---|
| Printing or showing an **official** number on a card | Question 1 (format) |
| “Berlaku sampai” copy | Question 4 (expiry) |
| Blocking issue on missing photo | Question 3 |
| Physical stock layout | Question 6 |
| Legacy import | Questions 7–8 plus 1F manual-member decision |

**Issuance table + verifier + staff issue/revoke** can be engineered against **RECOMMENDED** defaults **without** printing public cards, as long as no official number format is published.

**Phase 3D-B should not ship a public Kartu Anggota / QR** until questions **1, 3, and 4** are answered (or explicitly accepted as the recommendations: keep generator private, photo optional, no expiry).

No migrations, models, numbering assignment, card records, UI, PDF, QR, verification routes, photo uploads, intake/approval/capability changes, Member Portal, User linkage, Koperasi, Badge Koperasi, payments, or legacy data load in Phase 3D-A.
