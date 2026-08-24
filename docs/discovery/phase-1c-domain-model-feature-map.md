# Phase 1C — AB2TI Domain Model & Feature Map

**Status:** Domain analysis and feature mapping only. Not a schema. Not a technical architecture contract. Not tickets.  
**Date:** 2026-08-20  
**Primary inputs:**  
- `docs/discovery/phase-1a-ab2ti-domain-discovery.md`  
- `docs/discovery/phase-1b-product-information-architecture.md`  
**Canonical policy:** `docs/project/CONSTITUTION.md`

This document names **domain concepts**: bounded contexts, aggregates, value concepts, lifecycles, commands, capabilities, and events. It does not name database tables, SQL types, foreign keys, Eloquent relationships, Laravel namespaces, routes, or permission names.

Phase 1A and Phase 1B were not modified. No discovery contradiction required rewriting them. Modeling tensions are recorded in §24.

**Later addendum (does not rewrite this file):** Phase 1E-B confirms **AB2TI Awards** as an operated recurring business context (Program ≠ Edition; intake; official results; historical archive). That **supersedes** this document’s “thin Award as content / no awards engine” **for Award only**. See `docs/discovery/phase-1e-b-ab2ti-award-architecture-addendum.md`.

---

## 1. Executive Summary

AB2TI 2.0 is modeled as **one platform** with **several bounded contexts**. The smallest model that preserves Phase 1B is preferred over a noun-for-every-screen design.

**Identity.** User/Account is platform authentication. AB2TI Member and Cooperative Member are separate domain identities. A Member may exist with no Account. Linking is optional and later.

**Content.** CMS Page owns informational pages. Publication owns dated Publikasi items (Berita, Kegiatan, Siaran Pers, Liputan Media as **types**, not four domains). Agricultural Knowledge owns Teknologi Tani editorial items today and is the future home of structured knowledge. Tiptap and Media are **platform capabilities**, not domains.

**Membership.** Core association membership is staff-operated intake: Membership Application → review → AB2TI Member. Application status and member status are different lifecycles. Approval must not create cooperative membership. User creation is not a prerequisite.

**Deferred.** Cooperative operations, community contribution, member self-service, finance/shares, and seed catalogs are real contexts or future seams — not Core V1 aggregates.

**Core V1 (validated):** existing platform + public CMS + Publishing + Teknologi Tani editorial + association application/staff administration + public Koperasi information + a thin Program (Award as content/archive). Not a digital member portal and not cooperative accounting.

---

## 2. Domain Modeling Principles

1. Product / IA (1B) precedes this model. This model precedes technical architecture (1D). Tables do not drive the domain.
2. **User ≠ AB2TI Member ≠ Cooperative Member.**
3. **CMS Page ≠ Publication ≠ Knowledge Article ≠ Member.**
4. Prefer few aggregates. Applicant data lives on the application until approval; do not invent a third identity named Applicant.
5. Actors are not roles. Status names are conceptual, not columns.
6. Platform capabilities (auth, Tiptap, media, CMS Page, ACL pattern) are reused; they are not AB2TI business domains.
7. Do not invent membership classes, dues, renewal, or cooperative finance.
8. Do not model structured seed/variety catalogs. Leave a **seam** in Agricultural Knowledge.
9. Community never writes CMS Page content.
10. Reserved public URL families are owned by product areas, not by arbitrary CMS pages (policy; routing is 1D).
11. Copy-on-insert Block Templates are authoring tools, not domain components.
12. If a rule is unknown, mark **NEEDS PRODUCT DECISION** rather than inventing it.

---

## 3. Ubiquitous Language / Domain Glossary

Avoid implementation words (table, model, foreign key, route).

| Term | Meaning | Bounded context | What it is NOT | Ambiguity |
|---|---|---|---|---|
| **User / Account** | Authentication identity that can sign in. Today: staff. | Platform / Identity | Not a member. Not an applicant. Not “the seeded role named user.” | Kernel role name `user` is a collision; product language must not use it for anggota. |
| **Visitor** | Unauthenticated public person. | Public (no ownership) | Not an Account. | — |
| **Applicant** | Person filling an association application. A **role on a Membership Application**, not a lasting identity. | AB2TI Membership | Not a Member until approval. Not a User. | Do not persist Applicant as a separate identity. |
| **Membership Application** | Intake record for joining the association: submitted data, documents, and review state. | AB2TI Membership | Not Fortify registration. Not a CMS Page. Not a cooperative application. | — |
| **AB2TI Member** | Association membership identity after approval. | AB2TI Membership | Not User. Not Cooperative Member. Not Applicant. | “Anggota” on the legacy site mixed copy, forms, and documents. |
| **Cooperative Applicant** | Role on a Cooperative Application. | Koperasi Pusat | Not association Applicant. | — |
| **Cooperative Member** | Koperasi Pusat membership identity after cooperative approval. | Koperasi Pusat | Not AB2TI Member. Not Category B investor. | Legacy pays association card fees to a koperasi bank account — process, not identity. |
| **Contributor** | Person offering regional/community material for review. | Community Contribution | Not Editorial Staff. Not automatic publisher. | May also be an AB2TI Member. |
| **Submission** | Moderated intake item (text, files, type, status). | Community Contribution | Not a Publication. Not a CMS Page. | — |
| **Publication** | Dated, listable public editorial item in **Publikasi**. | Publishing | Not CMS Page. Not Knowledge Article. Not Member. | Legacy “Berita” mixed several types. |
| **Berita** | Publication type: news, commentary, current affairs. | Publishing | Not the whole publishing domain. | Includes opini unless product splits it. |
| **Kegiatan** | Publication type: organizational/regional activity. | Publishing | Not a calendar product unless later decided. Not `/events`. | May later want date/place. |
| **Siaran Pers** | Publication type: official statement. | Publishing | Not automatically distinct in legacy content. | Reserved type; may be unused in V1. |
| **Liputan Media** | Publication type: third-party coverage. | Publishing | Not the Media platform capability. | Reserved type. |
| **Teknologi Tani** | Public knowledge **area**. Editorial items live here today. | Agricultural Knowledge | Not Berita. Not `/sermons`. Not a seed catalog. | Hub copy vs article list. |
| **Knowledge Article** | Editorial item in Teknologi Tani. | Agricultural Knowledge | Not a Publikasi type. Not a variety record. | May reuse publishing *mechanics*. |
| **Program** | Named initiative that outlives a single news item. | Programs / Initiatives | Not ICBB the place. Not footer slogans automatically. Not empty WP “project.” | Award is an instance. |
| **Program Edition** | Optional cycle of a repeating Program (e.g. a year). | Programs / Initiatives | Not required for every Program. | Useful for Award; premature as a universal rule. |
| **Media / Media Asset** | Shared file used for display (image, public downloadable file). | Media (platform) | Not membership identity documents. Not “Galeri” the product by itself. | Same storage may hold sensitive files — access is the issue. |
| **Document / Domain Document** | File whose **meaning and access** belong to a business domain (e.g. KTP on an application). | Owning business context | Not equivalent to every File Manager item. | Public SK/PDF vs sensitive KTP. |
| **CMS Page** | Informational editable page (Tentang, visi/misi, history, contact). | CMS | Not Publication. Not Member. Not Application. Not Submission. | URL vs content ownership for `/keanggotaan` info. |
| **Region / organizational unit** | Future grouping of members or contributors (wilayah, kelompok, pengurus wilayah). | AB2TI Membership (future) | Not a Presbi congregation. | Structure unknown. |
| **Membership Card** | Evidence of association membership for the member (physical in current practice). | AB2TI Membership | Not a User session. Not a digital portal. | Issuance process unknown. |
| **Provibio** | Named agricultural product/knowledge subject with leaflets. | Agricultural Knowledge (today) | Not a Program. Not automatically a koperasi SKU. | May later be a cooperative service. |
| **RMP / PT AB2TI PSN** | Rice milling enterprise described as owned by Koperasi Pusat. | Koperasi Pusat (content today) | Not an AB2TI Program. Not association membership. | Operational role unknown. |
| **Editorial Staff** | Authenticated person who authors trusted public content. | Platform Identity + CMS/Publishing/Knowledge | Not Contributor. | Actor, not a required ACL role name. |
| **Membership Administrator** | Authenticated person who reviews applications and members. | Platform Identity + Membership | Not the Member. | — |
| **Publication Type** | Classifier on a Publication (Berita, Kegiatan, …). | Publishing | Not a bounded context. | — |
| **Account Link** | Optional future association between a User and a domain identity. | Platform Identity (later) | Not membership itself. | Who may link, 1:1 vs 1:n. |
| **ICBB (place)** | Sekretariat location (Kompleks ICBB). | CMS / contact | Not the Award. | Shared brand word. |
| **ICBB-AB2TI Award** | A Program instance with yearly results. | Programs | Not Teknologi Tani. Not ICBB-the-place. | — |
| **Category A / B shares** | Legacy investment/simpanan concepts around RMP. | Unmodeled; Koperasi-adjacent | Not Cooperative Member (especially B). | **NEEDS PRODUCT DECISION** |
| **Galeri** | Public presentation of media. | Presentation over Media | Not File Manager. Not Publishing. | Albums vs tags unknown. |
| **Site Landing** | Home composition (hero, welcome, teasers). | CMS / site chrome | Not Publication. Not Program domain. | Existing Hero/Welcome. |

---

## 4. Bounded Context Map

Distinction: **business bounded context** vs **platform capability**.

```
                    ┌──────────────────────────────────┐
                    │     PLATFORM CAPABILITIES        │
                    │  Identity  ·  CMS  ·  Tiptap     │
                    │  Media     ·  ACL pattern        │
                    └──────────────────────────────────┘
           ┌─────────────┬─────────────┬──────────────────┐
           ▼             ▼             ▼                  ▼
     Publishing    Agricultural   AB2TI Membership   Koperasi Pusat
                       Knowledge
           │
           └──── Community Contribution (handoff into Publishing / Knowledge)

     Programs / Initiatives  (referenced by Publishing; not a news type)
```

### 4.1 Platform / Identity — **platform capability**

| | |
|---|---|
| **Responsibility** | Authenticate Users; staff accounts; later optional Account Links. |
| **Owns** | User/Account; credentials; staff session; 2FA as platform concerns. |
| **May reference** | Nothing in membership/koperasi as source of truth. |
| **Must not own** | Member identity, application status, cooperative status, publications. |
| **Depends on** | Nothing in AB2TI domains. |

### 4.2 CMS — **platform capability** (informational content)

| | |
|---|---|
| **Responsibility** | Informational pages, site landing chrome, authoring snippets. |
| **Owns** | CMS Page; Hero; Welcome Section; Block Template (authoring). |
| **May reference** | Media assets. |
| **Must not own** | Members, applications, publications, submissions, program operations, cooperative operations. |
| **Depends on** | Identity (staff), Media, Tiptap. |

### 4.3 Publishing — **business context**

| | |
|---|---|
| **Responsibility** | Dated Publikasi: authoring, status, listing, type filters. |
| **Owns** | Publication; Publication Type; publication editorial status and public date. |
| **May reference** | Media; Program (by identity); later Region or Koperasi as labels; Submission after handoff. |
| **Must not own** | Program identity; Member identity; CMS Page; Knowledge Article identity; File Manager as a whole. |
| **Depends on** | Identity (staff), Tiptap, Media. |

### 4.4 Agricultural Knowledge — **business context** (thin now, seam later)

| | |
|---|---|
| **Responsibility** | Teknologi Tani public area; editorial knowledge now; structured knowledge later. |
| **Owns** | Knowledge Article (now); future structured subjects (not modeled). |
| **May reference** | Media; Documents; later Program. |
| **Must not own** | Publikasi types; Member; CMS of the whole org; seed trading enforcement. |
| **Depends on** | Identity (staff), Tiptap, Media. **Must not depend on Publishing as owner of knowledge identity.** May share *mechanics*. |

### 4.5 AB2TI Membership — **business context** (core)

| | |
|---|---|
| **Responsibility** | Association application, review, member identity, card tracking (minimal). |
| **Owns** | Membership Application; application status; AB2TI Member; member status; application documents (as domain documents); membership card as supporting concept. |
| **May reference** | Reviewing User; Media storage for files; optional future Region; optional future Account Link. |
| **Must not own** | User credentials; Cooperative Member; CMS/Publishing; payment ledger. |
| **Depends on** | Identity (staff reviewer); Media as storage only. Public membership *copy* may be authored via CMS/Tiptap but the **member record** is not a Page. |

### 4.6 Koperasi Pusat — **business context** (operations later; public copy now)

| | |
|---|---|
| **Responsibility** | Cooperative identity and, later, cooperative applications/members/services. Public explanation now. |
| **Owns** | (Later) Cooperative Application, Cooperative Member, cooperative status. (Now) cooperative informational content as *content*, not members. |
| **May reference** | Media; Publishing items about koperasi; RMP as described subject. |
| **Must not own** | AB2TI Member; association applications; Award program; accounting unless product later says so. |
| **Depends on** | CMS for public info in Core V1; Identity when operations start. |

### 4.7 Community Contribution — **business context** (later)

| | |
|---|---|
| **Responsibility** | Intake, moderation, revision, handoff to editorial. |
| **Owns** | Submission; submission status; original submitted payload. |
| **May reference** | Contributor identity (Member or name); resulting Publication or Knowledge Article after handoff. |
| **Must not own** | The public Publication identity after handoff; CMS Page; Member. |
| **Depends on** | Publishing and/or Knowledge (destination); preferably Membership (who may contribute). |

### 4.8 Programs / Initiatives — **business context** (thin)

| | |
|---|---|
| **Responsibility** | Named initiatives, optional editions, results as content. |
| **Owns** | Program; optional Program Edition; program landing content. |
| **May reference** | Media; Documents; Publications about the program. |
| **Must not own** | All news; ICBB-the-place; RMP operations; empty “project” leftovers. |
| **Depends on** | CMS/Tiptap for landing; Publishing for related items. |

### 4.9 Media / Document — **platform capability** with **domain access overlay**

| | |
|---|---|
| **Responsibility** | Store and retrieve files; conversions; staff library. |
| **Owns** | Media Asset bytes and technical metadata (filename, mime, size). |
| **May reference** | Uploading User. |
| **Must not own** | Whether a file is a KTP, a cover image, or a public SK; those meanings belong to the using domain. **Must not** treat all assets as public. |
| **Depends on** | Identity (staff upload). **Used by** CMS, Publishing, Knowledge, Membership, Koperasi, Community. |

Tiptap is **not** a bounded context. It is an editing capability inside CMS, Publishing, and Knowledge for **trusted staff**.

---

## 5. Domain Ownership Matrix

Source of truth is a single context. Others **reference**.

| Concept | Source of truth | Others may |
|---|---|---|
| User authentication | Platform / Identity | — |
| Staff permission to admin tools | Platform / Identity (ACL pattern) | UI may hide menus |
| CMS Page content & informational slug (under CMS-owned families) | CMS | — |
| Publication identity, type, editorial status, publish time | Publishing | Community references after handoff; Program referenced *from* Publication |
| Knowledge Article identity | Agricultural Knowledge | Publishing must not absorb it as Berita |
| Program identity | Programs | Publishing may reference a Program |
| Program edition identity (if used) | Programs | — |
| Membership Application & application status | AB2TI Membership | — |
| AB2TI Member identity & member status | AB2TI Membership | Identity may later *link*; Community may reference contributor |
| Membership Card issued/not (minimal) | AB2TI Membership | — |
| Application documents (meaning + access) | AB2TI Membership | Media stores bytes |
| Cooperative Application & status | Koperasi Pusat | — |
| Cooperative Member identity & status | Koperasi Pusat | — |
| Submission & moderation status | Community Contribution | Publishing/Knowledge receive a **new** editorial item |
| Media technical metadata | Media | — |
| Cover image *as used on a Publication* | Publishing (which asset, alt) | Media owns the file |
| SEO fields on a Page or Publication | Owning content context | Presentation reads them |
| Site Hero / Welcome | CMS / site chrome | Home composes teasers from Publishing by **reference** |
| ICBB office address | CMS / contact | Not Programs |
| RMP explanation copy | Koperasi content | Publishing may run related news |
| Provibio explanation | Agricultural Knowledge | — |

**Avoid duplicated ownership:** do not store “is member” on User; do not store member profile in CMS Page; do not let Submission replace Publication after publish.

---

## 6. Identity / Account Linkage Model

Conceptual only. No keys.

```
User / Account  (optional for members)
      │  optional later links
      ├── AB2TI Member
      └── Cooperative Member
```

### 6.1 Requirements (established)

| Requirement | Rule |
|---|---|
| Optional linkage | Core membership works with **zero** Accounts on the Member. |
| Multiple contexts | One person may be staff User, AB2TI Member, and Cooperative Member — still three concepts. |
| Staff without membership | Normal. Admin User must not require a Member. |
| Member without account | Normal for Core V1. |
| Cooperative member without association membership | Allowed by known legacy (koperasi open to public). |
| Cooperative member without User | Same as association: allowed. |
| Application without User | Required: public intake. |
| Join ≠ register | Membership Application is not Account registration. |

### 6.2 Linkage hypotheses (not implementation)

- A User **may** later be linked to at most one AB2TI Member and at most one Cooperative Member — **NEEDS PRODUCT DECISION** if a person could hold two association records.
- An AB2TI Member **should** link to at most one User (avoid two logins for one member) — **NEEDS PRODUCT DECISION** (family/shared accounts).
- Linking is an explicit act (invite, claim, staff attach), not a side effect of approval.
- Unlinking must not delete the Member.

### 6.3 Policy questions

- Who besides staff gets an Account in the first release? **Working assumption: staff only.** Overturning this expands Core V1.
- Who initiates link: staff, member, both?
- Must email on the Member match the User?
- Email verification? **NEEDS PRODUCT DECISION**; does not affect Member existence.

---

## 7. AB2TI Membership Domain

Staff-operated association membership. Simplest aggregates: **Membership Application** and **AB2TI Member**.

### 7.1 Concept classification

| Concept | Classification | Notes |
|---|---|---|
| Membership Application | **CORE DOMAIN ENTITY** | Intake aggregate. |
| AB2TI Member | **CORE DOMAIN ENTITY** | Created on approval, not before. |
| Applicant data (name, contact, address, etc.) | **VALUE / ATTRIBUTE CONCEPT** | Lives on the Application; copied/summarized onto Member at approval. Not its own identity. |
| Application documents | **SUPPORTING CONCEPT** | Domain documents (photo, identity scan, payment proof as **process artifact**). |
| Administrative review | **SUPPORTING CONCEPT** | Decision + notes on the Application. Not a separate identity. |
| Review notes / revision request | **SUPPORTING CONCEPT** | History on the Application. |
| Membership status | **VALUE / ATTRIBUTE CONCEPT** | On Member. Separate from application status. |
| Application status | **VALUE / ATTRIBUTE CONCEPT** | On Application. |
| Membership Card | **SUPPORTING CONCEPT** | Minimal: issued or not; identifiers if known. Not a card-design product. |
| Member profile/contact after approval | **VALUE / ATTRIBUTE CONCEPT** | On Member. |
| Reviewing administrator | **PLATFORM REFERENCE** | User who reviewed. |
| Account Link | **FUTURE CAPABILITY** | Optional. |
| Region / organizational association | **FUTURE CAPABILITY** | Structure unknown. |
| Member types/classes | **NEEDS PRODUCT DECISION** | Do not invent. |
| Dues, expiry, renewal | **NEEDS PRODUCT DECISION** | Do not invent. |
| Member directory | **FUTURE CAPABILITY** | — |
| Digital / printable card layout | **FUTURE CAPABILITY** | — |
| Tata niaga enforcement | **FUTURE CAPABILITY** | Policy vs software. |
| Applicant self-service status view | **FUTURE CAPABILITY** | Staff contact may suffice. |

### 7.2 Commands (conceptual)

On Application: Submit; Start review; Request revision; Resubmit; Reject; Approve.

On Member: Update profile (staff); Record card issued; (later) Suspend / Resign — only if product defines them.

Approve **creates** the Member. Reject does **not** create a Member.

### 7.3 What not to model

- Applicant as a third aggregate.
- Payment as a financial bounded context.
- Automatic Cooperative Member.
- User created on approve.

---

## 8. Membership Lifecycles

**Application status and member status are different.** An approved application is finished; the Member then has its own life.

### 8.1 Application status (minimal meaningful)

```
submitted
    → under review
         ├── revision required → (resubmitted) → under review
         ├── rejected          (terminal)
         └── approved          (terminal; Member activated)
```

This matches Phase 1B core: submit, verify, approve / reject / revision.

Unknown, **not** in the minimal set:

- withdrawn by applicant
- expired unreviewed
- duplicate merge

**NEEDS PRODUCT DECISION:** may a rejected person apply again? If yes, that is a **new Application**, not revival of the old one (hypothesis).

### 8.2 Member status (minimal meaningful)

```
(no Member exists)
    → active     (on application approved)
```

That is sufficient for Core V1.

Do **not** add as core without policy:

| Rule | Status |
|---|---|
| Membership classes/types | **NEEDS PRODUCT DECISION** |
| Expiry | **NEEDS PRODUCT DECISION** |
| Renewal | **NEEDS PRODUCT DECISION** |
| Suspension | **NEEDS PRODUCT DECISION** |
| Resignation | **NEEDS PRODUCT DECISION** |
| Termination | **NEEDS PRODUCT DECISION** |
| Dues | **NEEDS PRODUCT DECISION** |
| Card validity period | **NEEDS PRODUCT DECISION** |
| Inactive / deceased | **NEEDS PRODUCT DECISION** |

### 8.3 Card (minimal)

Supporting flags/concepts: **not issued** | **issued**. Print vendor workflow, digital card, and validity dates are **NEEDS PRODUCT DECISION** / FUTURE. Unknown fees (legacy Rp 50.000) are process artifacts on the Application, not a dues engine.

---

## 9. Publishing Domain

One **Publication** concept. Types are classifiers, not four contexts.

### 9.1 Core concept

**Publication** — dated public editorial item in Publikasi.

**Publication Type** — Berita | Kegiatan | Siaran Pers | Liputan Media.

Core V1 may **use** Berita and Kegiatan and **reserve** the other two types so they are not shoved into CMS Pages.

Opini stays **Berita** (or a label) unless product splits it.

### 9.2 Concern placement

| Concern | Belongs to |
|---|---|
| Publication identity | **DOMAIN DATA** |
| Publication Type | **DOMAIN DATA** (value) |
| Editorial status (draft / published) | **DOMAIN DATA** |
| Publication date | **DOMAIN DATA** |
| Title | **DOMAIN DATA** |
| Slug (within publishing URL family) | **DOMAIN DATA** / presentation contract |
| Body (trusted HTML) | **CMS/EDITOR CAPABILITY** (Tiptap) owned as content of Publication |
| Excerpt | **SEO / PRESENTATION** (optional; can derive) |
| Cover media | **MEDIA CAPABILITY** referenced by Publication |
| Inline images | **MEDIA CAPABILITY** + editor |
| Attachments (extra files) | **MEDIA** or **DOCUMENT**; only if needed |
| Author as User | **PLATFORM REFERENCE** optional |
| Byline / attribution string | **DOMAIN DATA** or presentation; string is enough for V1 |
| Taxonomy beyond type (tags, region) | **NEEDS PRODUCT DECISION** |
| SEO title/description | **SEO / PRESENTATION** |
| Related Program | **REFERENCE** to Programs, not ownership |
| Calendar (place, start/end) for Kegiatan | **NEEDS PRODUCT DECISION**; not required to have the type |
| Editor vs publisher workflow | **FUTURE** |
| Schedule-in-future | **FUTURE** |

Do not create separate entities for Type, Status, SEO, or Author in the domain sense unless behavior requires it. Type and status are values on Publication.

### 9.3 Editorial status (minimal)

`draft` → `published` (and unpublish back to draft). Enough for Core V1. Not the membership lifecycle.

### 9.4 What Publishing must not do

- Store Tentang pages.
- List Teknologi Tani articles as Berita by default.
- Be the source of truth for Program.
- Accept Contributor HTML as already-public content.

---

## 10. Agricultural Knowledge Boundary

### 10.1 Today

**Knowledge Article** in the Agricultural Knowledge context: editorial, listable under Teknologi Tani, trusted staff, Tiptap + media.

Hub/intro copy may be a CMS Page **or** a knowledge hub — presentation choice. The **articles are not CMS Pages** and **not Publikasi types**.

### 10.2 Architectural seam (without a rewrite)

```
TODAY                         LATER
Knowledge Article             Knowledge Article (unchanged as editorial)
      │                              │
      │                       structured subjects
      │                       (variety, crop, method, …)
      │                              │
      └── same public area: Teknologi Tani
          same staff editor capability
          Publishing remains Publikasi-only
```

Rules that keep the seam cheap:

1. Knowledge Article identity is **not** a Publication Type.
2. Shared **platform** (Tiptap, Media, draft/published) is allowed.
3. Public IA stays one door: Teknologi Tani.
4. Future structured records are **new concepts in this context**, optionally **referenced** by articles — not a migration of all articles into a catalog.
5. Do not model seed, variety, crop, cultivation method, or technology as entities now.

Provibio is a Knowledge subject (page + documents) until product assigns it elsewhere.

---

## 11. Koperasi Pusat Domain

Independent of association membership. Same *pattern* as association intake later; **not** the same aggregates, status, or queue.

### 11.1 Concepts

| Concept | Now / later | Classification |
|---|---|---|
| Cooperative informational content | Core V1 public site | Content (CMS/Tiptap) **about** koperasi; not a member record |
| Cooperative Application | NEXT | Core entity **when operations start** |
| Cooperative Applicant | NEXT | Role on that application |
| Administrative review | NEXT | Supporting |
| Cooperative Member | NEXT | Core entity when operations start |
| Cooperative membership status | NEXT | Separate lifecycle from AB2TI Member |
| Cooperative services | FUTURE | Placeholder |
| Category A/B, simpanan, investment | Unmodeled | **NEEDS PRODUCT DECISION** — not financial entities |
| Investor | Unmodeled | **Not** Cooperative Member |

Do not assume field-by-field parity with association applications (legacy: different fees, share language, open to public).

### 11.2 RMP / PT AB2TI PSN

**Classification: COOPERATIVE CONTENT** today; **COOPERATIVE SERVICE CANDIDATE** later; **not** a Program.

Operational plant, logistics, or PT administration: **SEPARATE FUTURE DOMAIN** or still cooperative services — **NEEDS PRODUCT DECISION**. Do not model.

### 11.3 Core V1 koperasi

Public information and a separate nav/IA entry. **No** Cooperative Member aggregate in Core V1. External application form may remain until NEXT.

---

## 12. Community Contribution Domain

Later than Core V1. Depends on Publishing (and/or Knowledge) existing as a destination.

### 12.1 Concepts

| Concept | Role |
|---|---|
| Contribution Submission | Core entity of this context |
| Contributor attribution | Name + optional Member reference; Account optional |
| Submission type | Value (activity, article, report, photo set, announcement, other) |
| Submitted content / media | Payload on the Submission; constrained, not staff Tiptap |
| Moderation / editorial decision | Supporting on Submission |
| Revision request | Supporting |
| Publication handoff | Reference from Submission to a **newly created** Publication or Knowledge Article |

### 12.2 When approved: A vs B

| | A. Submission *becomes* a Publication | B. Submission remains; a separate Publication is created |
|---|---|---|
| Audit | Original easily overwritten | Original + moderation trail kept |
| Editing | Mixes untrusted payload with staff HTML | Staff publish in the publishing/knowledge editor |
| Identity | Submission ID = public ID | Public ID is the Publication |
| Rejection after publish | Awkward | Submission history independent |

**Prefer B.** Preserve moderation/audit history. Approval means: *allowed to be used as source material*; Editorial Staff creates/edits the public item; Submission status becomes accepted/handed-off (or rejected/revision).

The public aggregate is never the Submission. The CMS Page is never the destination.

---

## 13. Programs / Initiatives Domain

ICBB-AB2TI Award is a **case study**, not the type system.

### 13.1 Concepts

| Concept | Classification | Notes |
|---|---|---|
| Program | **CORE** (thin) | Named initiative + landing content. |
| Program Edition / Cycle | **SUPPORTING**; useful for Award years | **Premature** as mandatory for every Program. V1 may record editions as structured years **or** as archived content under the Program. Do not build an awards engine. |
| Program content | Editor capability | Landing HTML. |
| Result / recipient information | **VALUE / CONTENT** for V1 | Winner lists can be content. Recipient as entity is FUTURE if operations need it. |
| Related publications | **REFERENCE** | Publishing owns those items. |
| Media / documents | References | — |

### 13.2 Classification (not all are Programs)

| Item | Classification |
|---|---|
| ICBB-AB2TI Award | **Program** (instance) + archive editions as content or supporting Edition |
| Provibio | **Not a Program** — Agricultural Knowledge + documents |
| RMP / PT AB2TI PSN | **Not a Program** — Cooperative content / future service |
| Legacy WP `project` CPT | **Ignore** — empty, not a domain |

Bank Benih / pelatihan / advokasi remain CMS or mission copy until product names them as Programs.

---

## 14. CMS Boundary

### 14.1 Page should own

- Tentang AB2TI, visi/misi, history, organizational information
- Contact / sekretariat (unless kept as chrome)
- Static **explanation** of membership and koperasi **as copy**
- Other informational pages that are not lists of dated items

Existing Hero, Welcome Section, Block Templates stay **site/authoring chrome**, not AB2TI domains.

### 14.2 Page must not become

- Member records
- Membership or cooperative applications
- Publications or Knowledge Articles
- Community submissions
- Program **operational** records (a Program landing may *use* Tiptap; the Program identity is not “just a Page” if it has editions/results — V1 may still *render* landing via page-like editing)
- Cooperative operational records
- Identity documents
- The Publikasi archive

### 14.3 Slug ownership (policy, not routing)

**CMS-owned families (examples):** `/tentang` and nested informational pages.

**Product-owned families (CMS must not occupy as an arbitrary Page):**  
`/publikasi`, `/berita`, `/kegiatan`, `/siaran-pers`, `/liputan-media`, `/teknologi-tani` (list/detail), `/program`, `/galeri`, `/keanggotaan`, `/koperasi`, `/dokumen` (if used).

**Tension (not a 1A/1B error):** membership/koperasi *copy* is informational (CMS-like authoring) while `/keanggotaan` and `/koperasi` are product URL families. **Rule:** the product area owns the first segment; intro copy is authored inside that area (page-like editor allowed). Do not create a competing CMS Page whose slug is `berita` or `keanggotaan`.

Kernel-reserved segments (login, admin, register, …) remain reserved (ADR 0003). `/sermons` and `/events` are not canonical product names.

---

## 15. Media & Document Boundary

Do not redesign File.

### 15.1 Two uses of a file

| | **Media Asset** | **Domain Document** |
|---|---|---|
| Example | Publication cover; gallery photo; page image; public leaflet | KTP scan; applicant photo for card; payment proof |
| Meaning owner | Using content context (which image is the cover) | Membership / Koperasi / Community |
| Default visibility | May be public if used on a public page | **Not public**; staff (and later the owning member) only |
| Lost File Manager listing | Inconvenient | **Confidentiality incident** |

A public PDF (tata niaga, SK) is a Media Asset **or** a classified public Domain Document. **NEEDS PRODUCT DECISION** for member-only vs public downloads. Application identity files are **not** that question — they are sensitive by nature.

### 15.2 What metadata belongs where

- Technical (size, mime, checksum): Media capability.
- Caption/alt for a cover: using domain.
- “This is a KTP for application X”: Membership.
- Access rule: **domain-enforced**, not “anyone with media.view.”

### 15.3 Flags for Phase 1D (security)

- Public File Manager JSON must not return application documents.
- Public `/storage` URLs must not be guessable for sensitive files (authorization on download).
- Staff `media.*` must not imply access to membership documents.
- Untrusted community uploads stay isolated until handoff.
- Tiptap `v-html` remains **staff-trusted** only; community HTML is not stored as Page/Publication body until rewritten by staff.
- Force-delete of a Media Asset can break public HTML that inlined URLs (known CMS behavior) — publishing should prefer stable references where possible (technical, 1D).

---

## 16. Conceptual Domain Events

Named to explain transitions and later integration. Not classes, not a message bus.

| Event | Why it matters |
|---|---|
| `MembershipApplicationSubmitted` | Intake exists; staff queue. |
| `MembershipApplicationRevisionRequested` | Applicant must change something; application not a Member. |
| `MembershipApplicationRejected` | Terminal; no Member. |
| `MembershipApplicationApproved` | Causes member creation. |
| `MemberActivated` | Association identity exists. |
| `MembershipCardIssued` | Supporting; physical process tracked. |
| `PublicationPublished` / `PublicationUnpublished` | Public list changes. |
| `KnowledgeArticlePublished` | Teknologi Tani list changes; not Publikasi. |
| `ProgramPublished` | Optional; landing visible. |
| `CommunitySubmissionSubmitted` | Later; queue. |
| `CommunitySubmissionAccepted` | Later; handoff allowed. |
| `PublicationCreatedFromSubmission` | Later; B-model link. |
| `CooperativeApplicationApproved` | Later; must **not** emit `MemberActivated`. |
| `AccountLinkedToMember` | Later; self-service possible. |

Omit speculative integration events (payments, share issuance, variety registered).

---

## 17. Domain Invariants

Protect regardless of UI.

1. Approving AB2TI membership **must not** create cooperative membership.
2. Approving cooperative membership **must not** create AB2TI membership.
3. Creating an AB2TI Member **must not** require a User.
4. Submitting a Membership Application **must not** require a User.
5. A Membership Application is **not** a Member until approved.
6. Community Submission **must not** become a CMS Page and **must not** be publicly rendered as unreviewed HTML.
7. CMS Page **must not** be the store for Member, Application, Publication, Submission, or cooperative operations.
8. Publication **must not** be the source of truth for Program identity.
9. Knowledge Article **must not** appear as a Publikasi type by default.
10. Staff User **must not** be treated as Member by existing.
11. Identity documents on an application **must not** be treated as public Media Assets.
12. Tiptap/Page HTML is **trusted-staff only**.
13. Block Template insertion does not create a live domain component.
14. `/sermons` and `/events` are **not** domain names.
15. ICBB-the-place is **not** the Award Program.
16. Category B investor is **not** a Cooperative Member unless product later says so (until then: do not model as member).
17. Public User self-registration is **not** join-AB2TI (ADR 0002).
18. Product URL families listed in §14.3 **must not** be claimed as arbitrary CMS Pages.

Unknown policy (classes, expiry, dues) is **not** stated as an invariant.

---

## 18. Feature Map

Organized by capability. Not controllers or tables.

Legend: **FOUNDATION EXISTING** · **CORE V1** · **NEXT** · **FUTURE** · **NEEDS PRODUCT DECISION**

### Public / CMS

| Feature | Stage | Depends on |
|---|---|---|
| Informational pages (CRUD, public render) | FOUNDATION EXISTING | Identity, Media, Tiptap |
| SEO meta on pages | FOUNDATION EXISTING | CMS |
| Site landing (Hero, Welcome) | FOUNDATION EXISTING | Media |
| Tentang / kontak / org copy in canonical IA | CORE V1 | CMS |
| Public Koperasi **information** | CORE V1 | CMS |
| Public Keanggotaan **information** | CORE V1 | CMS |
| Home teasers from Publishing | CORE V1 | Publishing |
| Canonical slug policy / reserved families | CORE V1 (policy) | 1D routing |
| Legacy URL redirects | NEXT (after ADR) | IA ADR |
| Auto menu from pages | FUTURE | — |

### Publishing

| Feature | Stage | Depends on |
|---|---|---|
| Publication management (staff) | CORE V1 | Tiptap, Media, Identity |
| Editorial status draft/published | CORE V1 | Publication |
| Public listing/detail | CORE V1 | Publication |
| Type: Berita | CORE V1 | Publication |
| Type: Kegiatan | CORE V1 | Publication |
| Types: Siaran Pers, Liputan Media | NEXT (reserved now) | Publication |
| Cover media | CORE V1 | Media |
| Byline string | CORE V1 | — |
| Opini as own type | NEEDS PRODUCT DECISION | — |
| Kegiatan calendar/RSVP | FUTURE / NEEDS PRODUCT DECISION | — |
| Editor vs publisher roles | FUTURE | Identity |
| Related Program reference | CORE V1 optional | Programs thin |

### Agricultural Knowledge

| Feature | Stage | Depends on |
|---|---|---|
| Teknologi Tani hub + article list/detail | CORE V1 | Tiptap, Media |
| Provibio as knowledge + downloads | CORE V1 (content) | Knowledge, Media |
| Structured variety/seed catalog | FUTURE | Knowledge seam |
| Tata niaga as enforced rule | FUTURE | Membership + policy |

### AB2TI Membership

| Feature | Stage | Depends on |
|---|---|---|
| Public apply (intake) | CORE V1 | Membership; Media storage |
| Application review / revision / reject / approve | CORE V1 | Membership, Identity (staff) |
| Member record administration | CORE V1 | Membership |
| Card issued tracking (minimal) | CORE V1 | Member |
| Application documents (sensitive) | CORE V1 | Membership + Media overlay |
| Member Account / self-service | NEXT | Account Link |
| Digital / printable card | NEXT / FUTURE | Card |
| Regional structure | NEXT / NEEDS PRODUCT DECISION | — |
| Renewal / dues | NEEDS PRODUCT DECISION | — |
| Member directory | FUTURE / NEEDS PRODUCT DECISION | Privacy |
| Applicant login to track status | FUTURE | Identity |

### Koperasi Pusat

| Feature | Stage | Depends on |
|---|---|---|
| Public information | CORE V1 | CMS |
| Application + member operations | NEXT | Koperasi context, Identity |
| Cooperative services | FUTURE | Operations |
| Shares / investment / accounting | NEEDS PRODUCT DECISION / FUTURE | Do not start |
| RMP operations | NEEDS PRODUCT DECISION / FUTURE | — |

### Community

| Feature | Stage | Depends on |
|---|---|---|
| Submission + moderation + handoff | NEXT | Publishing and/or Knowledge; preferably Member |
| Contributor portal | FUTURE | Account Link and/or Member |

### Programs

| Feature | Stage | Depends on |
|---|---|---|
| Award as Program landing + archive content | CORE V1 (thin) | Tiptap, Media |
| Program index | CORE V1 thin or CMS | Program |
| Structured Edition engine | NEXT if Award years need it | Program |
| Other official programs | NEEDS PRODUCT DECISION | — |
| Recipient operations | FUTURE | — |

### Media / documents / gallery

| Feature | Stage | Depends on |
|---|---|---|
| File Manager (staff) | FOUNDATION EXISTING | Identity |
| Public gallery presentation | CORE V1 optional / NEXT | Media |
| Domain-document authorization | CORE V1 (required for intake) | Membership + 1D |
| Gated public/member PDFs | NEXT / NEEDS PRODUCT DECISION | Sensitivity matrix |

### Platform / System

| Feature | Stage | Depends on |
|---|---|---|
| Staff login, password reset, 2FA | FOUNDATION EXISTING | — |
| Users / roles admin | FOUNDATION EXISTING | — |
| Public registration | Off (KEEP) | ADR 0002 |
| Account Link | NEXT | Identity + Membership |
| API as public AB2TI API | FUTURE | — |

---

## 19. Proposed Core V1 Boundary

### 19.1 Candidate (from Phase 1B)

A. Existing platform foundation  
B. Public CMS  
C. Publishing  
D. Teknologi Tani editorial  
E. Core AB2TI membership intake + staff administration  
F. Public Koperasi information  
G. Programs/public archive where needed  

Deferred: member self-service, member portal, community workflow, cooperative operations/finance, structured catalog.

### 19.2 Dependency check

| Piece | Can it stand alone? | Verdict |
|---|---|---|
| A | Yes | Required |
| B without C | Yes (brochure site) | Insufficient for “platform” vision but valid website |
| C without E | Yes | Website with news; not membership service |
| E without C | Yes | Intake without a living public site |
| D without C | Yes (parallel editorial) | Needed for IA (primary nav) |
| F | Yes via B | Required so Koperasi is not hidden |
| G | Award as content under Program or CMS | Thin G is enough; no awards engine |
| Community | Needs C (and ideally E) | Defer |
| Koperasi operations | Needs F + identity; independent of E records | Defer |

### 19.3 Recommendation

**Accept A–F plus thin G**, with these precise cuts:

**In:**

- Staff-only Accounts.
- CMS for Tentang, kontak, keanggotaan copy, koperasi copy.
- Publication with Berita + Kegiatan; other types reserved.
- Knowledge Article for Teknologi Tani; Provibio as knowledge content.
- Membership Application + review + AB2TI Member + minimal card tracking + sensitive application documents.
- Award as a thin Program (landing + historical content).
- Media as now, **plus** the rule that application documents are not public library items.

**Out:**

- User for members; Account Link.
- Cooperative Application/Member.
- Community Submission.
- Siaran Pers / Liputan Media as required launch types.
- Structured editions engine, recipient entities, catalogs, finance, gallery-as-DAM.

**Why not drop E:** Phase 1B warned that CMS-only + Google Forms is a website, not AB2TI 2.0. E is the smallest membership service.

**Why not drop D:** It is a primary public area; folding it into Berita or `/sermons` violates IA.

**Why thin G:** Award is prominent historically; a full Program edition system is premature.

**Working assumptions Core V1 proceeds on:** staff-only login; in-app association intake using the **known legacy packet** (identity/contact, photo, identity document, payment proof) plus staff notes; no member types/renewal; card = issued/not issued.

---

## 20. Cross-Domain Dependencies

```
Identity (staff)
    → CMS, Publishing, Knowledge, Membership (reviewer), Media
Media (storage)
    → used by all content and by Membership documents (access overlay)
CMS
    → public info for Membership & Koperasi (copy only)
Publishing
    → may reference Program
    → later receives handoff from Community
Knowledge
    → independent identity from Publishing
    → later receives handoff from Community
Membership
    → must not depend on Koperasi
    → later optional Account Link from Identity
    → later Contributor often references Member
Koperasi
    → must not depend on Membership records
    → Core V1 depends only on CMS + Identity (staff to edit pages)
Community
    → Publishing/Knowledge; preferably Membership
Programs
    → Tiptap/Media; Publishing references Program
```

Forbidden dependencies: Membership → Koperasi on approve; Publishing → owning Program; Community → CMS Page; User → required by Member.

---

## 21. NEEDS PRODUCT DECISION

Carried from 1B, with Core V1 impact in §22–§23.

- Who besides staff receives login in first release  
- Email verification  
- Seeded kernel role name `user` (product language)  
- When a Member is invited to become a User; link cardinality  
- Membership types/classes  
- Dues / what Rp 50.000 is; bank account policy  
- Renewal, expiry, suspension, resignation, termination  
- Membership number public?  
- Regional structure; mandatory on apply?  
- Reapply after reject  
- Card: tracking vs vendor print vs digital; validity  
- Member directory  
- Full application field list beyond legacy packet  
- Document sensitivity matrix (public vs member-only vs koperasi-only) for **non-application** PDFs  
- Opini as type  
- Kegiatan calendar semantics  
- Staff editor vs publisher  
- WP migration keep/rewrite/archive  
- Provibio URL and whether it is a cooperative service  
- Koperasi in-app application timing  
- Cooperative membership requirements vs association  
- Investor actor; Category A/B shares in-app  
- RMP/PT product role; RMP journal channel  
- Official Program inventory beyond Award  
- Gallery: albums vs tags vs publishing-only  
- Community: anonymous identified vs member-only  
- `/events` redirect target; `/provibio` vs nested knowledge  
- Formal IA ADR for `/sermons`, `/events`, `/about`, `/contact`

---

## 22. Decisions That Block Core V1

Only these **block the proposed Core V1 boundary** if answered against the working assumptions:

| Decision | Why it blocks |
|---|---|
| **Non-staff login required in first release** | Forces Account Link, member portal, and security scope into V1. |
| **Association intake stays external-only (no in-app application)** | Removes E; Core V1 becomes a CMS+news site, not the proposed membership service. |
| **Legacy application packet is legally insufficient and the real fields are unknown** | Cannot specify Application contents. (If stakeholders **accept the known legacy packet** as V1 minimum, this does **not** block.) |
| **Identity documents must be stored as ordinary public media** | Violates invariant 11; intake cannot ship. This is a constraint, not a preference. |

Staff-only + in-app intake + sensitive documents + legacy packet as minimum = **Core V1 can proceed**.

---

## 23. Decisions That Can Be Deferred

### Does not block Core V1

Member types; dues/renewal/suspension; regional tree; directory; digital card layout; opini type; Siaran Pers/Liputan as launch types; editor vs publisher; WP full inventory (can migrate incrementally); koperasi operations timing; cooperative field list; investor/shares; RMP operations; Provibio as SKU; programs beyond Award; community rules; email verification; seeded role rename; `/events` exact redirect target; gallery album model; calendar for Kegiatan.

### Blocks a later feature (not V1)

| Decision | Blocks |
|---|---|
| Account linking policy | Member self-service |
| Regional structure | Regional admin, contributor routing |
| Renewal/expiry | Renewal feature |
| Sensitivity matrix for downloads | Gated document library |
| Investor / shares | Finance/investor portal |
| Community identity rule | Community intake |
| Official program list | Extra Program instances |
| Kegiatan as events | Calendar |
| Provibio as catalog/service | Commerce/koperasi SKU |
| Koperasi application fields | Koperasi operations |

---

## 24. Risks / Modeling Ambiguities

1. **Payment account coupling** — do not let a shared bank account merge Membership and Koperasi.  
2. **CMS vs product slugs** — especially `/keanggotaan` copy vs module family.  
3. **Publication vs Knowledge Article** — sharing an editor must not merge lists.  
4. **Thin Program vs CMS Page** — Award must not collapse back into “just another Page” if editions/results become real; V1 content is acceptable.  
5. **Media dual use** — one library, two access classes; 1D must not skip this.  
6. **Applicant vs Member** — UI may say “anggota” too early.  
7. **Category B** — highest identity confusion after association vs koperasi.  
8. **Event names** — do not build an event bus because this list exists.  
9. **Edition abstraction** — do not generalize Award years into a platform requirement.  
10. **Home composition** — Hero/Welcome vs Publishing teasers vs Program highlight; still a composition problem, not a new domain.  
11. **Working assumptions** — if stakeholders reject staff-only or in-app intake, Core V1 in §19 is invalid; do not silently keep it.  
12. **No DISCOVERY CONTRADICTION** with 1A/1B; the slug/copy tension is an unresolved implementation concern for 1D, already flagged in 1B §14.

---

## 25. Required Inputs for Phase 1D

Technical architecture questions. Do not answer them here except where the kernel already has a convention (controller + inline validation; Spatie `permission:` on admin routes; CMS reserved paths; Tiptap HTML not JSON; public registration off).

Phase 1D should decide:

1. **Module / folder boundaries** aligned to contexts (without inventing extra layers).  
2. **Application service vs fat controller** — current kernel has no Services; changing that needs an explicit convention.  
3. **Form Request usage** — none exist today.  
4. **Policy vs route `permission:` middleware** — current convention is route middleware; Policies do not exist.  
5. **How application/member/publication status is represented** (without treating this doc’s labels as column names).  
6. **Transaction boundaries** on Approve (application terminal + Member created).  
7. **Media vs domain-document authorization** (non-public downloads).  
8. **Account/domain identity linkage strategy** when NEXT starts — not required to implement in V1.  
9. **Slug ownership implementation** (reserved first segments for product families; CMS catch-all).  
10. **Publishing type strategy** (single Publication with a type value vs separate modules — domain prefers one Publication + type).  
11. **Knowledge vs Publishing code reuse** without merging identities.  
12. **Audit logging** for membership review and later community.  
13. **Public application intake security** (spam, upload size/type, PII).  
14. **Tiptap sanitization/rendering contract** for staff HTML vs never for submissions.  
15. **Landing composition** (Hero/Welcome + publication teasers).  
16. **Whether Core V1 Program is a first-class record or landing content with a reserved URL family.**  
17. **Test strategy** for invariants (especially no koperasi side effect on approve).  
18. **Migration/content import** is not 1D’s first job; do not couple architecture to WP IDs.

Phase 1D must **not** design cooperative finance, community, or catalogs as if they were V1.

---

End of Phase 1C domain model & feature map.
