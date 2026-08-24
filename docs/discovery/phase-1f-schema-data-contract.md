# AB2TI 2.0 — Phase 1F Schema & Data Contract

**Status:** Persistence / data contract only. Not migrations. Not models. Not implementation.  
**Date:** 2026-08-20  
**Inputs:** Phase 1A–1E, **1E-A**, **1E-B** (all CLOSED), `ARCHITECTURE_BASELINE.md`, Constitution, ADRs 0001–0003, canonical migrations, Eloquent models, `config/acl.php`.

**Conflict priority:** Phase **1E-B** wins on Award. Phase **1E-A** wins on Secure Capability / authorization terminology. Older “thin Award as CMS / no Award tables” language in 1C/1D and any prior 1F draft is **superseded**.

This contract names conceptual persistence so a later, explicit task can write migrations. It is **not** `Schema::create`.

---

## 1. Executive Summary

Canonical schema today is **platform only**: Users, Spatie ACL, CMS (`pages`, `block_templates`, `heroes`, `welcome_sections`), File Manager (`files` + Spatie `media`), sessions/cache/jobs, Sanctum `personal_access_tokens`. There are **no** Membership, Publishing, Knowledge, Awards, or capability-grant tables.

Core V1 **new** persistence:

| Context | Conceptual structures |
|---|---|
| Membership | Application, application transitions, membership documents, Member |
| Platform security | Secure Capability Grant |
| Publishing | Publication |
| Agricultural Knowledge | Knowledge Article |
| **AB2TI Awards** | Award Program, Award Edition, Award Entry, Award Recognition |

Existing CMS/Tiptap/Media **stay**. Membership (and any future Award PII files) **must not** use File Manager as the access model.

Applicant data lives **on the Application**. Award participant data lives **on the Entry** (no Person platform). Grants bind **purpose + target type + id** (digest at rest). Approval creates **one** Member per approved application. Current Award edition is a **Program pointer**, not `currentYear()`.

**Award Category:** **DEFERRED — NO CORE V1 TABLE.**  
**Award judging/scoring tables:** **not** Core V1.

**Readiness:** **PASS / CLOSED.** No remaining persistence-architecture blockers. Remaining items are sequencing, product policy, or additive fields — not schema identity.

---

## 2. Authoritative Inputs

| Source | Role |
|---|---|
| 1A–1B | Legacy packet; Award archives on live site; IA |
| 1C | Aggregates; no Applicant table; Page ≠ Publication ≠ Knowledge |
| 1D | States; `domain.action`; mass-assignment |
| 1E | Laravel mapping; Approve Action; private documents |
| **1E-A** | Grants; purposes; digest; domain state wins |
| **1E-B** | Awards context; Program ≠ Edition; Entry ≠ Recognition; archive |
| Constitution §5 | Canonical schema = migration files |
| ADR 0002 / 0003 | No public registration; CMS reserved paths |
| Repository | 14 migrations, 6 models; **no** Award/competition tables; landing `#program` / `ServiceEvents` are placeholders |

**1C vs 1E (Applicant):** party, not a table; attributes on Application.  
**1C/1F-old vs 1E-B (Award):** operated Awards context with four Core V1 records, not CMS-only archive.

Staff ACL remains `domain.action`. Grant **purposes** are named intents (`membership-application-revision`, future `award-entry-revision`).

---

## 3. Locked Invariants

```
User / Account
  != Membership Applicant
  != Membership Application
  != AB2TI Member
  != Award Participant
  != Award Entry
  != Award Recognition
  != Authorization Role
  != Secure Capability Grant
```

| Concept | Persistence |
|---|---|
| User | Existing `users`. Staff login. Not membership or Award participation. |
| Membership Applicant | Columns on Application. No User. |
| Membership Application | Own lifecycle. Not Member. |
| Member | Created on approve. No User in V1. |
| Award Participant | Snapshot on Entry. No User required. |
| Award Entry | One Edition. Not Recognition. Not Membership Application. |
| Award Recognition | Official result. Not entry status. Not a score. |
| Role | Spatie. Not business identity. |
| Grant | Digest + purpose + constrained target. Not Sanctum PAT. |

Also locked: public input never sets admin/winner/eligibility; domain state wins over a live grant; File Manager ≠ membership documents; CMS Page ≠ Publication ≠ Knowledge ≠ Award Edition; new Award edition must not overwrite previous; current edition ≠ wall clock.

---

## 4. Current Schema Inventory

Canonical = `database/migrations`. Old local DBs may have leftover church tables (not authority).

| Structure | Classification |
|---|---|
| `users` (+ 2FA) | **KEEP AS PLATFORM** |
| `password_reset_tokens` | **KEEP AS PLATFORM** |
| `sessions` | **KEEP AS PLATFORM** (capability session payload; no new session table) |
| Spatie `permissions` / `roles` / pivots | **KEEP AS PLATFORM** |
| `personal_access_tokens` | **KEEP AS PLATFORM** — **DO NOT REUSE** as grants |
| `pages` | **KEEP AS PLATFORM** — **not** Award/Publication/Knowledge |
| `block_templates` | **KEEP AS PLATFORM** |
| `heroes` + `hero-backgrounds` | **KEEP AS PLATFORM** |
| `welcome_sections` (`image_url`) | **KEEP AS PLATFORM** / **ADAPT LATER** (Media) |
| `files` (`user_id` cascadeOnDelete, soft delete) | **KEEP AS PLATFORM** — **POTENTIAL CONFLICT** if used for KTP or Award PII |
| `media` (Spatie morphs) | **KEEP AS PLATFORM** editorial; **DO NOT REUSE** as membership-document access |
| `cache*`, `jobs*` | **IRRELEVANT TO PHASE 1F** (keep) |
| Empty `add_heroes_permissions` | **IRRELEVANT TO PHASE 1F** |
| Membership / Publication / Knowledge / Grant / Award tables | **Absent** — to add later |
| Leftover church (`congregations`, `letters`, `songs`, related) on old DBs | **LEGACY / DO NOT REUSE** |
| `users.current_team_id` (no teams table) | **KEEP**; not org/Award membership |
| Landing `#program`, `ServiceEvents.vue` | **GENERIC / SAFE** placeholders — **not** Award schema |

No AB2TI business tables and no Award remnants in canonical migrations.

---

## 5. Target Conceptual Data Map

```
PLATFORM
  User, Role/Permission, Session, Sanctum PAT     existing
  CMS Page / Hero / Welcome / BlockTemplate       existing
  File + Media (editorial)                        existing
  Secure Capability Grant                         NEW

MEMBERSHIP
  Membership Application                          NEW  (Applicant attributes embedded)
  Application transition                          NEW  (justified)
  Membership Document                             NEW  (metadata; private bytes)
  AB2TI Member                                    NEW

PUBLISHING / KNOWLEDGE
  Publication                                     NEW
  Knowledge Article                               NEW

AWARDS
  Award Program                                   NEW
  Award Edition                                   NEW
  Award Entry                                     NEW  (participant snapshot embedded)
  Award Recognition                               NEW
```

### Table-boundary justifications

| Concept | Table? | Why |
|---|---|---|
| Applicant / Award Participant | **No** | Snapshot on Application / Entry |
| Application / Edition status | **Column** | Lifecycle on the aggregate |
| Publication Type / Recognition kind | **Column** | Values, not engines |
| Award Category | **No V1 table** | 1E-B deferred |
| Cover/editorial image | **Spatie `media`** | Generic Media |
| Membership document | **Own table** | Different access than `files` |
| Grant purpose | **Column** + **code registry** | Not Spatie |
| Member ↔ User | **No V1 column** | Reserved |
| Award Entry ↔ Member | **No V1 column** | Eligibility **DR** |
| Program (generic Pelatihan…) | **No** | Not a Programs engine |
| Judging / scores | **No** | External in V1 |
| Award private files | **No** unless V1 requires uploads | 1E-B: not confirmed |

### Cardinality (Core V1)

| From | To | Cardinality | Rule |
|---|---|---|---|
| Application | Documents | 1:n | Membership owns meaning |
| Application | Member | 1:0..1 | Unique source application |
| Program | Editions | 1:n | Historical retain |
| Program | Current Edition | 1:0..1 | Pointer; not year() |
| Edition | Entries | 1:n | Entry cannot change edition |
| Edition | Recognitions | 1:n | Official results |
| Recognition | Entry | n:0..1 | Nullable FK; feature may require |
| Grant | Target | n:1 | Constrained type+id; **no** morph-to-anything |
| Publication/Knowledge/Page/Award | Media | 1:0..n | Editorial only |
| Media | Applications/Members/Grants/Entries | **Forbidden ownership** | |

---

# Membership

## 6. Membership Application Contract

**Table (conceptual):** `membership_applications`  
No Applicant table.

**Identity:** bigint PK (staff, behind ACL). Unique opaque `reference_code` for support — **not** authorization. **No** durable edit token on the row.

Exact extra biographic fields beyond the **legacy packet + 1E-A revision examples** remain **DECISION REQUIRED**. Minimum justified set below. Do not start with an unbounded JSON entity bag.

### APPLICANT-WRITABLE

Legal name; email; phone; address; optional applicant notes; documents via `membership_documents` (photo, identity scan, payment proof).

### STAFF-WRITABLE

Internal review notes; revision instructions/reason; rejection reason; optional process remarks (not a finance ledger).

### SYSTEM-WRITTEN

Status (commands only); `submitted_at`; `review_started_at` / `reviewed_by`; `revision_requested_at`; `resubmitted_at`; `approved_at` / `approved_by`; `rejected_at` / `rejected_by`; timestamps; `reference_code`.

### IMMUTABLE / DERIVED

Terminal `approved`/`rejected` status; Member linkage via Member.source_application; admin flags never from applicant input.

**Out:** NIK as required column, religion, congregation, gender, birth date, region, member class, dues, cooperative shares — unless product later lists them.

**Soft delete:** none in V1. No public delete.

**Search:** staff filter `status`, sort `submitted_at`, lookup email/name/`reference_code`.

**Concurrency:** Approve uses row lock, not a version column.

Email **not** unique on applications (reapply = new Application — 1C hypothesis).

---

## 7. Membership Lifecycle Contract

Status on `membership_applications.status`. Not Publication status, not Member status.

| State | Terminal? | Applicant revision? | Capability? | Who | Metadata |
|---|---|---|---|---|---|
| `submitted` | No | No | No | Visitor Submit | `submitted_at` |
| `under_review` | No | No | No | Staff Start review; resubmit lands here | `review_started_at`, `reviewed_by` |
| `revision_requested` | No | Yes, limited | Yes if product uses grants | Staff Request revision | `revision_requested_at`, reason |
| `rejected` | **Yes** | No | Writes denied | Staff Reject | `rejected_at`, `rejected_by`, reason |
| `approved` | **Yes** | No | Writes denied | Staff Approve from `under_review` only | `approved_at`, `approved_by`; Member row |

Not in V1: `draft`, `withdrawn`, `expired`.

```
submitted → under_review
under_review → revision_requested | rejected | approved
revision_requested → under_review   (resubmit)
approved / rejected → (none)
```

**1E:** approve only from `under_review`.  
Rejected reapply = new Application (**product DR**, schema must not unique-constrain email).

---

## 8. Membership Revision Contract

| Question | Contract |
|---|---|
| Target | That Membership Application |
| Purpose | `membership-application-revision` |
| Operations | Domain: view/edit applicant-writable revision fields, upload requested docs, resubmit — **not** stored as Spatie names |
| Expiry / revoke / consume | Grant `expires_at`, `revoked_at`, `consumed_at` |
| Reuse | 1E-A assumption: reusable until consume/revoke/expiry |
| Consume on resubmit | **DECISION REQUIRED** (column exists either way) |
| Issuer | Staff User |
| State | Writes only if Application is `revision_requested` |
| Rotation | Optional **purpose policy** (e.g. revision grant may revoke siblings on reissue). **Not** a DB unique. Historical grants remain |
| Secret | **Digest only.** No raw token on Application |

Staff-mediated revision remains a valid product choice; intake tables do not depend on grants.

---

## 9. Membership Document Contract

**Table:** `membership_documents`  
Metadata vs private-disk bytes. **Not** `files`/`media` access model.

| Concept | Why |
|---|---|
| id, `membership_application_id` | Owner while intake; remains after approve |
| `document_type` | `applicant_photo`, `identity_document`, `payment_proof` |
| disk + object_key | No public path |
| original / display filename | Support; key is not the original name |
| MIME, size, optional checksum | Validation / integrity |
| `uploaded_at`, `uploaded_by_context` (`applicant`\|`staff`), optional `uploaded_by_user_id` | Audit |
| Replacement | One **current** per `(application, type)`; prior `replaced_at` |

**No `member_id` in V1.** Bytes stay on the source Application.

Access: staff `membership.documents.*` (later); applicant only on submit/revision grant. **DR:** applicant download of existing identity files.

Retention/soft-delete: **DR**. Must allow delete without moving files to public disk.

---

## 10. AB2TI Member Contract

**Table:** `members`  
Created only by Approve.

Canonical migrations contain **no** `members` table. Temporary isolated migrate (Phase 0A baseline) produced only platform tables. There is **no repository collision**. Proposed name remains `members`. Leftover church tables on some old local DBs (`congregations`, `letters`, `songs`, and related) are operator recovery (`LOCAL_DATABASE_RECOVERY.md`), not a 1F schema blocker. If an operator later finds an incompatible leftover `members`, they must **not reuse it**; that is an environment issue, not a reason to rename the canonical contract now.

| Concept | Contract |
|---|---|
| PK | bigint |
| `membership_number` | Unique **string** business key, system-written. **Format/generation is feature policy**, not a migration blocker. Store UNIQUE; do not encode format in the schema |
| `source_application_id` | **Unique** FK. **NOT NULL** in V1 (no manual members) |
| `status` | `active` only |
| `recognized_at` | Same instant as application `approved_at` |
| `card_issued` / optional `card_issued_at` | Default false |
| Contact copy | legal name, **email (contact only)**, phone, address **as of approval** |
| Audit | timestamps; `created_by` = approving User |

**Snapshot vs master:** Application keeps history, documents, staff notes, payment-proof meaning. Member holds current contact + number + card flag. Do not copy KTP bytes onto Member.

**Member email is CONTACT DATA.** It is not authentication identity, not membership identity, not a uniqueness key, and not User linkage. No unique index on Member email unless a later CLOSED product rule requires it. A non-unique index for staff search is optional. Membership identity is the Member row + unique `membership_number` (+ unique source application).

Deactivation: not V1. Manual member without application: **DR**; V1 keeps `source_application_id` NOT NULL.

---

## 11. Membership Approval Invariants

| Guarantee | DATABASE CONSTRAINT | TRANSACTION | APPLICATION OPERATION | AUTHORIZATION |
|---|---|---|---|---|
| Eligible (`under_review`) | Status allow-list | Lock row | Action refuses other states | `membership.applications.approve` |
| One Member | **UNIQUE** `source_application_id` | Insert Member + set approved together | Idempotent second approve | Staff only |
| No duplicate on race | Unique + lock | `lockForUpdate` | One insert wins | — |
| Public cannot approve | Status not applicant-fillable | — | Separate commands | No public approve permission |
| No User / role / Koperasi | — | — | Action must not write those | — |

Reject / request revision must not insert `members`.

---

## 12. Future Member–User Link

**RESERVED — NOT CREATED IN CORE V1**

No `user_id` on `members` in the first migration. Later additive nullable unique-or-1:1 column. Approval never inserts `users`. No Spatie `member`. Unlink must not delete Member. Do not infer link from email.

---

# Platform Security

## 13. Secure Capability Grant Contract

**Table:** `secure_capability_grants`  
Not Sanctum. Not Spatie. Signed URLs persist **nothing** here.

### Fields (why)

| Field | Why |
|---|---|
| `id` | Session/audit identity (not the secret) |
| `purpose` | Named intent from **code registry** |
| `target_type` | Allow-list. V1: `membership_application`. Future: `award_entry` |
| `target_id` | Bound resource |
| `secret_digest` | Verify without storing usable secret |
| `issued_by` / `issued_at` | Audit |
| `expires_at` | Fail expired |
| `revoked_at` | Kill switch |
| `consumed_at` | Single-use / completion |
| `last_used_at` | Use tracking without logging secret |
| timestamps | Ordinary |

**Omitted:** raw bearer secret (not persisted); Spatie/`abilities` JSON; `max_uses` (speculative); open metadata JSON. Usage policy inferred from `consumed_at` + purpose registry (**assumption**).

**Registry (not a table):** purpose → allowed `target_type` → owning domain → implied operations. Unknown purpose → deny.

**Not** open `morphTo`. `target_type` is **allow-listed**. Unrestricted morph access is forbidden. No FK to target (avoids cascade/circular ownership). Target existence/type is validated by the owning domain at issue and at use.

**Uniqueness:** **`secret_digest` UNIQUE** is the only grant uniqueness invariant. Raw secret is not stored.

**Multiple grants:** historical, rotated, revoked, expired, and consumed grants **may coexist** for the same purpose+target. There is **no** universal “one live grant per target” database rule. If a **named purpose** later requires a single effective grant, that is **purpose/domain policy** at issue/use time (e.g. revoke siblings when issuing), not a partial unique index (MySQL/MariaDB do not provide PostgreSQL-style partial uniques; do not invent one).

**Effective-use validation** (every sensitive operation): purpose allow-listed + constrained target (type+id match) + operation allowed by that purpose’s owning domain + not expired + not revoked + consumption/usage policy satisfied + **domain state still valid**.

**Indexes (query, not uniqueness):** `(target_type, target_id, purpose)` to find grants for a resource; `expires_at`; optional `revoked_at` / `consumed_at` if listing live grants.

Session stores grant `id` in existing `sessions`. Session TTL must not outlive `expires_at`.

---

# Publishing & Knowledge

## 14. Publication Contract

**Table:** `publications` — **not** `pages`.

| Concept | Contract |
|---|---|
| `type` | `berita` \| `kegiatan` \| `siaran_pers` \| `liputan_media` (last two reserved values) |
| `title`, unique `slug` (this table only) | Required |
| `excerpt` | Optional |
| `content` | Tiptap HTML |
| `status` | `draft` \| `published` |
| `published_at` | Set on publish. **ASSUMPTION:** unpublish keeps last `published_at`; visibility uses `status` |
| `created_by` / `updated_by` | Staff User |
| SEO | `meta_title`, `meta_description` nullable |
| Cover | Spatie collection later |

No tags, schedule, program_id, archive status, revision table. Prefer unpublish over delete of published (**DR**). Index `(status, type, published_at)`.

---

## 15. Knowledge Article Contract

**Table:** `knowledge_articles`  
Same *shape* as Publication (title, slug, optional summary, HTML, draft/published, authors, SEO, Spatie cover). **No** `type`. Separate slug space. Never listed as Publikasi. No variety/crop entities.

---

# AB2TI Awards

## 16. Award Program Contract

**Table:** `award_programs`  
Long-lived identity. **Not** a generic Programs engine. Core V1 **ASSUMPTION:** one row (AB2TI Award). Schema may allow more rows without requiring a platform.

| Concept | Contract |
|---|---|
| id | Internal PK |
| `slug` | Unique public identifier (e.g. `ab2ti-award`). Stable. Not authorization |
| `name` | Canonical display name. Brand “AB2TI Award” vs “ICBB-AB2TI Award” is a **value**, not two tables |
| `summary` / `content` | Optional evergreen Tiptap HTML — **Award-owned**, not `pages` |
| `is_active` | Staff can hide the program landing without deleting history |
| `current_edition_id` | Nullable FK → `award_editions`. **Configured current** (§18) |
| `current_edition_set_at` / `current_edition_set_by` | Audit pointer changes |
| timestamps | Yes |

Public discoverability: active program + published (non-`draft`) editions. Historical editions remain even if another is current.

---

## 17. Award Edition Contract

**Table:** `award_editions`  
One cycle. **New edition = new row.** No `award_2027` tables. Year is **not** the only identifier (`slug`/`label` may be `2026` or `2026-2027`).

Confirmed lifecycle (column `status`):

```
draft → announced → registration_open → registration_closed → completed
```

No mandatory `assessment`. `archived` is **not** a status that hides/deletes.

| Concept | Contract |
|---|---|
| id | PK |
| `award_program_id` | Required FK (restrict delete of program with editions — or restrict both ways) |
| `slug` | Unique **per program** `(program_id, slug)` |
| `label` | Display (“2026”, “Edisi 2026/2027”) |
| `period_start` / `period_end` | Optional dates; **not** used to compute current |
| `theme_title` | Optional |
| `status` | Five values above |
| `registration_opens_at` / `registration_closes_at` | Optional windows (display/validation aid). **State remains authoritative** for whether submit is allowed |
| `content` | Edition Tiptap HTML (rules, jury copy, etc.) |
| `announced_at`, `registration_opened_at`, `registration_closed_at`, `completed_at` | System timestamps on transitions |
| `created_by` / `updated_by` | Staff |
| timestamps | Yes |

**Public:** `draft` not public; all later states public for **that edition’s** page. Submit only if `registration_open` (and optional window if both used — **feature DR** if they disagree; **ASSUMPTION:** status wins).

**Terminal:** `completed` for normal flow. Reopen after completed: **deny by default** (1E-B). Unpublish/withdraw: **DR**; not delete.

**Indexes:** `(award_program_id, status)`, `(award_program_id, slug)` unique.

---

## 18. Current Edition Contract

**Chosen design:** **program-level pointer** `award_programs.current_edition_id` → `award_editions.id`.

### Mandatory same-program invariant

If `AwardProgram.current_edition_id` is **non-null**, the referenced `AwardEdition` **MUST** have `award_program_id` equal to that program’s id.

A normal FK to `award_editions.id` alone does **not** guarantee this (any edition of any program could be pointed at).

**Enforcement recommendation:** **mandatory application operation** inside the “set current edition” transaction — lock the program row, load the edition, assert `edition.award_program_id === program.id`, then write the pointer + `current_edition_set_at/by`.

**Why not a composite/circular DB FK:** a composite FK `(current_edition_id, program.id) → (editions.id, editions.award_program_id)` is possible in theory but **circular** (`editions` already FK to `programs`). That complexity is not justified for V1. Application assertion + tests are the contract.

| Concern | Behavior |
|---|---|
| Uniqueness | At most one current per program (single FK) |
| Null | Allowed (setup; landing shows archive/evergreen only) |
| Switch | Update pointer + audit fields only. **Edition rows unchanged** (archive intact) |
| Race | Lock the **program** row when switching; last committed pointer wins; both writers must pass same-program assert |
| Same-program | **Invariant above** — reject/abort if edition belongs to another program |
| History | Pointer change never rewrites past editions, entries, or recognitions |
| Delete | Edition delete remains **restrict** if entries/recognitions exist. If an edition is the current pointer, **clear or retarget the pointer first**, then restrict-or-refuse edition delete. Do not cascade-delete the program |
| `is_current` on edition | **Rejected** for V1 (two-true race; MySQL partial unique unsupported) |

**Forbidden:** `year == currentYear()`; replacing one CMS page as “the” award.

---

## 19. Award Entry Contract

**Table:** `award_entries`  
Exactly one `award_edition_id`. **Not** Membership Application. **Not** Recognition. Winner is **not** a status.

Entry mode (self / nomination / org / individual) **not encoded as required schema**. Optional `organization_name` is a contact field, not a mode engine.

### PARTICIPANT-WRITABLE

Display/legal name; email; phone; optional organization; optional participant message; (files **not** in V1 unless product requires — §38).

### STAFF-WRITABLE

Internal notes; eligibility decision **via command** (not generic mass-assign of status from public).

### SYSTEM-WRITTEN

`status`; `reference_code`; `submitted_at`; `review_started_at` / `reviewed_by`; `decided_at` / `decided_by`; timestamps.

### IMMUTABLE / DERIVED

`award_edition_id` after create; Recognition linkage queried from recognitions; public winner flags **never** on entry.

| State | Meaning |
|---|---|
| `submitted` | Received |
| `under_review` | Staff verification |
| `eligible` | In the considered set |
| `ineligible` | Not accepted; reason staff-owned |

```
submitted → under_review → eligible | ineligible
```

No `winner`. Writes after `registration_closed` denied except staff review. Closed/completed edition + live grant = **DENY** participant writes.

**Uniqueness:** unique `reference_code`. Email is **contact**, not unique per edition. No User id required.

**Public exposure:** entries are **not** a public directory by default. Public sees Recognitions, not the queue.

**Search:** staff by edition, status, email, name, `reference_code`.

**Retention:** stay with the edition forever for archive. Delete entry: **restrict** if a Recognition points at it (§21).

No raw edit token column.

---

## 20. Award Participant Representation

**ENTRY SNAPSHOT** — not a reusable Participant/Person aggregate.

**Why:** 1E-B does not require long-lived cross-edition identity. Core V1 has no Award login. A Person table would become a third identity beside User and Member without a product need.

Contact on the Entry is the source for staff review. Public winner display uses **Recognition** fields (may copy a display name at record-result time) so later entry edits cannot silently rewrite the public plaque.

Optional later `member_id` on Entry is **RESERVED — NOT CREATED IN CORE V1** (eligibility **DR**).

---

## 21. Award Recognition Contract

**Table:** `award_recognitions`  
Official, edition-scoped, staff-recorded. Not a score. Not entry status.

| Concept | Contract |
|---|---|
| id | PK |
| `award_edition_id` | **Required** FK |
| `award_entry_id` | **Nullable** FK. Schema **permits** Recognition without Entry; **feature may forbid** until product decides (1E-B). **When non-null:** same-edition invariant below |
| `recipient_display_name` | Required for public listing (from entry snapshot or staff-entered) |
| `recognition_kind` | String. Core values: `winner` required-capable; allow `runner_up`, `finalist`, `special` without a taxonomy engine. V1 may only **use** `winner` |
| `sort_order` | Optional display order (multiple recipients) |
| `title` / `citation` / `public_description` | Optional; public copy. **Not** staff-only notes |
| `recorded_at` / `recorded_by` | Staff audit |
| `updated_at` / `updated_by` | Corrections attributed |
| Cover | Optional Spatie later (winner photo editorial) |

### Mandatory same-edition invariant (when `award_entry_id` is present)

```
Recognition.award_edition_id MUST equal Entry.award_edition_id
```

Nullable `entry_id` remains approved (Recognition without Entry is a **feature** rule, not a schema prohibition).

**Enforcement recommendation: database composite FK** — practical, not circular.

1. Unique index on `award_entries (id, award_edition_id)` (id is already PK; the extra unique exists **so** a composite FK can reference it).  
2. FK `award_recognitions (award_entry_id, award_edition_id)` → `award_entries (id, award_edition_id)`.  
3. When `award_entry_id` is NULL, InnoDB/MariaDB **does not apply** the composite FK check (NULL in an FK column). Recognition-without-entry stays allowed.  
4. When `award_entry_id` is set, the edition on the recognition **cannot** disagree with the entry’s edition.

Also keep the same check in the **record-Recognition operation** (defense in depth; clear error). Do not rely on Vue.

**Publication:** public list when **edition is `completed`** (and program/edition not withdrawn). Do not make winner lists depend on the current-edition pointer.

**Uniqueness:** **not** unique `(edition, kind)` (multiple winners allowed). Optional unique `(edition, entry_id)` where entry_id NOT NULL — **ASSUMPTION skip** (one person could receive special + winner); application can constrain later.

**Historical stability:** update is staff-attributed; do not delete completed-edition recognitions casually (**restrict**).

---

## 22. Award Category Decision

**DEFERRED — NO CORE V1 TABLE**

No repository evidence of a stable category list. Recognitions and entries work without `category_id`.

**Future-compatible:** add `award_edition_categories` + optional FKs later without rewriting Program/Edition identity. Do **not** add a free-text `category` grab-bag on Recognition in V1 (avoids fake taxonomy). Display grouping can wait.

---

## 23. Award Judging Boundary

**No Core V1 tables** for judges, criteria, rubrics, scores, panels, rankings.

Staff can: set Entry `eligible`/`ineligible`; operationally receive an external decision; **insert/update Recognition**.

**Future extension (no schema now):** judging records would **reference** Edition + Entry; they must **not** replace Recognition as public truth.

---

## 24. Award Secure Capability Compatibility

One-shot submit: **no grant required**.

Future purpose `award-entry-revision` → `target_type = award_entry`. Allow-list that type in the registry when needed. **Not** Core V1 required.

Entry table **must not** contain raw permanent tokens, Spatie permission strings, or shared secrets.

Domain state still wins (edition not `registration_open` / entry not revisable → DENY).

---

## 25. Award Historical Archive Contract

- Many `award_editions` per program, retained.  
- Stable `(program slug, edition slug)` for public identity.  
- Recognitions persist on their edition.  
- Editorial `content` on program/edition rows persists.  
- Media attached to those records persists independently of current pointer.  
- Changing `current_edition_id` **does not** mutate other editions.  
- `completed` editions remain queryable/public.  
- Archived ≠ deleted ≠ automatically private.

Delete program/edition with children: **restrict**.

---

# Shared Platform

## 26. CMS / Tiptap Compatibility

**KEEP** `pages.content`, `block_templates.html_content`, heroes/welcome as today.

| Record | HTML owner | Visibility |
|---|---|---|
| `pages` | CMS | `is_active` |
| Publication / Knowledge | Own tables | `draft`/`published` |
| Award Program / Edition | Own `content` columns | Program `is_active` + edition `status` |

**Shared editor ≠ shared table.** Award HTML is not stored in `pages`. Do not occupy `award` as a CMS slug once routes exist (reserve later; do not edit `config/cms.php` now).

---

## 27. Generic Media References

```
CMS / Publishing / Knowledge / Awards (editorial) --> media
Media ✗ owns Application, membership_documents, Member, Grant, Entry, Recognition identity
```

| Verdict | |
|---|---|
| `files` + `media` | **NO CHANGE** for editorial |
| Membership documents | Separate table |
| Award participant PII files | **Not V1** unless product requires; then membership-style store, not File Manager |
| Winner/jury photos | Editorial Media OK |

`files.user_id` cascade is OK for editorial. Membership docs: do not cascade-delete PII on User delete.

---

# Cross-Cutting Data Rules

## 28. Data Ownership Matrix

| DATA | OWNER | MAY REFERENCE | MUST NOT OWN | DELETE BEHAVIOR | NOTES |
|---|---|---|---|---|---|
| User | Identity | — | Member, Entry, Recognition | No cascade to Member/Award/Application | Grant `issued_by` null/restrict |
| Membership Application | Membership | Reviewer User | User, Media library | **Restrict** if Member exists | |
| Membership Document | Membership | Application | File Manager | Authorized path only | |
| Member | Membership | Source Application | Application history | Application delete must not cascade Member | |
| Grant | Platform | Target id, issuer | Target row | Revoke ≠ delete target | No FK to target |
| Publication | Publishing | User, Media | Page, Knowledge, Award | Unpublish preferred | |
| Knowledge Article | Knowledge | User, Media | Publication, Page | Same | |
| CMS Page | CMS | Media | Award/Publication/Member | Existing | |
| Media | Media | User | Business aggregates | Soft/force-delete as today | |
| Award Program | Awards | Current Edition, Media | Entries | Restrict if editions exist | Pointer change ≠ delete |
| Award Edition | Awards | Program, Media | — | Restrict if entries or recognitions | |
| Award Entry | Awards | Edition | Recognition | Restrict if Recognition.entry_id set | Snapshot identity |
| Award Recognition | Awards | Edition, optional Entry, Media | Entry row | Restrict on completed editions | Public archive |

Changing current edition **must not** affect previous editions’ rows.

---

## 29. Identifier Strategy

Unguessable ≠ authorization.

| Aggregate | Public | Internal |
|---|---|---|
| Page | `slug` | bigint |
| Publication / Knowledge | own `slug` | bigint |
| Application | `reference_code` | bigint |
| Member | `membership_number` (public? **DR**) | bigint |
| Grant | bearer secret once; then session | bigint |
| Membership document | no public URL | bigint |
| Award Program | `slug` | bigint |
| Award Edition | `slug` scoped to program | bigint |
| Award Entry | `reference_code` (staff/support); not a public directory key | bigint |
| Recognition | listed under edition URL; PK staff | bigint |

No UUID PKs for style. Opaque codes where enumeration of **intake** ids would leak existence in public/capability URLs.

---

## 30. Enum / Status Contract

String columns + later PHP enums. **No** MySQL native ENUM. **No** shared status table.

| Set | Values | Owner | Later PHP Enum? | Transitions |
|---|---|---|---|---|
| Application status | `submitted`, `under_review`, `revision_requested`, `rejected`, `approved` | Membership | Yes | §7 |
| Member status | `active` | Membership | When more exist | none V1 |
| Card | boolean | Membership | No | Staff |
| Publication status | `draft`, `published` | Publishing | Yes | unpublish → draft |
| Publication type | `berita`, `kegiatan`, `siaran_pers`, `liputan_media` | Publishing | Yes | not workflow |
| Knowledge status | `draft`, `published` | Knowledge | Yes | separate enum |
| Document type | `applicant_photo`, `identity_document`, `payment_proof` | Membership | Yes | — |
| Grant purpose | `membership-application-revision` (+ later `award-entry-revision`) | Platform registry | Yes | not Spatie |
| Grant target_type | `membership_application` (+ later `award_entry`) | Platform | Yes | allow-list |
| Award edition status | `draft`, `announced`, `registration_open`, `registration_closed`, `completed` | Awards | Yes | §17 |
| Award entry status | `submitted`, `under_review`, `eligible`, `ineligible` | Awards | Yes | §19 |
| Recognition kind | `winner`, `runner_up`, `finalist`, `special` | Awards | Yes | not a workflow; V1 may use a subset |
| Upload actor | `applicant`, `staff` | Membership | Optional | — |
| Page visibility | `is_active` boolean | CMS | No | keep |

---

## 31. Index & Constraint Contract

| Structure | PK | Uniques | FKs | Indexes | Races |
|---|---|---|---|---|---|
| `membership_applications` | id | `reference_code` | reviewer/approver Users null/restrict | status, submitted_at, email | Approve `lockForUpdate` |
| `membership_application_transitions` | id | — | application restrict | application_id, created_at | append-only |
| `membership_documents` | id | current-per-type (app-enforced / careful unique) | application restrict | application_id, type | replacement txn |
| `members` | id | `source_application_id`, `membership_number` | application **restrict**; created_by User | optional non-unique email (search) | unique source + lock |
| `secure_capability_grants` | id | **`secret_digest` only** | issued_by User set null; **no target FK** | (target_type, target_id, purpose), expires_at | no universal one-live-grant unique |
| `publications` | id | slug | created_by/updated_by restrict | (status, type, published_at) | — |
| `knowledge_articles` | id | slug | same | (status, published_at) | — |
| `award_programs` | id | slug | `current_edition_id` → editions.id; **clear pointer before edition delete**; **restrict** edition delete with children | — | lock program; **app same-program assert** |
| `award_editions` | id | `(award_program_id, slug)` | program restrict | (program_id, status) | — |
| `award_entries` | id | `reference_code`; unique `(id, award_edition_id)` for composite FK | edition restrict | (edition_id, status); optional non-unique email | — |
| `award_recognitions` | id | — | edition restrict; **composite FK** `(award_entry_id, award_edition_id)` → entries `(id, award_edition_id)` when entry set; restrict delete of referenced entry | (edition_id, kind, sort_order) | composite FK + operation check |

**Current-edition same-program:** FK to edition id + **mandatory application invariant** (lock program). Not a composite circular FK.

**Recognition same-edition:** composite FK when `award_entry_id` is set.

Duplicate membership: unique `members.source_application_id`.

Member email: **not** unique. Award entry email: **not** unique.

---

## 32. Audit / History Contract

No event store.

| Area | Durability |
|---|---|
| Membership transitions / revision snapshots | `membership_application_transitions` |
| Approve + Member | Application columns + Member + transition |
| Card issued | Member timestamps |
| Member contact update | `updated_at` |
| Grant issue/revoke/consume/use | Grant columns |
| Failed redeem | Logs/rate-limit; optional events table **DEFERRED** |
| Publish/unpublish content | status + published_at + updated_by |
| Award edition transitions | Edition timestamp columns + actor on `updated_by` |
| Current-edition switch | `current_edition_set_at/by` on program |
| Entry eligibility | `decided_at/by` + notes; flip history **not** a required extra table in V1 |
| Recognition create/correct | `recorded_by` / `updated_by` |

Do not log bearer secrets. Do not log unnecessary Award participant PII in generic app logs.

---

## 33. Privacy / Data Minimization

**Membership:** keep name, email, phone, address, three document files. Member **email is contact**, not identity. Do not inherit Presbi PII. Do not add NIK/birth date unless product asks. Payment proof is a document, not a ledger. Do not copy KTP onto Member. Do not log grant secrets.

**Awards:** participant contact is **staff/intake**, not automatically public. Public archive shows Recognition `recipient_display_name` (+ citation), **not** email/phone. Do not assume winner = publish the Entry row. No Award PII document store in V1.

---

## 34. Legacy Schema Collision Review

| Item | Class |
|---|---|
| Leftover church tables on old DBs | **DO NOT REUSE** / **SAFE TO REMOVE LATER** (consented recovery) — **not** a 1F schema blocker |
| `members` table name | **GENERIC / SAFE** in canonical migrations (table **does not exist**). Proposed Core V1 name remains `members`. Hypothetical leftover on an old local DB is operator recovery, not a contract rename to `ab2ti_members` |
| `personal_access_tokens` | **GENERIC / SAFE** — **DO NOT REUSE** for grants |
| `files` / `media` | **GENERIC / SAFE** editorial — **DO NOT REUSE** for KTP |
| Seeded role `user` | **GENERIC / SAFE** if documents isolated |
| `pages` slugs `sermons`/`events` | **SAFE TO REMOVE LATER** (IA ADR) |
| `#program` / `ServiceEvents` | **GENERIC / SAFE** — do not treat as Award editions |
| Stale `culture.*` / `artikel.*` permission rows | **SAFE TO REMOVE LATER** |
| No competition/registration tables in migrations | **GENERIC / SAFE** |

No cleanup in this phase.

---

# Target Schema

## 35. Proposed Core V1 Table Set

### Existing (unchanged)

`users`, Spatie ACL, `pages`, `block_templates`, `heroes`, `welcome_sections`, `files`, `media`, `sessions`, `personal_access_tokens`, cache/jobs.

### `membership_applications` — CORE V1 — Membership

Intake + applicant attributes + lifecycle. Key: reference_code, contact, status, review/approval metadata. 1:n documents; 0..1 member.

### `membership_application_transitions` — CORE V1 — Membership

from/to status, actor, reason, optional applicant snapshot JSON.

### `membership_documents` — CORE V1 — Membership

Private metadata; not File Manager.

### `members` — CORE V1 — Membership

Unique source_application_id + membership_number; status `active`; contact master.

### `secure_capability_grants` — CORE V1 — Platform

purpose, target_type/id, digest, expiry, revoke, consume. First batch may wait on revision-channel product choice.

### `publications` — CORE V1 — Publishing

type, slug unique, HTML, draft/published.

### `knowledge_articles` — CORE V1 — Knowledge

Parallel without type.

### `award_programs` — CORE V1 — Awards

slug, name, evergreen content, `current_edition_id`.

### `award_editions` — CORE V1 — Awards

program_id, slug per program, label, status, optional windows, content, transition timestamps.

### `award_entries` — CORE V1 — Awards

edition_id, participant snapshot, status, reference_code. No token. No winner status.

### `award_recognitions` — CORE V1 — Awards

edition_id, nullable entry_id, display name, kind, sort_order, citation, recorded_by.

### Not in Core V1

`award_categories`, judges/scores, Award private documents, Applicant/Participant tables, `user_id` on members, Koperasi/Community/Programs engine, grant events table.

---

## 36. Relationship Map

```
users  <── Spatie roles/permissions
sessions
personal_access_tokens     (User API ONLY — not grants)

pages / heroes / welcome / blocks
files ──< media >── pages, files, heroes
                 >── publications, knowledge_articles   (later collections)
                 >── award_programs, award_editions,
                     award_recognitions (editorial photos)

secure_capability_grants
    purpose + target_type + target_id   (no FK cascade)
    issued_by ──> users
    targets (logical): membership_applications
                       [future award_entries]

membership_applications
    1 ──< membership_documents
    1 ──< membership_application_transitions
    1 ── 0..1 members     UNIQUE source_application_id
                          FK restrict; no User on members in V1

award_programs
    1 ──< award_editions
    current_edition_id ──> 0..1 award_editions     (configured current)

award_editions
    1 ──< award_entries
    1 ──< award_recognitions

award_recognitions
    0..1 ──> award_entries     (nullable; same edition)

FORBIDDEN
  pages ──store──> editions / publications / members
  media ──own──> membership_documents / entries / grants
  members ──require──> users
  applications ──cascade──> members
  grants ──delete──> targets
  current_edition pointer ──mutate──> other editions
  award_entries.status = winner
  membership_applications reused as award_entries
```

---

## 37. Schema Decision Register

### CONFIRMED DATA DECISIONS

- No Applicant or Award Participant tables.  
- Application status ≠ Member status ≠ Edition status ≠ Entry status.  
- Member unique per source application; no User on approve.  
- Membership documents ≠ File Manager.  
- Grants: unique `secret_digest`; raw secret not stored; historical grants allowed; allow-listed `target_type`.  
- Member email is **contact data**, not a uniqueness/identity key.  
- `membership_number` UNIQUE (string); format is feature policy.  
- Current edition same-program: **application invariant** (lock program).  
- Recognition→Entry same-edition: **composite FK** when entry_id set.  
- Table name `members` (no canonical collision).  
- Publication ≠ Knowledge ≠ Page.  
- Awards: Program, Edition, Entry, Recognition are Core V1 tables.  
- Current edition = program pointer, not year().  
- No per-year Award tables.  
- No Core V1 category/judging/score tables.  
- Entry winner is not a status.  
- CMS does not own Award lifecycle.  
- 1E-B overrides older Award deferrals.

### SCHEMA ASSUMPTIONS

- Minimum membership applicant columns + three document types.  
- `reference_code` on applications and award entries.  
- `source_application_id` NOT NULL.  
- Purpose `membership-application-revision` **may** revoke siblings on reissue (**policy**, not DB unique).  
- No FK from grants to targets.  
- Table name `members`.  
- Unpublish keeps `published_at`.  
- One Award Program row in V1.  
- Edition **status** wins over registration window timestamps if they disagree.  
- Recognition.entry_id nullable.  
- Recognition kinds stored as strings; V1 may only populate `winner`.  
- Program pointer (not edition `is_current` flag).

### DECISIONS REQUIRED BEFORE MIGRATION

**None.** Canonical schema has no identity/uniqueness blockers.

Reclassified former list:

| Former “blocker” | Reclass |
|---|---|
| Local `members` name | **Not a blocker.** No incompatible `members` table in repository migrations. Keep `members`. Do not reuse leftover church tables if an operator’s old DB has them. |
| Grants in migration wave 1 | **Migration sequencing**, not persistence architecture. Table contract is defined; order of migrate files is an implementation task. |
| Live-grant unique strategy | **Not a schema unique.** `secret_digest` UNIQUE only. One-effective-grant is purpose/domain policy if ever required. |
| Member email uniqueness | **Not a blocker.** Email is contact; no unique index. |
| Membership number format | **Feature implementation.** Column is unique string. |
| Extra applicant / Award Entry fields | **FEATURE REQUIREMENT / ADDITIVE MIGRATION RISK.** Base identity/ownership/uniqueness does not depend on them. |

### DECISIONS REQUIRED BEFORE FEATURE IMPLEMENTATION

See §38. Intake field lists, revision consume policy, Award brand/routes/entry mode, eligibility vs membership, recognition-without-entry **product rule** (schema already nullable), reopen/unpublish, file uploads, role split, membership-number **generation**, whether a purpose revokes sibling grants on reissue.

### DEFERRED / FUTURE

Member self-service; User provisioning; Koperasi; Community; Award judging/judge portal/rubrics/ranking; generic Programs engine; Award Categories; Award private documents; `user_id` on members; `member_id` on entries; grant events table; capability `award-entry-revision` usage.

---

## 38. Phase 1E-B Open Decision Classification

| Decision | Class | Schema implication |
|---|---|---|
| Brand: AB2TI Award vs ICBB-AB2TI Award | **NON-BLOCKING PRODUCT** | `award_programs.name` / copy |
| Entry mode (self / nomination / org / individual) | **FEATURE IMPLEMENTATION BLOCKER** | Do not encode engine; optional org name only |
| Membership eligibility | **FEATURE IMPLEMENTATION BLOCKER** | No `member_id` in V1; add later if yes |
| Recognition without Entry | **FEATURE IMPLEMENTATION BLOCKER** | Column **nullable**; product may still require entry_id in the Action |
| Reopen / unpublish policy | **FEATURE IMPLEMENTATION BLOCKER** | Status machine in operations; no extra tables |
| First-edition categories | **NON-BLOCKING** / deferred table | No V1 category table |
| Recognition kinds | **NON-BLOCKING PRODUCT** | String column; subset used in V1 |
| `/award` vs `/program/...` | **NON-BLOCKING** (routes/CMS reserve) | Slugs on program/edition, not path table |
| V1 participant files | **FEATURE** if yes becomes schema add | **No V1 table** unless decided yes before migrate-of-files |
| `results.publish` vs `manage` | **NON-BLOCKING** (ACL 1G) | No schema |
| `awards-admin` vs `editor` | **NON-BLOCKING** (ACL 1G) | No schema |

None of these is a **SCHEMA BLOCKER** for the proposed table set, provided Recognition.entry_id stays nullable and categories/files stay out until chosen.

---

## 39. Implementation Readiness

**PASS / CLOSED**

Persistence architecture is complete for Core V1 table design. No remaining schema identity/uniqueness blockers.

Do **not** write migrations in this phase. Do **not** start Phase 1G from this correction.

Former “non-blocking decisions” are product/sequencing/additive-field items; they do not hold closure.

---

## 40. Recommended Next Phase

**Phase 1G — ACL / Authorization Migration Plan** (still no feature code): map `membership.*`, `publishing.*`, `knowledge.*`, `awards.*`, and grant purposes onto seed/sync strategy without applying ACL yet.

Alternatively, an **explicit implementation task** to reserve URL segments and/or write Core V1 migrations from this contract.

Do not start either from this document automatically.

---

End of Phase 1F schema & data contract.
