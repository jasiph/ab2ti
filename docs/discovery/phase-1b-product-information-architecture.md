# Phase 1B — AB2TI Product & Information Architecture

**Status:** Product / information architecture only. Not a schema. Not an implementation plan. Not tickets.  
**Date:** 2026-08-19  
**Primary input:** `docs/discovery/phase-1a-ab2ti-domain-discovery.md`  
**Canonical policy:** `docs/project/CONSTITUTION.md`  
**Current platform facts:** `docs/project/ARCHITECTURE_BASELINE.md`

This document decides **what AB2TI 2.0 is for** and **how people conceptually move through it**. It does not change application behavior. It does not name database tables, foreign keys, or Eloquent relationships. Actor names are not ACL roles.

Phase 1A was not rewritten. No factual error in Phase 1A required correction.

---

## 1. Executive Summary

AB2TI 2.0 is one platform with **several bounded product areas**, not a redesign of the WordPress menu and not a church-admin leftover with new labels.

The product is:

1. A **public organizational website** (CMS pages + landing).
2. A **publishing and agricultural-knowledge platform** (recurring editorial content, distinct from CMS Page).
3. An **AB2TI association membership service** (domain identity, not a login).
4. A **Koperasi Pusat bounded service area** (separate membership, separate admin, separate future services).
5. A **community / regional contribution** intake with moderation (later than staff publishing).
6. An **extensible staff administration** surface grouped by capability, not by every content type.

**Identity rule:** User (account) ≠ AB2TI Member ≠ Cooperative Member. A person may eventually hold more than one of these. A member may exist with no account.

**Publishing rule:** Tentang AB2TI belongs in CMS Page + Tiptap. Berita, kegiatan, press, and media coverage belong in a **shared publishing capability** with distinct public types/filters. They are not CMS pages.

**First slice is not the whole vision.** Core public site + staff publishing + core association membership (staff-operated) should precede cooperative operations, member self-service, community intake, and structured agricultural catalogs.

Tiptap stays platform editing infrastructure. Block templates stay copy-on-insert authoring snippets. Neither is an AB2TI business domain.

`/sermons` and `/events` are **not** part of the canonical IA. Compatibility is a later redirect concern, not product taxonomy.

---

## 2. Product Principles

Established for AB2TI 2.0. Repository evidence from Phase 1A supports them; no contradiction was found.

### 2.1 User is not Member

| Concept | Meaning |
|---|---|
| **User** | Authentication / account identity (today: staff/admin on the kernel) |
| **AB2TI Member** | Association membership domain identity |
| **Cooperative Member** | Koperasi Pusat membership domain identity |

A future User **may be linked** to one or more domain identities. The concepts stay separate. An AB2TI member **may exist without a User**.

Do not reuse the seeded kernel role name `user` to mean anggota.

### 2.2 AB2TI membership ≠ cooperative membership

These are separate bounded product areas: navigation, application, verification, administration, permissions, and future services stay conceptually separable.

Membership in AB2TI does **not** create Koperasi Pusat membership. The legacy site already states this. Koperasi Pusat is also open to the public, not only to AB2TI members.

### 2.3 CMS Page ≠ publishing content

Existing Page + Tiptap is for **editable informational pages**: Tentang AB2TI, history, vision/mission, organizational information, contact, and similar.

Recurring dated public content — news, activities, press releases, media coverage, agriculture articles — needs a **publishing capability** separate from Page. Do not force it into Page.

### 2.4 Tiptap is platform editing capability

Tiptap (and its current customizations) is reusable editing infrastructure for trusted staff HTML. It is not an AB2TI domain. Do not redesign it in this phase. Publishing and CMS pages should **reuse** it later, not fork it.

### 2.5 Block template ≠ reusable application component

BlockTemplate is copy-on-insert HTML for authors. Vue Base components are application primitives. Do not treat block templates as a design system or as live-linked page modules.

### 2.6 Legacy URL names are not product taxonomy

`/sermons`, `/events`, and church terminology are not AB2TI 2.0 IA. Canonical names are organizational and Indonesian-semantic. Legacy URLs may later redirect.

### 2.7 Additional product principles from discovery

- **One platform, several domains.** Admin must group by capability (Content, Membership, Koperasi, Community, Programs, System), not one menu per WordPress leftover.
- **Untrusted authors never write CMS Page HTML.** Community submissions go through moderation, then editorial publishing.
- **Do not architect around one program.** ICBB-AB2TI Award is a case of Program/Initiative, not the program model.
- **ICBB is not one thing.** Office location, partner organization, and Award program stay distinct in language and IA.
- **File Manager remains platform media**, not automatically a member-document vault or a public gallery product.
- **Public self-registration of Users stays off** until an identity decision says otherwise (ADR 0002). Member *application* is not Fortify registration.

---

## 3. Actor Map

Actors are **product contexts**, not Spatie roles, not tables, and not a requirement that every actor logs in.

### 3.1 Public

| Actor | Authenticates? | Exists without login? | Primary intent |
|---|---|---|---|
| **Anonymous Visitor** | No | Yes (default) | Read the organization, news, knowledge, program info; start a membership or koperasi application |

### 3.2 Association

| Actor | Authenticates? | Exists without login? | Primary intent |
|---|---|---|---|
| **Membership Applicant** | Not required in core | **Yes** — application can be a public form processed by staff | Submit data/documents; respond to revision requests |
| **AB2TI Member** | Optional later | **Yes** — membership is a domain record | Hold status, card, member-facing information; later self-service |
| **Regional / organizational contributor** | Likely later, if they submit themselves | Possible as a name on a record without account | Submit regional activity, articles, photos for review |

A contributor is **not** automatically an editor. They do not publish.

### 3.3 Cooperative

| Actor | Authenticates? | Exists without login? | Primary intent |
|---|---|---|---|
| **Cooperative Applicant** | Not required in core | **Yes** | Apply to Koperasi Pusat (separate from association application) |
| **Cooperative Member** | Optional later | **Yes** | Cooperative membership and, later, cooperative services |

**Not automatically an actor:** Category B / RMP investor. Legacy text treats this as a **different track** from cooperative membership. Until product confirms otherwise: **NEEDS PRODUCT DECISION** (see §18). Do not merge investors into Cooperative Member.

### 3.4 Staff / administration

These actors **do authenticate** as Users. One User may hold several staff contexts via permissions later. Do not create one role per row today.

| Actor | Authenticates? | Notes |
|---|---|---|
| **Editorial Staff** | Yes | CMS pages, publishing, Teknologi Tani editorial, media used in content |
| **Membership Administrator** | Yes | Association applications, verification, member records, cards as operated by secretariat |
| **Cooperative Administrator** | Yes | Koperasi applications and members; not the same permission area as association |
| **Community Moderator** | Yes | Intake queue: accept for editorial, reject, request revision — may be the same person as Editorial Staff, different context |
| **Program Administrator** | Yes | Program landings, editions, results — may start as Editorial Staff if programs are content-only |
| **Platform / Super Administrator** | Yes | Users, roles, platform settings; already exists as kernel `super-admin` *capability*, not as an AB2TI domain |

Secretariat on the legacy site is a **job**, not a separate product actor. In practice it will overlap Membership Administrator + Editorial Staff + Cooperative Administrator.

### 3.5 Overlap: one person, several contexts

Typical overlaps (allowed, not merged):

- A person is an **AB2TI Member** and later a **Cooperative Member** — two domain identities.
- A person is **staff User** and also an **AB2TI Member**.
- A **Regional contributor** may also be an **AB2TI Member**.
- **Anonymous Visitor** becomes **Membership Applicant** without becoming a User.
- **Editorial Staff** and **Community Moderator** are often the same human, different queues.

Forbidden collapses:

- Applicant ≠ Member until approval.
- Member ≠ User.
- AB2TI Member ≠ Cooperative Member.
- Contributor ≠ Editorial Staff.

### 3.6 First-release actor hypothesis (not implementation)

**Likely in core:** Anonymous Visitor, Membership Applicant, AB2TI Member (as a staff-managed record), Editorial Staff, Membership Administrator, Platform Administrator.

**Likely next / future:** Cooperative Applicant/Member as operated records, Community Contributor with login or identified submission, member self-service User, Cooperative Administrator as a distinct staff context, Program Administrator if programs become operational.

**NEEDS PRODUCT DECISION:** whether any non-staff actor authenticates in the first public release.

---

## 4. Public Information Architecture

The live WordPress menu is a **content inventory**, not the 2.0 sitemap. The kernel menu (Home / About / Farming Tech / News / Contact on `/sermons` and `/events`) is a **placeholder**. Neither is canonical.

### 4.1 Design goals

- Ordinary visitors understand the organization in a few top-level items.
- Publishing types are findable without seven news-like items in the header.
- Keanggotaan and Koperasi are **siblings**, never nested as one “Anggota” blob.
- Teknologi Tani is a public product area, not a CMS alias of a church URL.
- Programs exist without crowding the header with every initiative.

### 4.2 Primary navigation (public header)

Keep **six** visitor-facing items plus Home. Home is the logo / first item, not a seventh novelty.

| Item | Role |
|---|---|
| **Beranda** | Landing |
| **Tentang** | Organization (CMS) |
| **Publikasi** | Shared publishing hub (berita as default) |
| **Teknologi Tani** | Knowledge / agricultural editorial |
| **Keanggotaan** | Association membership information + apply |
| **Koperasi** | Koperasi Pusat information + apply (separate) |

That is enough for a first-time visitor. **Kontak** can sit in the header on large screens **or** in the footer only; recommendation: **footer + Tentang subsection**, not a seventh primary item, unless product insists on header Contact.

### 4.3 Secondary navigation (footer / utility)

| Item | Role |
|---|---|
| Kontak / Sekretariat | Address, phone, email, WhatsApp |
| Program | Index of initiatives (Award is one entry) |
| Galeri | Media presentation |
| Publikasi sub-links | Berita, Kegiatan, Siaran Pers, Liputan Media |
| Dokumen | Public downloads index (if kept public) |
| Language | Existing `id` / `en` UI chrome (not translated CMS HTML) |

### 4.4 Subsections (not top-level)

**Tentang**

- Tentang AB2TI (hub)
- Visi & misi
- Sejarah (if content exists)
- Other organizational CMS pages as needed

These are CMS pages, possibly nested under `/tentang/…`.

**Publikasi**

- Default: Berita
- Filters or sub-nav: Kegiatan, Siaran Pers, Liputan Media  
  These are **types/taxonomies of publishing**, not primary nav items.

**Teknologi Tani**

- Hub / landing (intro)
- Article list
- Later: structured knowledge entry points (varieties, guides) without changing the top-level item

**Keanggotaan**

- What membership is
- Benefits / obligations at a public level
- How to apply
- Public documents that are meant to be public  
  Member-only documents are **not** primary public IA.

**Koperasi**

- What Koperasi Pusat is (and that it is not automatic with AB2TI membership)
- How to apply
- Public cooperative news/content that belongs here
- RMP / enterprise **information** only until product decides otherwise

**Program**

- Program index
- Each program landing (e.g. ICBB-AB2TI Award) with yearly archives

### 4.5 What must not be top-level

- Press Release, Media Coverage, Kegiatan as separate header items
- ICBB-AB2TI Award as a permanent header item
- Provibio as a header item
- Category A/B shares
- WordPress “Anggota” as a dump of forms + tata niaga + SK
- Anything named sermons, events, jemaat, congregation

### 4.6 Home composition (conceptual)

Home is a **composition surface**, not a CMS Page and not a news archive:

- Organization message (existing Hero / Welcome capabilities may serve)
- Latest Publikasi
- Entry points to Keanggotaan and Koperasi (separate CTAs)
- Optional highlight of a current Program
- Sekretariat / contact strip

Do not hardcode a third competing “Program Kami” list as if it were the program domain.

---

## 5. Publishing Architecture

### 5.1 Recommendation

**Shared publishing backbone, distinct public types.**

Not option A (completely separate product areas for Berita, Kegiatan, Press, Media Coverage). That would duplicate authoring, media, dates, lists, and staff workflow, and it would freeze the WordPress accident that already mixed these in one Berita stream.

Option B:

- **One publishing capability** for dated, listable, staff-authored (or community-originated then edited) public items.
- **Editorial types** (visitor-facing): Berita, Kegiatan, Siaran Pers, Liputan Media.
- **Shared** title, body (Tiptap), cover/media, date, publication state, optional related program or region **as labels later**.
- **Distinct** public filters, list headings, and optional dedicated URL families (`/berita`, `/kegiatan`, …) that are **views** of the same capability, not four CMS Page trees.

This is product architecture, not inheritance or schema.

### 5.2 Why the types still matter

| Type | Visitor expectation | Legacy observation |
|---|---|---|
| **Berita** | Organizational news, commentary, current affairs | Majority of WP posts |
| **Kegiatan** | What AB2TI / regions **did or will do** | Mixed into Berita today (e.g. regional awards) |
| **Siaran Pers** | Official statements | Not separated on the legacy site |
| **Liputan Media** | Third-party coverage, embeds | Video/press mixed into Berita |

Staff may start with a small type set (e.g. Berita + Kegiatan) and add Siaran Pers / Liputan Media as filters when editorial practice is ready. The IA still **reserves** them as types so they are not stuffed into Tentang pages.

### 5.3 What publishing is not

- Not CMS Page (no listing/date/type model there).
- Not Teknologi Tani’s public **area** (different intent; see §6) even if the **editor** is reused.
- Not a gallery product (a publishing item may include photos).
- Not membership or koperasi records.
- Not untrusted HTML from contributors.

### 5.4 Editorial workflow (staff)

Core: draft/unpublished vs published is enough conceptually (`is_active`-like publication, but **on publishing items**, not by overloading Page).

Richer review (editor vs publisher) is **NEXT** unless product requires it in core.

Community-originated items enter publishing **only after** moderation + editorial review (§9).

### 5.5 Opinion / “opini”

Legacy Berita includes opinion pieces. Treat opini as **Berita** (or a tag) until product wants a fifth type. **NEEDS PRODUCT DECISION** only if opini must be a first-class public type.

---

## 6. Agricultural Knowledge Positioning

**Teknologi Tani is a distinct public product area.** It is not Berita, not Tentang, and not `/sermons`.

### 6.1 Now: editorial-first

Today on the legacy site it is a landing plus a **small** set of instructional articles (cultivation guidance, IF16 advice, biopori, etc.).

IA:

- Hub page: CMS Page **or** a knowledge hub that can hold intro copy (Tiptap).
- Articles: **agricultural knowledge items**, listable under `/teknologi-tani`.
- They may **reuse the publishing editor and media**, but they are a **separate channel/area** so farmers looking for guidance do not hunt through food-policy news.

Do not put Teknologi Tani articles in the Berita type by default.

### 6.2 Later: structured knowledge without IA breakage

The same public area can later add structured entry points, for example:

- Varieties (names already appear in prose: IF16, IF8, “AB2TI super”)
- Technical documents
- Seed-related guidance that is currently PDF/SK

Those become **STRUCTURED DOMAIN DATA — FUTURE** behind the same top-level **Teknologi Tani**. Visitors still enter one door. Staff later get additional admin tools under Content, not a new header brand.

Provibio sits in this orbit until product says it is a cooperative service or a commercial catalog (§10, §13).

### 6.3 What not to do

- Do not invent a seed database in order to launch the public area.
- Do not implement tata niaga as an enforcement engine in the knowledge IA.
- Do not merge ICBB Award into Teknologi Tani.

---

## 7. AB2TI Membership Journey

Conceptual association journey. Legal fees, member classes, and renewal rules that were not confirmed remain **NEEDS PRODUCT DECISION**.

### 7.1 End-to-end (desired)

```
Anonymous Visitor
    → Membership information (public, CMS)
    → Application (association form — not Fortify register)
    → Document / data submission
    → Administrative verification (Membership Administrator)
    → Approval / Rejection / Revision requested
    → Active membership (domain record; User optional)
    → Member administration (staff)
    → Membership card (physical in known legacy practice)
    → Status lifecycle / renewal if product later defines it
```

Cross-links:

- From membership information, a **separate** CTA to Koperasi — never “apply once for both.”
- After approval, member-only documents may become visible **to staff** immediately; **to the member** only if/when self-service exists.

### 7.2 CORE MEMBERSHIP (conceptually required for a membership service)

| Capability | Why core |
|---|---|
| Public membership information | Visitors must understand what they join |
| Association application intake | Replaces (eventually) the external form |
| Staff queue: verify, approve, reject, request revision | Secretariat work that the website currently dumps into Google-like forms |
| Member record (identity in the membership domain) | Without this there is no “member” |
| Status at least: applied / needs revision / rejected / active | Minimum lifecycle; names can change |
| Application documents (photo, identity scan, payment proof as **process artifacts**) | Legacy already collects these |
| Staff member administration (search, update status, notes) | Operated by Membership Administrator |

Physical card issuance is **core to the known current practice** (legacy charges for print/shipping) but **how** it is tracked in-app is still **NEEDS PRODUCT DECISION**. Treat “card issued / not issued” as a membership administration concern, not as a requirement to build a card-design studio.

### 7.3 LATER ENHANCEMENT

| Capability | Why later |
|---|---|
| Member self-service account (User linked to member) | Identity linking; ADR 0002; not required to record members |
| Digital membership card | Enhancement of physical card process |
| Printable card layout in-app | Enhancement |
| Regional / organizational relation | Important long-term; structure is unknown |
| Renewal / expiry | No confirmed term on the public site |
| Public or private member directory | Privacy and product scope unknown |
| Member document library beyond the application packet | Distinct from File Manager |
| Enforcement of tata niaga (who may trade seed) | Policy vs software; unknown |
| Applicant login to track status | Nice; staff email/WhatsApp may suffice at first |

### 7.4 Explicit unknowns (do not invent)

- Member types (individual, kelompok, lembaga, regional board).
- Dues vs card-only fee; whether payment is to Koperasi’s account by policy or by accident of history.
- Whether rejected applicants may reapply.
- Whether inactive/resigned/deceased statuses exist.
- Whether a membership number is public.
- Whether regional affiliation is mandatory at application.

**NEEDS PRODUCT DECISION** for all of the above.

### 7.5 What this journey is not

- Not User registration.
- Not Koperasi application.
- Not CMS Page comments.
- Not a community contribution flow.

---

## 8. Koperasi Pusat Product Boundary & Journey

Koperasi Pusat is a **bounded product area inside the same platform**, not a submenu of Keanggotaan.

### 8.1 Boundary

| In the koperasi area | Outside it |
|---|---|
| Public explanation of Koperasi Pusat | AB2TI association membership |
| Cooperative application and verification | Platform Users / roles |
| Cooperative member records and administration | CMS of the whole organization (shared platform, separate content) |
| Cooperative member-facing services (future) | ICBB as office address |
| Cooperative pages/content that are about the cooperative | Award program (association/partner program) |
| Future business/service modules **if product assigns them here** | Structured variety catalog (knowledge) |

Hard rule: applying to AB2TI never auto-enrols Koperasi Pusat. Applying to Koperasi never implies association membership unless product later adds an explicit optional cross-offer — and even then they remain two records.

### 8.2 Conceptual journey (membership operations)

```
Visitor (public or already an AB2TI Member)
    → Koperasi information (CMS / cooperative content)
    → Cooperative application (separate form)
    → Verification by Cooperative Administrator
    → Approval / Rejection / Revision
    → Cooperative Member record
    → Cooperative administration
    → Later: cooperative services
```

This **mirrors** association membership as a *pattern* (intake → verify → record) without sharing the same queue, the same status, or the same “member” concept.

Do **not** design financial ledgers, simpanan accounting, dividend, or share registries in this IA. Legacy numbers (simpanan wajib, sukarela caps, Category B thresholds) are **content and policy**, not a mandate to build accounting.

### 8.3 Public cooperative information (website)

Belongs in **CORE public site**, even if cooperative *operations* wait:

- What the cooperative is
- Explicit non-automatic relationship to AB2TI membership
- How to apply (even if the first release still points at an external form)
- News about cooperative activities **may** appear in Publikasi with a cooperative label, or on cooperative pages — **NEEDS PRODUCT DECISION** for RMP journal posts (see below)

### 8.4 RMP / PT AB2TI PSN, Category A/B, investment

| Concept | Conceptual home | Classification |
|---|---|---|
| RMP / PT AB2TI PSN as **explained to the public** | Cooperative content / pages | Content now; **COOPERATIVE SERVICE CANDIDATE** later |
| RMP construction journal (legacy category) | Publishing **or** cooperative news | **NEEDS PRODUCT DECISION** (type vs cooperative channel) |
| Category A (simpanan → cooperative-linked shares in legacy text) | Tied to **cooperative membership policy** | **STRUCTURED DOMAIN DATA — FUTURE** / **NEEDS PRODUCT DECISION** |
| Category B (investor, different rules, not koperasi membership) | Not Cooperative Member | **NEEDS PRODUCT DECISION** — do not invent an investor portal |
| “INVESTASI” form | External process today | **NEEDS PRODUCT DECISION** whether it is ever in-app |

Do not invent a workflow for share issuance or PT administration.

### 8.5 CORE vs LATER for koperasi

| CORE (when koperasi operations start) | LATER / OPTIONAL |
|---|---|
| Public info pages | Share ledger, accounting |
| Separate application + staff verification + member record | Investor portal |
| Clear IA separation from association | RMP plant operations / logistics |
| | Cooperative e-commerce or input supply |
| | Digital cooperative member card |

**Validated sequencing:** cooperative **public information** can ship with the website (CMS). Cooperative **operations** should not block association membership core, and should not precede a clear membership domain for the association. See §16.

---

## 9. Community / Regional Contribution Journey

This is a **new** product capability. Legacy regional stories were editor-published WordPress posts, not a submission channel.

### 9.1 Conceptual flow

```
Contributor (often an AB2TI Member; account optional at first)
    → Submit content / information / media
    → Moderation (accept into editorial, reject, ask revision)
    → Editorial review / editing (trusted Tiptap)
    → Publish as Publikasi or Teknologi Tani or Program-related item
      or Reject
```

Failed or pending items **never** become CMS Pages and **never** skip to public `v-html`.

### 9.2 Possible submission types (product kinds, not tables)

- Activity information / laporan kegiatan
- Article draft
- Report
- Photo / media set
- Announcement
- Other regional contribution

Moderation decides **destination**: usually Publikasi (Kegiatan or Berita), sometimes Teknologi Tani, rarely a Program page. Destination is an editorial decision.

### 9.3 How this differs from CMS / admin publishing

| Staff CMS / publishing | Community contribution |
|---|---|
| Trusted User (Editorial Staff) | Untrusted or semi-trusted contributor |
| Direct to draft/publish | Queue first |
| Full Tiptap / Page or publishing editor | Constrained intake (fields, uploads, short text) |
| Immediate public risk if published | No public render until editorial publish |
| Platform media library | Uploads held as submission until accepted |

Do not let contributors “create a Page.”

### 9.4 Placement in the product

- **Admin:** Community → Submissions / Moderation (then items appear in Publishing after acceptance).
- **Member-facing (later):** My submissions.
- **Public:** No “upload” in primary nav for anonymous visitors in core.

**Release:** NEXT / FUTURE after publishing exists (there must be somewhere to publish) and after association identity exists (contributors are usually members). Not in FOUNDATION.

---

## 10. Programs & Initiatives Architecture

### 10.1 Generic concept

**Program / Initiative** = a named AB2TI effort that can outlive a single news item: landing information, optionally yearly editions, results/recipients, related articles/media/documents, historical archive.

It is **not**:

- The entire site IA
- ICBB-AB2TI Award hard-coded as the only program
- WordPress empty `project` CPT resurrected
- Footer placeholders (“Pelatihan”, “Advokasi”) automatically promoted to programs

Editorial staff may publish related Publikasi items **about** a program without the program becoming a news type.

### 10.2 Anatomy (conceptual)

- Program landing (purpose, partner, how it works) — often Tiptap
- Editions (e.g. year 2021–2024) when the program repeats
- Recipients / results lists
- Related publishing and media
- Archive when an edition is closed

If a “program” is only a paragraph on Tentang, it stays CMS content and is **not** promoted.

### 10.3 Case study: ICBB-AB2TI Award

Fits the generic concept well: repeating yearly editions, winner lists, about-copy, archives. Public URL should live under **Program**, not as a forever header item.

Do not merge with:

- Kompleks ICBB (contact/location)
- ICBB the partner institution (mentioned in copy)
- Teknologi Tani

### 10.4 Classification of legacy concepts

| Concept | Classification | Why |
|---|---|---|
| **ICBB-AB2TI Award** | **PROGRAM / INITIATIVE** (current + archive editions) | Repeating named effort with results; not a CMS-only blob forever, but early delivery can be landing + archived editions as content |
| **Provibio** | **AGRICULTURAL KNOWLEDGE** + **DOCUMENT / DOWNLOAD**; **COOPERATIVE SERVICE CANDIDATE** only if product later sells/distributes it as a service | Today a product/knowledge page with leaflets, not a yearly program and not empty CPT |
| **RMP / PT AB2TI PSN** | **COOPERATIVE CONTENT** now; **COOPERATIVE SERVICE CANDIDATE** later | Enterprise of Koperasi Pusat, not an AB2TI Award-style program |
| **Legacy WP `project` CPT** | **ARCHIVE / MIGRATION ONLY** (ignore) | API empty; do not create a module to match a vacant CPT |
| Bank Benih / pelatihan / advokasi (mission language) | **CMS PAGE** or future Program **if** product names them as initiatives | Today they are mission/footer copy, not operated programs |

**NEEDS PRODUCT DECISION:** which named efforts besides Award are officially Programs in 2.0 (Pelatihan, Advokasi, Bank Benih as program vs mission).

---

## 11. Admin Information Architecture

Conceptual staff navigation. **Do not implement the sidebar in this phase.** Group by **capability**, not by table or WordPress type.

Hypothesis improved from the prompt: Landing chrome stays under Content; Teknologi Tani is a Content channel; Users stay in System, never under Membership; Koperasi is a top-level capability equal to Membership, not a child of it.

```
Dashboard          ← operational home (auth). Not a dumping ground for every module.

Content
    Pages                  ← CMS informational pages (Tiptap)
    Publishing             ← berita / kegiatan / press / liputan (types/filters)
    Teknologi Tani         ← knowledge channel (editorial-first)
    Media                  ← platform File Manager
    Site landing           ← Hero, Welcome (homepage composition)
    Authoring tools        ← Block templates (not a business domain)

AB2TI Membership
    Applications
    Members
    Administration         ← status, card issuance tracking, notes
    Regional / organizational data   ← NEXT; hide until product defines structure

Koperasi Pusat
    Applications
    Members
    Cooperative pages      ← or “content” scoped to koperasi; may reuse Pages with IA discipline
    Services               ← placeholder until a real service exists

Community
    Submissions
    Moderation

Programs
    Programs
    Editions / results     ← when a program needs them

System
    Users                  ← accounts, not members
    Roles & permissions
    Platform settings
```

### 11.1 Rules for this hierarchy

- New AB2TI capabilities add a **group or a child**, not a random top-level item.
- Permissions later map to groups (membership.* vs koperasi.* vs cms.* vs publishing.*) — **not designed here**.
- Cooperative Administrator must not need Membership menus; Editorial Staff must not be forced through Membership to publish.
- Dashboard remains a landing for authenticated staff, not the authorization model.
- Do not add “Sermons”, “Events”, “Artikel”, “Culture”, or one item per publishing type at the top of admin.

### 11.2 Mapping to current kernel (validation only)

| Today | Conceptual home |
|---|---|
| Pages, Block templates, Welcome | Content |
| Heroes | Content → Site landing |
| File Manager | Content → Media |
| Users, Roles | System |
| Profile (Jetstream) | Account utility, not “AB2TI Settings” |
| Unused `access settings` | Do not treat as a product area |

---

## 12. Member-Facing Information Architecture

What an **authenticated person** could **eventually** see. Not first-release scope. One account may expose **multiple domain contexts** without merging them.

```
My Account                 ← User: profile, password, 2FA (existing kernel)

AB2TI Membership           ← shown only if this User is linked to an AB2TI Member
    Profile
    Status
    Documents
    Membership card

Koperasi Pusat             ← shown only if linked to a Cooperative Member
    Cooperative membership
    Cooperative services   ← later

Contributions              ← shown if contributor capability exists
    My submissions
```

### 12.1 Rules

- Empty domain sections stay hidden. A staff User with no membership sees Account (+ admin), not fake member menus.
- Linking is optional and later. Core membership works with **no** member-facing area.
- Fortify `/register` is **not** “join AB2TI.” Join is `/keanggotaan` application.
- Do not put admin Membership queues in the member-facing tree.

### 12.2 First-release member-facing

**None required.** Visitors apply; staff operate. Member-facing IA is NEXT after linking policy is decided.

---

## 13. Content Classification Matrix

| Item | Classification | Notes |
|---|---|---|
| Tentang AB2TI (visi/misi, org copy) | **CMS PAGE** | Tiptap Page |
| History / organizational subpages | **CMS PAGE** | Nested under Tentang |
| Contact / sekretariat | **CMS PAGE** (and/or site chrome) | Do not hide only in hardcoded footer long-term |
| Home landing | **CMS PAGE** fragments + composition | Hero/Welcome + teasers; not a news object |
| Berita | **PUBLISHING CONTENT** | Default publishing type |
| Kegiatan | **PUBLISHING CONTENT** | Type/filter, not header item |
| Press Release / Siaran Pers | **PUBLISHING CONTENT** | Type; currently mixed in WP |
| Media Coverage / Liputan Media | **PUBLISHING CONTENT** | Type; currently mixed in WP |
| Opini | **PUBLISHING CONTENT** (as Berita or tag) | Fifth type = **NEEDS PRODUCT DECISION** |
| Teknologi Tani landing | **CMS PAGE** or knowledge hub copy | Public area hub |
| Teknologi Tani articles | **AGRICULTURAL KNOWLEDGE** | Editorial-first; may reuse publishing editor |
| Gallery | **MEDIA / GALLERY** | Presentation; File Manager is infrastructure |
| Membership information (public) | **MEMBERSHIP CONTENT** delivered as **CMS PAGE** | Copy + CTA; not the member record |
| Membership application | **MEMBERSHIP CONTENT** (process) | Replaces external form eventually |
| Cooperative information (public) | **COOPERATIVE CONTENT** delivered as **CMS PAGE** | Separate from Keanggotaan |
| Cooperative application | **COOPERATIVE CONTENT** (process) | Separate journey |
| ICBB-AB2TI Award | **PROGRAM / INITIATIVE** | Plus archive editions |
| Award yearly winner pages | **PROGRAM / INITIATIVE** / **ARCHIVE / MIGRATION** | Migrate as editions |
| Provibio | **AGRICULTURAL KNOWLEDGE** + **DOCUMENT / DOWNLOAD** | Service/catalog = **NEEDS PRODUCT DECISION** |
| RMP / PT AB2TI PSN explanation | **COOPERATIVE CONTENT** | Operations = **NEEDS PRODUCT DECISION** |
| RMP journal posts | **PUBLISHING CONTENT** or **COOPERATIVE CONTENT** | **NEEDS PRODUCT DECISION** which channel |
| Category A/B shares | **STRUCTURED DOMAIN DATA — FUTURE** | Not CMS; not Cooperative Member for B |
| Tata niaga, SK varietas, SK RMP, leaflets | **DOCUMENT / DOWNLOAD** | Access (public vs member-only) = **NEEDS PRODUCT DECISION** |
| Varieties named in prose | **STRUCTURED DOMAIN DATA — FUTURE** | Keep in articles until a catalog is justified |
| Legacy WP `project` CPT | **ARCHIVE / MIGRATION ONLY** | Empty; do not productize |
| Bank Benih / pelatihan / advokasi as footer lines | **CMS PAGE** or future **PROGRAM / INITIATIVE** | **NEEDS PRODUCT DECISION** to promote |
| WP Sample Page | **ARCHIVE / MIGRATION ONLY** | Ignore |
| `/sermons`, `/events` kernel aliases | not content | URL residue; §15 |

---

## 14. Canonical URL Intent

Semantic families for AB2TI 2.0. **Do not implement routes in this phase.** Indonesian public paths. No church names.

When these families become application routes, they must be **reserved from CMS catch-all** (same class of decision as ADR 0003). Until then, do **not** create CMS pages that permanently occupy `/berita`, `/keanggotaan`, `/koperasi`, `/program`, `/publikasi`, `/teknologi-tani`, `/galeri` if those will be modules — Phase 1C should reserve them in policy.

| Family | Intent |
|---|---|
| `/` | Home |
| `/tentang` | Organization hub (CMS) |
| `/tentang/...` | Nested org pages |
| `/publikasi` | Publishing hub |
| `/berita` | Publishing type view |
| `/kegiatan` | Publishing type view |
| `/siaran-pers` | Publishing type view (optional until type is used) |
| `/liputan-media` | Publishing type view (optional) |
| `/teknologi-tani` | Knowledge area |
| `/teknologi-tani/...` | Knowledge articles / later structured entries |
| `/program` | Program index |
| `/program/...` | Named initiative (e.g. award slug) |
| `/keanggotaan` | Association membership information + apply |
| `/koperasi` | Koperasi Pusat information + apply |
| `/galeri` | Gallery presentation |
| `/kontak` | Contact |
| `/dokumen` | Public document index **if** product wants a single downloads area |

Item permalinks (future publishing) should live under their family (e.g. `/berita/...`), not under CMS `/{slug}` collision space.

**Out of canonical IA:** `/sermons`, `/events`, `/about` as English leftovers, `/register` as join-AB2TI, `/home` as WordPress duplicate.

Kernel-reserved paths (`/admin`, `/login`, `/dashboard`, `/user`, …) stay reserved.

---

## 15. Legacy URL Migration Intent

No routes are changed in this phase. Constitution still forbids renaming `/sermons` and `/events` without an IA ADR. This section is **intent** for that future ADR.

### 15.1 Current kernel public aliases

| URL | Classification | Intended destination (later) |
|---|---|---|
| `/` | **KEEP CANONICAL** | Home |
| `/about` | **REDIRECT LATER** | `/tentang` |
| `/contact` | **REDIRECT LATER** | `/kontak` |
| `/sermons` | **RETIRE** as canonical; **REDIRECT LATER** | `/teknologi-tani` |
| `/events` | **RETIRE** as canonical; **REDIRECT LATER** | `/publikasi` or `/berita` (**NEEDS DECISION** which) |

`/sermons` and `/events` must never remain canonical names.

### 15.2 Live WordPress families (ab2ti.org)

| Legacy | Classification | Intended 2.0 family |
|---|---|---|
| `/tentang-ab2ti/` | **REDIRECT LATER** | `/tentang` |
| `/teknologi-tani/` | **KEEP CANONICAL** (family) | `/teknologi-tani` |
| `/galeri/` | **KEEP CANONICAL** (family) | `/galeri` |
| `/anggota/` | **REDIRECT LATER** | `/keanggotaan` |
| `/koperasi-ab2ti-pusat/` | **REDIRECT LATER** | `/koperasi` |
| `/icbb-ab2ti-award/` and yearly pages | **REDIRECT LATER** | `/program/...` |
| `/provibio/` | **NEEDS DECISION** | `/teknologi-tani/...` vs stand-alone `/provibio` |
| `/category/berita/` and post permalinks | **REDIRECT LATER** | `/berita` / item URLs |
| `/category/teknologi-tani/` | **REDIRECT LATER** | `/teknologi-tani` |
| `/category/rmp/` | **NEEDS DECISION** | publishing vs `/koperasi/...` |
| `/home/` | **RETIRE** | `/` |
| WP sample page | **RETIRE** | — |

Exact redirect maps for every post slug are a **content-migration** task, not this IA.

---

## 16. Capability / Release Prioritization

No tickets. Broad stages only. Vision stays intact; the first slice must not attempt every future capability.

### 16.1 Validation of the suggested principle

Suggested: *Core organizational publishing and core association membership before advanced community/cooperative services.*

**Validated, with one refinement:**

- **Yes:** Operational Koperasi membership, community intake, member self-service, share/investor systems, and structured catalogs wait until the public site and association membership domain exist.
- **Refinement:** Koperasi **public information** (CMS pages, separate nav item, clear “not automatic” copy) belongs with the **public website**, not with “later cooperative services.” Shipping Tentang + Publikasi without a Koperasi **page** would hide a major public area of the current organization.
- **Refinement:** Association membership **operations** (intake + staff records) are CORE AB2TI 2.0; association **self-service login** is NEXT.
- **Refinement:** Publishing is required before community contribution, because contribution has nowhere coherent to land.

### 16.2 FOUNDATION / REQUIRED FIRST

- Keep kernel: auth (staff), ACL pattern, CMS Page + Tiptap, media, layouts, i18n chrome.
- Canonical public IA for org pages: Home, Tentang, Kontak, Koperasi **info**, Keanggotaan **info**.
- Stop treating `/sermons` and `/events` as product names in **documentation and future ADR**; implementation of redirects is not this phase.
- Do not enable public User registration.
- Do not build cooperative accounting.

This stage is largely “use CMS + landing correctly,” still without new domains.

### 16.3 CORE AB2TI 2.0

- Publishing backbone with at least Berita (and Kegiatan if editorial practice is ready).
- Teknologi Tani as its own public area (editorial list + hub).
- Association application + staff verification + member records (member login **not** required).
- Admin grouping: Content vs AB2TI Membership vs System.
- Program landing for Award as a Program instance (content + archives acceptable).
- Simple gallery **presentation** if needed (not a new DAM).
- Media continued as platform File Manager.

### 16.4 NEXT

- Member self-service (User linked to AB2TI Member).
- Additional publishing types (Siaran Pers, Liputan Media) if not in core.
- Regional / organizational data once product defines it.
- Koperasi application + cooperative member administration (still no accounting).
- Community submissions + moderation.
- Digital or in-app printable card.
- Document access rules (public vs member-only).

### 16.5 FUTURE / OPTIONAL

- Structured variety / seed catalog.
- Tata niaga enforcement.
- Category A/B share and investment operations.
- RMP/PT operational modules.
- Investor portal.
- Public member directory.
- Calendar/RSVP for kegiatan.
- Cooperative business services (supply, etc.).
- Rich editor-vs-publisher workflow.
- Multi-actor login beyond staff + linked members.

---

## 17. Product Decisions Established

These are IA/product decisions for later phases to respect. They are **not** ADRs until recorded as such; they are the Phase 1B baseline.

1. User ≠ AB2TI Member ≠ Cooperative Member.
2. Members may exist without an authenticated account.
3. AB2TI membership does not create Koperasi membership.
4. CMS Page + Tiptap is for informational/organization pages, not for recurring publishing archives.
5. Berita, Kegiatan, Siaran Pers, and Liputan Media share one publishing capability with distinct public types/filters.
6. Teknologi Tani is a separate public area, editorial-first, evolvable into structured knowledge without a new top-level brand.
7. Tiptap remains platform editing infrastructure; publishing should reuse it, not replace it.
8. Block templates remain authoring snippets, not application components.
9. Primary public nav is small: Beranda, Tentang, Publikasi, Teknologi Tani, Keanggotaan, Koperasi.
10. Award is a Program/Initiative instance under Program, not the header and not the program model.
11. Community contribution is moderated intake into publishing/knowledge, never direct CMS Page authoring.
12. Admin IA is capability-grouped; Membership and Koperasi are sibling top-level staff areas; Users stay in System.
13. Member-facing menus are optional later and are hidden unless a domain identity is linked.
14. Canonical public URLs are Indonesian semantic families; church aliases are not canonical.
15. Public User self-registration remains off; membership application is a domain journey.
16. Koperasi public information is part of the website slice; koperasi operations are not a prerequisite for association core, and are not foundation accounting.
17. Empty WP `project` CPT is not a product module.
18. File Manager stays platform media, distinct from member documents and from gallery-as-product.
19. ICBB location, ICBB partner, and ICBB-AB2TI Award stay conceptually distinct.
20. First implementation slice must not attempt community, cooperative finance, or seed catalogs.

---

## 18. NEEDS PRODUCT DECISION

Still required from stakeholders before or during Phase 1C (domain modeling), without blocking the IA baseline above.

### Identity

- Who authenticates in the first public release besides staff?
- Email verification for any actor?
- Fate of seeded kernel role name `user` (rename in product language even if the row remains for now).
- When (if ever) a Member is invited to become a User.

### Membership policy

- Member types; mandatory regional affiliation; renewal/expiry; membership numbers.
- What the Rp 50.000 payment is (card only vs dues) and whether using the Koperasi bank account is policy.
- Card: tracking only vs print vendor workflow vs digital in NEXT.
- Public member directory yes/no.
- Application field list (from current external form) as the real intake contract.

### Koperasi / enterprise

- When internal koperasi application replaces the external form.
- Whether Category B investors are a product actor.
- Whether any share/simpanan data is in-app or stays off-platform.
- RMP journal: Publikasi type vs Koperasi channel.
- Provibio: knowledge page vs cooperative service vs catalog.

### Publishing / knowledge

- Whether opini is a type.
- Whether Kegiatan needs calendar semantics (date/place) in core or only as an article type.
- Staff draft vs publish roles in core.
- Which WP posts migrate, rewrite, or archive.
- Provibio URL: keep `/provibio` or nest under Teknologi Tani.
- `/events` redirect target: `/publikasi` vs `/berita` vs `/kegiatan`.

### Programs / documents / community

- Official Program list beyond Award.
- Document sensitivity matrix (public vs member-only vs koperasi-only).
- Community: identified-but-unauthenticated intake vs member-only submission.
- Gallery: curated albums vs tagged media vs publishing galleries only.

### IA ADR

- Formal ADR to retire `/sermons` and `/events` as canonical (implementation still later).

---

## 19. Risks / Ambiguities

1. **Same bank account for association card fee and koperasi payments** — process coupling that can leak into product if payment is modeled too early.
2. **Occupying module slugs as CMS Pages** before routes exist — repeats `/sermons`. Reserve families in Phase 1C policy.
3. **Publishing vs Teknologi Tani reuse** — sharing an editor is good; sharing the Berita list is not.
4. **Staff who are also members** — UI must not mix admin queues into My Membership.
5. **Promoting footer “programs”** without owners — menu spam.
6. **Investor vs anggota koperasi** — Category B is the highest confusion risk after association vs koperasi.
7. **Gallery vs Media vs Documents** — three visitor words, one platform File Manager today.
8. **Home composition** — Hero/Welcome/ServiceEvents already compete; IA must not add a fourth unmanaged program strip.
9. **Community HTML** — any shortcut into Page.content is a security and editorial failure.
10. **Presbi aliases remaining live** until an ADR — public IA docs and production URLs will diverge; that is acceptable until 1C/implementation, but must not be forgotten.
11. **Over-building koperasi** because the legacy page is long (RMP, shares, investment).
12. **Under-building membership** by shipping only CMS copy and leaving Google Forms as the real system with no staff queue — that is a website, not a membership platform.
13. **Actor names copied into Spatie roles** too early — fragments ACL.

---

## 20. Inputs Required for Phase 1C

Phase 1C should turn this IA into **domain boundaries and implementation-ready decisions** still without opportunistic coding. It should **not** start from WordPress CPTs or Presbi models.

Needed inputs:

1. Stakeholder confirmation of §17 (especially identity and the six-item public nav).
2. Answers to the **blocking** subset of §18: first-release authenticators; membership application field list; document sensitivity; official program list; Provibio and RMP channel.
3. Content freeze list: which WP pages/posts/PDFs migrate into CMS Page vs Publishing vs Knowledge vs Program archive.
4. External form field inventory (association and koperasi) — labels only, for domain language.
5. Draft ADR: canonical URLs + redirect intent for `/about`, `/sermons`, `/events`, `/contact`.
6. Draft ADR: publishing capability separate from Page (no schema required in the ADR beyond “not Page”).
7. Draft ADR: User vs Member vs Cooperative Member (identity), extending ADR 0002.
8. Permission **group names as product areas** (content, membership, koperasi, community, programs, system) — still not a full ACL matrix.
9. Agreement on CORE vs NEXT vs FUTURE in §16 for the first build slice.
10. Explicit non-goals for 1C: no migrations, no member tables until the identity ADR exists, no cooperative finance.

Phase 1C should **not**:

- Design database schema as the first artifact
- Implement navigation
- Enable Fortify registration
- Create one module per publishing type or per WP CPT
- Treat this document as a sprint backlog

---

End of Phase 1B product & information architecture.
