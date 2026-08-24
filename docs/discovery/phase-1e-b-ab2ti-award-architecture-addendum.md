# AB2TI 2.0 — Phase 1E-B AB2TI Award Architecture Addendum

**Status:** Architecture addendum only. Not schema. Not implementation. Not ACL.  
**Date:** 2026-08-20  
**Parent:** `docs/discovery/phase-1e-technical-architecture-contract.md`  
**Closed predecessors:** Phase 1A–1E-A remain closed. This addendum **adds** a newly confirmed product requirement; it does not rewrite those documents.

Phases 1C / 1D / 1F treated Award as a **thin Program / CMS-or-content archive** and deferred an awards engine. That was correct **at the time**. A later product confirmation now requires a **structured, recurring Award program**. **This addendum wins for AB2TI Award.** Other named efforts (Pelatihan, Advokasi, Bank Benih, RMP) are **not** automatically pulled into this context.

---

## 1. Executive Summary

**AB2TI Award** is a recurring annual or periodic AB2TI program. It needs a persistent program identity, edition cycles, public presentation, participation (registration/submission/nomination), staff verification, official results, public announcement, and a **historical archive that a new edition must not overwrite**.

It is **not** “a CMS Page named AB2TI Award 2026.” CMS/Tiptap/Media support **editorial presentation**. The **Awards** business context owns lifecycle, entries, and official recognition.

**Position:** AB2TI Awards is a **first-class business context**, alongside Membership, Publishing, and Agricultural Knowledge. It is the first **operated** program-like domain. It is **not** a generic Programs platform that every initiative must use.

**Core V1 (recommended):** program + editions + public pages + entry intake + admin verification + staff-recorded official results + public archive. **Not** an internal judging/scoring engine.

Judging may start **outside** the system. Official winner recording is a **distinct** administrative act, not a calculated scoreboard.

Participant identity does **not** require a User account. Award Entry is **not** a Membership Application.

Repository: no Award/competition modules exist. Landing footer `#program` and `ServiceEvents.vue` are **kernel placeholders**, not this domain.

---

## 2. Reason for Addendum

| Earlier closed position | New confirmation |
|---|---|
| 1B/1C: Award is a Program **instance**; V1 may be landing + archive **content**; do not build an awards engine | Award is an **operated recurring program** with editions, intake, results, archive |
| 1D: Program record vs CMS landing **DECISION REQUIRED**; no `program-admin` in V1 | Award operations need an **Awards** authorization surface, distinct from `editor` publishing news *about* the Award |
| 1F: no Program/Award tables in Core V1 | Phase 1F (or a 1F extension) **must** include Award persistence concepts |

This is a **product-driven extension**, not a discovery error in 1A–1E-A.

---

## 3. Authoritative Inputs

- Phase 1A: live site `/icbb-ab2ti-award/` + yearly winner archives (2021–2024 observed); ICBB place ≠ Award  
- Phase 1B: Award under Program IA; not a permanent header item; not Teknologi Tani  
- Phase 1C: Programs context **thin**; Edition “premature as universal”; winner lists as **content** for V1 — **superseded for Award by this addendum**  
- Phase 1D–1E-A: identity split, `domain.action` ACL, CMS ≠ business aggregates, Secure Capability Access, private docs for membership PII  
- Constitution: do not resurrect Presbi; do not invent modules for convenience  
- Kernel: CMS Page + Tiptap + File Manager; **no** award/competition/registration code  

Unresolved in repo: categories, eligibility, scoring, schedule, entry mode (self vs nomination). Marked **DECISION REQUIRED**, not invented.

---

## 4. Architecture Position

```
BUSINESS CONTEXTS
  Membership
  Publishing
  Agricultural Knowledge
  AB2TI Awards          ← this addendum
  Koperasi Pusat        [later operational]
  Community             [later]
```

**Validated:** Yes. Award has its own lifecycle, intake, and historical results. That is the same *kind* of reason Membership is not a CMS Page.

**Not:** collapse Award into Publishing (winner lists as Berita).  
**Not:** collapse Award into Membership (entry ≠ association application).  
**Not:** a CMS Page per year.  
**Not:** a generic `programs` engine required before Award can exist. Other initiatives stay CMS/mission copy until product names them.

Publishing **may reference** an Award Edition (news about winners). Publishing **must not own** Award identity, edition state, entries, or official results.

---

## 5. Domain Boundary

| Concept | Role | Aggregate now? |
|---|---|---|
| **Award Program** | Long-lived program identity (AB2TI Award) | **Yes** — one (or few) persistent programs |
| **Award Edition** | One cycle/period (2026, 2027, … or a named period) | **Yes** — core |
| **Award Entry** | Neutral participation record for one edition (covers registration / submission / nomination until product picks a mode) | **Yes** for Core V1 intake |
| **Award Recognition / Result** | Official, attributable outcome (winner, runner-up, special, …) | **Yes** — distinct from Entry status |
| **Award Category** | Optional partition of an edition (recipient types, competition tracks) | **Boundary reserved**; not required as a Core V1 aggregate if still unknown |
| **Judging / Assessment** | Evaluation process | **Future** as platform workflow; Core V1 = external + staff record result |
| **Award editorial content** | Intro, rules, FAQ, jury profiles, winner stories | **Owned by Awards**, authored via **Tiptap** |
| **Generic Media** | Banners, public photos, gallery | Platform; Awards **references** |

Do **not** create a table per noun in this phase. Phase 1F decides persistence. Jury-as-entity, score sheets, and panels are **not** Core V1 aggregates.

```
Award Program
  └── Award Edition (many, historically retained)
        ├── editorial content + media references
        ├── Award Entries (many)
        └── Award Recognitions / Results (many)
              optional: Category (edition-scoped)
```

---

## 6. Award Program vs Award Edition

**Award Program** = long-lived identity.

- Name, evergreen purpose, public landing, pointer to **current edition** (configured, not `year == now()`).
- Outlives any year. Creating 2028 does not replace 2026 as a program.

**Award Edition** = one occurrence.

May conceptually own: identity; label (year **or** period slug, e.g. `2026` or `2026-2027`); optional theme/title; registration window; lifecycle state; eligibility/rules **snapshot** (copy or structured later); optional category snapshot; public content; entries; results; announcement/visibility; archive presence.

**Year is not the only identifier.** Periodic editions are first-class.

**Invariant:** Program ≠ Edition. No `award_2027` table-per-year.

---

## 7. Edition Lifecycle

Smallest set justified by: hide-while-preparing, public information, open/close intake, external judging without a scoring module, publish official results, keep history.

| State | Meaning | Public edition page | Registration | Admin | Terminal? |
|---|---|---|---|---|---|
| `draft` | Staff preparing | No (or staff preview only) | No | Full edit | No |
| `announced` | Public information; intake not open | Yes | No | Edit content; open registration later | No |
| `registration_open` | Accepting entries | Yes | **Yes** | Review incoming entries | No |
| `registration_closed` | No new entries; assessment may occur **off-platform** | Yes (info; not submit) | No | Verify entries; wait for external judging; record results when ready | No |
| `completed` | Official results recorded; public winners | Yes (info + results) | No | Corrections under audit; do not reopen intake without product policy | **Yes** for normal flow |

**Not used as a hiding state:** `archived`. Historical editions stay **publicly listable**. “Not current” is derived from the Program’s current-edition pointer, not from deleting or unpublishing completed editions.

**Rejected as Core V1 states:** `assessment` as a required platform state (judging is external; `registration_closed` covers the wait). Extra micro-states need product policy.

### Transitions (conceptual)

```
draft → announced → registration_open → registration_closed → completed
         ↑                │
         └────────────────┘  reopen/close registration before completed
                             (product may forbid reopen — DECISION REQUIRED)
```

`completed` → reopen registration: **DECISION REQUIRED** (default **deny**).  
Withdrawing a public edition: **DECISION REQUIRED** (unpublish vs keep with notice). Until then, do not treat unpublish as delete.

---

## 8. Registration / Submission Boundary

**Neutral concept: Award Entry.** One row of participation bound to **exactly one Edition**.

Product has **not** locked: self-registration vs nomination vs organizational vs individual. The Entry record must tolerate a **mode** later without becoming a second Membership Application.

| Rule | Contract |
|---|---|
| Cardinality | Entry belongs to one Edition |
| Lifecycles | Entry status ≠ Edition status |
| Field split | Participant-writable ≠ staff-writable ≠ system-written |
| Public input | **Never** sets eligibility decision, internal notes, recognition/winner flags |
| History | Entries stay on their original edition when a new edition starts |
| Identity | Participant **need not** be a User (same as Membership Applicant) |
| Membership | Entry **is not** Membership Application. Optional later **reference** to Member if eligibility requires it — **DECISION REQUIRED** |

### Entry lifecycle (minimal, Core V1)

| State | Meaning |
|---|---|
| `submitted` | Received |
| `under_review` | Staff verification |
| `eligible` | Accepted into the edition’s considered set |
| `ineligible` | Not accepted (reason staff-owned) |

No `winner` status on the Entry. Recognition is a **separate** official result (§10). That allows runner-up/finalist without overloading entry status.

Withdrawn / draft-entry before submit: **DECISION REQUIRED**; not required for Core V1 (submit can be one-shot).

---

## 9. Assessment / Judging Boundary

**Candidate Core V1 flow (not organizational policy):**

```
Entry submitted
  → staff verification (eligible / ineligible)
  → eligible set
  → external assessment / judging  (outside the platform)
  → staff records official Recognition
  → public announcement (edition completed / results visible)
```

| In Core V1 | Future-ready, not designed now |
|---|---|
| Entry intake + staff eligibility | Judge Users / judge portal |
| Staff notes on entries | Criteria, rubrics, score sheets |
| Staff **records** official result | Panel workflows, conflict-of-interest, ranking engine |
| Public winners from recorded results | Automated winner calculation |

Architecture **must not** require scores to exist in order to record a winner. Future judging should **attach to Edition + Entry**, not replace Recognition as source of public truth.

Do not design scoring schema here.

---

## 10. Results / Winner Recognition

**Award Recognition** (official result) is explicit, edition-scoped, historically stable, publicly publishable, and staff-auditable.

| Relates to | How |
|---|---|
| Edition | **Required.** Results never float to “the program” without an edition |
| Category | Optional. Unknown globally; if used, edition-scoped |
| Entry | **Preferred** link when the recipient participated. Staff-entered recipient snapshot possible if product allows recording a winner without a prior Entry — **DECISION REQUIRED** |
| Kind | Do not hard-limit to one winner. Allow conceptually: winner, runner-up, finalist, special recognition, multiple recipients. Core V1 may ship a small closed set (e.g. `winner` + `other`) — **ASSUMPTION:** at least “winner” plus optional `recognition_kind` string/enum later |

Public announcement reads **Recognitions**, not “the Entry marked winner.”

Corrections: new Recognition or audited amendment; **not** silent overwrite without staff identity/time. Not event sourcing.

---

## 11. Public Historical Archive

First-class. Completing 2028 **must not** destroy 2026–2027.

Rules:

- New edition = **new Edition record**, not an in-place year update.  
- Previous winners remain discoverable via their edition.  
- Edition content is historically preserved (editorial + results).  
- Public URLs should be **edition-stable** (slug/period), not only “current.”  
- “Current edition” is a pointer/configuration, not the only stored copy of content.  
- Completed ≠ deleted. Not-current ≠ hidden.  
- Withdrawn-from-public is an explicit staff act (**DR**), not a side effect of opening a new edition.

Do not finalize routes here. Conceptual: evergreen program landing + per-edition pages + per-edition results.

---

## 12. CMS / Tiptap Boundary

| May use Tiptap | Must not be a CMS Page |
|---|---|
| Program intro, hero copy, rules, FAQ, timeline narrative, jury profiles, winner stories, historical narrative | Award Program identity |
| | Award Edition lifecycle |
| | Entries, eligibility, official results |

**CMS Page ≠ Award Edition.** **Tiptap HTML ≠ lifecycle.**

Award-owned rich text should **reuse** the existing editor/HTML representation (same pattern as Publication vs Page) and remain **owned by Award records**. Do not store editions inside `pages`.

News *about* an edition remains **Publishing**. Duplicate winner lists in Berita are presentation, not source of truth.

---

## 13. Media Boundary

```
Awards  -->  generic Media   (banners, public photos, jury/winner imagery, gallery)
Media   ✗  does not own Award Program / Edition / Entry / Recognition
```

Editorial Award assets may use File Manager / Spatie collections like Publication covers.

If future requirements add **sensitive participant documents** (identity scans, etc.), treat them like Membership documents: **not** File Manager, private storage, domain access. **Do not design that store now** — not confirmed.

`media.*` does not authorize Award entry PII or result recording.

---

## 14. Actors

| Actor | Account? | Notes |
|---|---|---|
| **Public Visitor** | No | Reads landing, editions, published results |
| **Award Participant** (applicant / nominator / submitter) | **Not required** in Core V1 | Party on an Entry; ≠ User; ≠ Member unless eligibility says so |
| **Awards Administrator** | Yes (staff User) | Editions, verification, official results |
| **Editorial Staff** | Yes | Publishing/CMS; may edit Award **copy** if permitted; must **not** silently gain result-recording |
| **Judge / Assessor** | Future | Not a Core V1 actor inside the app |

```
User / Account
  != Award Participant
  != Award Entry
  != Award Recognition
  != Authorization Role
  != AB2TI Member
  != Membership Applicant
```

---

## 15. Authorization Surface

Do **not** modify `config/acl.php`. Names below are **1G-oriented concepts**, `domain.action`, no wildcards.

### PUBLIC ACCESS (not Spatie)

- Read published program/edition/results  
- Submit an Entry when edition is `registration_open` (abuse controls like public membership apply)

### PARTICIPANT (not Spatie)

- Create/submit Entry (public)  
- Later: resume/revise via **Secure Capability Grant** purpose owned by Awards — not a Spatie permission  

### STAFF (future Spatie)

| Capability | Meaning |
|---|---|
| `awards.editions.view` | Staff list/detail including drafts |
| `awards.editions.manage` | Create/update editions, lifecycle transitions except perhaps publish-results split |
| `awards.entries.view` | Queue |
| `awards.entries.review` | Eligible / ineligible + notes |
| `awards.results.manage` | Record/correct official recognitions |
| `awards.results.publish` | Make results public / complete edition — **optional split**; may fold into `manage` until editor-vs-publisher exists |

Do **not** use `programs.programs.*` for Award operations (1D’s thin-program names). Awards is its own domain prefix.

Do **not** reuse `membership.*` for entries.

**Role concept (not seeded):** `awards-admin` (or equivalent) holds `awards.*`. `editor` publishes news and may be granted Award **copy** later by product; **ASSUMPTION:** `editor` does **not** automatically receive `awards.entries.review` or `awards.results.manage` (same split as membership vs editor).

Public submit is **not** `awards.entries.create` for staff unless staff-capture is a later DR.

---

## 16. Secure Capability Compatibility

Phase 1E-A platform primitive **applies**. Awards must **not** invent a second token system.

| Use | Now |
|---|---|
| One-shot public submit | Capability **not required** |
| Resume incomplete entry / staff-requested revision | Compatible purpose, e.g. `award-entry-revision`, target = that Entry (or Edition+Entry). **Future / if product wants loginless multi-step** |
| Winner confirmation email link | Optional later; signed URL or grant |

Do not put a durable edit token on the Entry row. Domain state still wins (closed edition + live grant = **DENY** writes).

---

## 17. Public Information Architecture

Conceptual only. **Not** mandatory paths.

Must support: evergreen Award landing; current edition; historical editions; results; registration when open.

**Recommendation (DECISION REQUIRED to confirm):** reserved family **`award`** (e.g. `/award`, `/award/{edition}`, `/award/{edition}/winners`, register nested or `/award/{edition}/daftar`). Phase 1B used `/program/...` for Award as a Program instance. Because Awards is now its own context, **`/award` is clearer**; keep **`/program` reserved** for a future program index of *other* initiatives.

Do not hardcode the site around one calendar year. Do not occupy `award` as a CMS Page slug once routes exist.

---

## 18. Current Edition Concept

**Current edition** = structured configuration on the Award Program (explicit current-edition reference), optionally informed by edition state.

**Forbidden:** `if (year == currentYear())`; `award_2027` tables; replacing a single CMS Page each year as the only “current” record.

Staff set current edition. Public landing reads that pointer **and** still lists historical editions from Edition records.

---

## 19. Category Model

Categories are **not** a global taxonomy.

If needed: **owned by Award Edition** (snapshot). 2026 categories need not equal 2028.

**Core V1:** **DEFERRED** as a required aggregate. Recognitions can exist **without** category. When product lists categories, add edition-scoped categories without rewriting Program/Entry.

Do not invent tracks (individual vs kelompok, etc.) here.

---

## 20. Retention & Historical Integrity

- Completed editions are not destructively replaced by a new cycle.  
- Official recognitions remain traceable to edition (+ entry when linked).  
- Public archive remains available unless **explicitly** withdrawn.  
- Administrative corrections are staff-attributed; do not silent-rewrite public truth.  
- No event-sourcing platform. Timestamps + staff actor on result changes suffice.  
- No regulatory retention period is known — **not specified**.  

Deleting an Edition that has Recognitions or Entries: **restrict** (Phase 1F). Prefer not to delete completed editions.

---

## 21. Feature Boundary Matrix

| FEATURE / DATA | OWNER | PUBLIC? | CMS? | MEDIA? | SECURE CAPABILITY? | CORE V1 / FUTURE | NOTES |
|---|---|---|---|---|---|---|---|
| Award Program | Awards | Landing yes | No (Tiptap on Award) | Branding optional | No | **CORE V1** | One long-lived program |
| Award Edition | Awards | If not `draft` | No | Optional | No | **CORE V1** | Historical rows |
| Award editorial content | Awards | Per edition/program visibility | **Tiptap reuse**, not `pages` | Yes editorial | No | **CORE V1** | |
| Award Entry | Awards | Submit when open; not a public directory by default | No | Public imagery **DR**; PII later private | Optional later revision | **CORE V1** | Not Membership Application |
| Administrative review | Awards | No | No | No | No | **CORE V1** | Eligibility |
| Judging / scoring | Awards (future) | No | No | No | No | **FUTURE** | External in V1 |
| Official Recognition | Awards | When edition completed / results published | No | Winner photos editorial | No | **CORE V1** | Not calculated |
| Winner archive | Awards | Yes | No | Yes | No | **CORE V1** | Per edition |
| Award media (editorial) | Media referenced by Awards | If used on public pages | No | **Yes** | No | **CORE V1** | File Manager OK |
| Award private documents | Awards overlay | No | No | **Not** generic Media | Maybe upload purpose | **FUTURE** unless product requires in V1 |
| News about Award | Publishing | Yes | No | Yes | No | Publishing V1 | Reference edition |

---

## 22. Core V1 vs Future Scope

### Core V1 (smallest justified)

- Award Program  
- Recurring Award Editions (not year-only)  
- Public program + edition pages (Tiptap)  
- Award Entry when registration open  
- Staff verification (eligible / ineligible)  
- Staff-recorded official Recognition  
- Public results + historical archive  
- Current-edition pointer  
- Editorial Media references  

### Future

- Internal judging, judge accounts, criteria, rubrics, panels, COI, ranking, analytics  
- Category engine  
- Nomination-specific workflows, organizational entries as a product type  
- Member-eligibility enforcement / Member FK  
- Private participant document store  
- Capability-based entry revision  
- Automated winner calculation  
- Generic Programs platform for Pelatihan/Advokasi/Bank Benih  
- Award as permanent site header (1B still advises against)

Validate: this split matches “judging may be outside” and “history is first-class” without a scoring product.

---

## 23. Updated Domain Map

```
PLATFORM / KERNEL
  Identity
  Authorization (ACL)
  CMS / Tiptap
  Media
  Secure Capability Access

BUSINESS CONTEXTS
  Membership          --> Identity(staff); private docs; optional Capability
  Publishing          --> Media, Tiptap, Identity(staff);
                          MAY REFERENCE Award Edition (not own it)
  Agricultural Knowledge --> Media, Tiptap, Identity(staff)
  AB2TI Awards        --> Identity(staff awards-admin);
                          Tiptap (owned content);
                          Media (editorial);
                          MAY consume Secure Capability Access (entry revision);
                          MUST NOT own Membership / Publishing identity
  Koperasi Pusat      [later]
  Community           [later]

THIN / UNOPERATED
  Other Programs / initiatives (CMS or future Programs context)
  — not a prerequisite for Awards
```

**Forbidden:** CMS owns Award lifecycle; Media owns Award; Membership Application reused as Award Entry; Publishing owns official winners; new edition overwrites previous; User required for participant; Sanctum PAT as Award token.

ICBB-the-place remains CMS/contact. ICBB partner remains copy. **ICBB-AB2TI Award** is this Awards program (naming **DECISION REQUIRED**: public brand “AB2TI Award” vs “ICBB-AB2TI Award”).

---

## 24. Impact on Phase 1F

Phase 1F (`phase-1f-schema-data-contract.md`) was written **before** this confirmation and deferred Program/Award tables. **That deferral no longer applies to AB2TI Award.**

Phase 1F (or a **Phase 1F Award extension**) **must** evaluate persistence for:

| Concept | 1F treatment |
|---|---|
| Award Program | **Include** Core V1 contract |
| Award Edition | **Include** Core V1 contract |
| Award Entry | **Include** Core V1 contract |
| Award Recognition / Result | **Include** Core V1 contract |
| Award Category | Evaluate; likely **optional / deferred** unless product lists categories |
| Award editorial HTML | Columns or related content on Program/Edition — 1F decides, not CMS `pages` |
| Judging/scores | **Not** Core V1 schema |
| Judge Users | **Not** Core V1 schema |
| Award private documents | **Not** unless V1 requires uploads of PII |

1F still must **not** treat Award Entry as Membership Application or as `pages`.

Do not prescribe table names here.

---

## 25. Impact on Later Phases

| Phase | Impact |
|---|---|
| **1F** | Award persistence contract (extend existing 1F) |
| **1G ACL** | Add `awards.*` groups when the slice ships; do not reuse `membership.*` or thin `programs.programs.*`; consider `awards-admin` vs `editor` split |
| **Award vertical slice** | Controllers/Actions for edition lifecycle, public submit, review, record recognition; tests for historical non-overwrite |
| **Public frontend** | Evergreen + edition + winners; not a single yearly CMS page |
| **Admin dashboard** | Awards staff area sibling to Membership/Content; not File Manager |

Do not implement these here.

---

## 26. Architectural Invariants

1. AB2TI Award is **not** merely a CMS Page.  
2. Award Program ≠ Award Edition.  
3. An Award Edition is **historically persistent**.  
4. A new edition **must not overwrite** previous editions.  
5. An Award Entry belongs to **one** specific Edition.  
6. Public participant input **cannot** set administrative, eligibility, or winner state.  
7. Judging **may** remain outside the platform in Core V1.  
8. Official Recognition is **distinct** from judging calculation.  
9. Historical winners/results are **public archive data** (unless explicitly withdrawn).  
10. CMS / Tiptap / Media are **platform capabilities**; they do not own Award records.  
11. Award Participant identity does **not** automatically require a User (or a Member).  
12. Future participant revision must use **Secure Capability Access**, not a permanent insecure token on the Entry.  
13. Award Entry ≠ Membership Application ≠ Publication.  
14. `media.*` does not grant Awards administration.  
15. Current edition is **configured domain state**, not the wall clock.

---

## 27. Decision Register

### CONFIRMED

- AB2TI Awards is an approved **business context**.  
- Program ≠ Edition; editions are recurring and retained.  
- CMS/Tiptap/Media support presentation; they do not own lifecycle.  
- Core V1 includes intake + staff verification + staff-recorded results + public archive.  
- Core V1 does **not** require an internal judging engine.  
- Participant need not be a User.  
- Entry ≠ Membership Application.  
- Official result is a distinct Recognition concept.  
- Secure Capability Access is the only loginless-token pattern if revision is added.  
- 1E-B **supersedes** 1C/1D/1F “thin Award as content / no Award tables” **for Award only**.

### ASSUMPTIONS

- One Award Program in V1 (AB2TI Award / ICBB-AB2TI Award brand).  
- `editor` does not auto-get entry review or result recording.  
- Public URL family **`award`**; `/program` remains reserved for other initiatives.  
- `archived` is not a hiding lifecycle state.  
- Entry mode can start as undifferentiated “Entry.”  
- Recognitions can ship without categories.  
- Completing an edition does not unpublish older editions.  
- Winner photos are editorial Media unless marked sensitive.

### DECISIONS REQUIRED BEFORE IMPLEMENTATION

- Public name: AB2TI Award vs ICBB-AB2TI Award.  
- Entry mode: self-register vs nomination vs mixed; individual vs organization.  
- Eligibility vs AB2TI membership (optional Member reference).  
- Whether staff may record a Recognition without an Entry.  
- Reopen registration after close; reopen after `completed`.  
- Unpublish/withdraw historical edition policy.  
- Category list for first operated edition (or confirm none).  
- Recognition kinds for V1 (winner only vs runner-up/special).  
- Exact reserved path (`award` vs `program/...`).  
- Whether V1 collects participant files (and if any are private).  
- `awards.results.publish` split vs folded into `manage`.  
- Default whether `awards-admin` is a distinct role at first seed.  
- Registration window fields vs using edition state only.

### DEFERRED / FUTURE

- Internal judging, scoring, judge portal, panels, COI, analytics  
- Category management UI  
- Advanced nomination  
- Automated ranking  
- Private Award document store  
- Capability entry revision  
- Generic Programs platform / other initiatives as Programs  
- Member portal integration  

---

## 28. Readiness for Phase 1F

**PASS WITH NON-BLOCKING DECISIONS**

Award is architecturally bounded: Program vs Edition vs Entry vs Recognition vs CMS vs judging-future. Phase 1F can define persistence for those four Core V1 concepts without categories, scoring, or private docs.

Non-blocking items (entry mode, categories, membership eligibility, URL family, brand name) affect optional columns and IA, not whether 1F may start the Award contract.

**Do not start Award migrations from this addendum.**

**Sequencing note:** A Phase 1F document already exists and deferred Award tables. Next schema work should **extend Phase 1F** for Awards rather than treat the existing 1F “no Program table” line as still authoritative for Award.

---

End of Phase 1E-B.
