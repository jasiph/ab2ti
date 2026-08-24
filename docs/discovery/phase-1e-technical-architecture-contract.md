# AB2TI 2.0 — Phase 1E Technical Architecture Contract

**Status:** Technical architecture only. Not schema. Not implementation. Not ACL migration.  
**Date:** 2026-08-20  
**Inputs:** Phase 1A–1D, `ARCHITECTURE_BASELINE.md`, ADRs 0001–0003, Constitution, Cursor rules, `config/acl.php`, `config/cms.php`, `routes/web.php`, Media/CMS/Tiptap/authorization code and tests.  
**Addenda:**  
- Secure Capability Access: `docs/discovery/phase-1e-a-secure-capability-access.md`.  
- **AB2TI Awards** (operated recurring program; not merely a CMS Page): `docs/discovery/phase-1e-b-ab2ti-award-architecture-addendum.md`. That addendum extends this contract for Awards; it does not replace Membership/Publishing/Knowledge decisions.

This contract maps approved **product/domain** architecture onto **Laravel + Inertia** before tables or features are built.

Previous discovery documents were not rewritten. Contradictions and corrections are explicit below.

---

## 1. Executive Summary

The kernel remains a **Laravel-native** app: `route → controller → validate → Eloquent (+ Spatie Media) → Inertia`. AB2TI 2.0 must **not** become microservices, repositories, CQRS, or a folder-per-noun DDD tree.

**Target:** keep Identity, ACL, **Secure Capability Access**, CMS, Tiptap, and generic Media as **platform**. Add **Membership**, **Publishing**, **Knowledge**, and **AB2TI Awards** as **modular slices** (controllers, operations, policies, Vue pages, tests) without collapsing them into `Page`. Secure Capability Access is **not** a second Spatie ACL; see Phase 1E-A. Awards is **not** a CMS Page per year; see Phase 1E-B.

**Justify extra layers only where complexity exists.** Fortify already uses Actions. **Approve Membership Application** (two identities, transaction, idempotency, no User/role side effects) **does** justify an application Action. Simple CMS Page CRUD may stay controller-shaped.

**Security split:** File Manager / public `/storage` URLs are for **editorial Media only**. Membership identity documents require **private storage + authorized retrieval**. They must never appear in `files.json`.

**ACL:** keep today’s permission strings until a dedicated migration. Map them to Phase 1D **concepts**; split/rename later, do not seed now.

**Applicant correction (Phase 1D):** a Membership Applicant is a **business actor / party**, not an application lifecycle state. The application **has** states (`submitted`, `under_review`, …). Core V1: Applicant needs no User.

---

## 2. Inputs and Architecture Invariants

### 2.1 Authoritative inputs

- Phase 1A–1C (domain, IA, feature map)
- Phase 1D (actors, roles, workflows, enforcement layers)
- Constitution §§6–9, 13 (backend pattern, auth, routing, frontend)
- ADR 0002 (no public User registration)
- ADR 0003 (`config/cms.php` reserved first segments)
- Cursor rules 10 / 20 / 40

### 2.2 Invariants (carry forward)

```
User / Account
  != Membership Applicant
  != AB2TI Member
  != Authorization Role
  != Secure Capability Grant
```

Secure Capability Grant is **bearer, purpose-scoped, temporary authority**. It does not establish identity, membership, a User, or Spatie roles/permissions. Details: Phase 1E-A.

Approval:

```
Membership Application  --approve-->  AB2TI Member
```

- Creates/establishes a Member **once** (idempotent).
- Does **not** create a User, assign a `member` role, or create a Koperasi member.
- Atomic (application approval + Member creation).
- Administrative fields never come from public request data.

Frontend permissions = UX. Backend = security.

Roles (target concepts): `super-admin`, `platform-admin`, `editor`, `membership-admin`. Future: `koperasi.*`, `community.*`.

### 2.3 Explicit correction to Phase 1D wording

| Phase 1D phrasing | Correction |
|---|---|
| Applicant as “business **state** (role *on* the application)” | **Membership Applicant** = **party/actor** associated with a Membership Application. |
| Application states listed as if they *were* the Applicant | States belong to the **Membership Application** aggregate. |

This does **not** change Core V1: Applicant still needs no User. 1D is not silently rewritten.

### 2.4 Tension with Constitution (documented, not coded)

Constitution §6: do not introduce Form Requests, Actions, Policies **speculatively**.

Phase 1D: those layers are needed for object rules and Approve.

**Contract:** do **not** add them for every CRUD resource. **Do** add them when a workflow has multi-model transactions, public vs staff field sets, or object-level authorization. Membership approval and public intake qualify. Page index CRUD may remain inline validation until it does not.

---

## 3. Current Technical Architecture

Inspected HEAD of this working tree (kernel post–Phase 0C). Classification is **current**, not target.

### 3.1 Laravel layout

| Path | Classification |
|---|---|
| `app/Http/Controllers/Admin/*` | **PLATFORM/KERNEL** |
| `app/Http/Controllers/PageController.php` | **PLATFORM/KERNEL** (CMS public) |
| `app/Http/Middleware/HandleInertiaRequests.php`, `SetLocale.php` | **PLATFORM/KERNEL** |
| `app/Models/{User,Page,BlockTemplate,Hero,WelcomeSection,File}` | **PLATFORM/KERNEL** |
| `app/Actions/Fortify/*`, `Actions/Jetstream/*` | **PLATFORM/KERNEL** |
| `app/Support/CmsPath.php` | **PLATFORM/KERNEL** |
| `app/Console/Commands/{SyncPermissions,MergeLanguageFiles}` | **PLATFORM/KERNEL** |
| `app/Http/Requests` | **Absent** |
| `app/Policies` | **Absent** |
| `app/Services` | **Absent** |
| `routes/web.php` | **PLATFORM/KERNEL** (+ **LEGACY PRESBI** aliases `/sermons`, `/events`) |
| `config/acl.php` | **PLATFORM/KERNEL** (`access settings` unused) |
| `config/cms.php` | **PLATFORM/KERNEL** |

### 3.2 Permission / middleware

- Spatie aliases in `bootstrap/app.php`: `permission`, `role`, `role_or_permission`.
- `/admin/*` module routes: `auth:sanctum` + `permission:…` (Phase 0C-A).
- Dashboard: **auth only**.
- `Gate::before` → `super-admin` all abilities.
- Tests: `tests/Feature/Admin/AdminAuthorizationTest.php`, `InteractsWithAcl`.

### 3.3 Inertia / Vue

| Area | Classification |
|---|---|
| `Pages/Admin/{Users,Roles,Pages,BlockTemplates,Hero,WelcomeSection,FileManager}` | **PLATFORM/KERNEL** |
| `Pages/Frontend/Landing/*`, `Frontend/Page/Show.vue` | **PLATFORM/KERNEL** (landing has **LEGACY PRESBI** residue: `ServiceEvents` name) |
| `Pages/Auth/*`, `Profile/*`, `API/*` | **PLATFORM/KERNEL** |
| `Components/Base/*` | **GENERIC / REUSABLE** (in use except `BaseSearchSelect`) |
| `Components/RichEditor.vue`, Tiptap extensions | **PLATFORM/KERNEL** (CMS editor) |
| `Components/FileManager/*`, `MediaPickerModal.vue` | **PLATFORM/KERNEL** |
| Jetstream `PrimaryButton`, `Modal`, … | **GENERIC / REUSABLE** (duplicate family) |
| `GuestLayout`, `WebLayout`, `DashboardStatCard` | **UNCERTAIN** / unused |
| `Components/Welcome.vue` Laracasts cards | **LEGACY PRESBI** / Jetstream leftover |

**No AB2TI BUSINESS** PHP or Vue modules exist yet (Membership, Publishing, Knowledge).

### 3.4 CMS / Tiptap / Media (facts)

- Page HTML in DB; Tiptap `getHTML()`; public `v-html`.
- File Manager: `File` + Spatie Media; `getUrl()`; soft delete; restore; force-delete; `getJson` / `storeJson` for picker.
- `FILESYSTEM_DISK=local` (`storage/app/private`) is Laravel default disk. Spatie Media Library config is **not published**; File Manager URLs behave as **public web-accessible** assets (`/storage/…` pattern). Treat **editorial files as public-disk-class** until proven otherwise.
- Tests: `FileManagerTest`, `CmsRoutingTest`, `CmsPathTest`, `HeroMediaTest`.

### 3.5 Pattern today

**route → Admin controller → `$request->validate()` → Eloquent + Media → Inertia.**  
That stays valid for simple platform CRUD. It is **insufficient** for Approve Membership as a fat controller.

---

## 4. Target Module Boundaries

Laravel-native **modular monolith**. No new top-level “Domains” package unless a later ADR says so.

### 4.1 Ownership of layers (conceptual)

| Context | Models | Operations | Authz | Validation | HTTP | Vue |
|---|---|---|---|---|---|---|
| **Identity** | `User` | Fortify/Jetstream Actions; User/Role admin | `users.*`, `roles.manage`; User policy for super-admin protection | User FormRequest or inline | `Admin/UserController`, `Admin/RoleController` | `Pages/Admin/Users`, `Roles`, Profile |
| **ACL** | Spatie tables | `acl:sync` | — | `config/acl.php` | — | `usePermission` UX only |
| **Secure Capability Access** | Persisted grants (later schema) + optional Laravel signed URLs | Issue / redeem / revoke / consume (later) | **Not** Spatie permissions; purpose + target binding | Purpose-specific (owned by consuming domain) | Reserved public capability routes (later; recommend `access`) | Loginless purpose UI — **not** Member Portal |
| **CMS** | `Page`, `BlockTemplate`, `Hero`, `WelcomeSection` | Keep controller CRUD; optional PublishPage Action if publish splits | `cms.*` → later `cms.pages.*` / landing | Page validation (reserved slugs) | Existing Page/Hero/Welcome/Block controllers | Existing Admin Pages + Frontend Page/Show |
| **Media (generic)** | `File` + Spatie | File Manager | `media.*` **assets only** | MIME/size | `FileManagerController` | File Manager + MediaPicker |
| **Membership** | Application, Member, documents (later schema) | **Actions** for review/approve | `membership.*` + policies | Public vs staff FormRequests | `Admin/Membership/*` + public submit | `Pages/Admin/Membership/*`, public apply page |
| **Publishing** | Publication | Create/update in controller or Action; **Publish/Unpublish Actions** if permission split | `publishing.*` | Staff FormRequest | `Admin/Publishing/*` + public list/show | Admin + `Pages/Frontend/Publishing` |
| **Knowledge** | Knowledge Article | Parallel to Publishing; **separate** classes | `knowledge.*` | Staff FormRequest | `Admin/Knowledge/*` + public | Admin + `Pages/Frontend/Knowledge` |
| **AB2TI Awards** | Program, Edition, Entry, Recognition (later schema) | Edition lifecycle; public submit; staff verify; **record** official results (not a scoring engine) | `awards.*` (Phase 1E-B; seed later) | Public vs staff FormRequests | `Admin/Awards/*` + public award/edition | Admin + public Award pages — **not** `pages` |
| **Koperasi** | Later | Later | `koperasi.*` reserved | — | Future `Admin/Koperasi` | Future |
| **Community** | Later | Later | `community.*` reserved | — | Future | Future |

### 4.2 Suggested directory gravity (not created now)

When implementation starts, **prefer**:

```text
app/Http/Controllers/Admin/Membership/
app/Http/Controllers/Admin/Publishing/
app/Http/Controllers/Admin/Knowledge/
app/Actions/Membership/ApproveMembershipApplication.php
app/Policies/MembershipApplicationPolicy.php   # when the resource exists
resources/js/Pages/Admin/Membership/
resources/js/Pages/Frontend/Publishing/
```

Keep `app/Models` flat until model count hurts. Do **not** invent `app/Services/MembershipService` as a dumping ground.

### 4.3 Coupling rules

- Membership HTTP **must not** call File Manager `getJson`.
- Publishing/Knowledge **may** use MediaPicker (generic Media).
- CMS **must not** own Publication or Member.
- Membership (and later Community/Koperasi/Awards entry revision) **may consume** Secure Capability Access; the platform **must not** import those domains’ state machines.
- Awards HTTP **must not** reuse Membership Application records or File Manager for future private participant files.
- Shared: `RichEditor`, Base UI, `AppLayout` / `FrontendLayout`.

---

## 5. Shared vs Feature Component Contract

### 5.1 What exists

**Shared (keep central):** `Components/Base/*` in use, `RichEditor`, `MediaPickerModal` / File picker, `ConfirmDialog`, `useConfirm`, `usePermission`, layouts, Jetstream form bits on Profile/Auth.

**Feature (today):** landing `HeroSection`, `WelcomeSection`, `FooterSection`, `ServiceEvents`; Admin *Index/Form* pages.

### 5.2 Rules

| Belongs in shared UI | Belongs in a feature |
|---|---|
| Button, input, select, modal, list, pagination, card | Membership application review panel |
| Tiptap **primitive** (`RichEditor`) | Publication type selector + publish controls |
| Media **picker** for **editorial** assets | Membership document upload (sensitive) |
| Toast, confirm, language switcher | Member status badge with domain labels |
| Layout chrome | Knowledge article public card if it encodes knowledge IA |

- Feature components **wrap** Base/RichEditor; they do not fork BaseButton.
- Do **not** put approve/reject rules inside `BaseButton` or `RichEditor`.
- Changing a shared component requires checking **all** callers (CMS, later Publishing).
- Do not add a third button family. Do not promote unused `BaseSearchSelect` / `DashboardStatCard`.
- Sensitive document UI is **feature** UI, never `MediaPickerModal` pointed at the whole library.

---

## 6. Application Operation Boundaries

### 6.1 Convention

| Kind | Where |
|---|---|
| Simple CRUD (one model, no invariant) | Controller + inline validate or FormRequest |
| Auth account flows | Existing Fortify/Jetstream Actions |
| Multi-model + transaction + idempotency | **`app/Actions/{Context}/…`** (Laravel-native, like Fortify) |
| Generic `app/Services` bag | **Forbidden** |

Name Actions as **commands**, not “Manager.” Exact class names may vary; the **Approve** contract is mandatory.

### 6.2 Membership operations (future)

| Operation | Layer |
|---|---|
| Submit application (public) | Public controller + FormRequest (no admin fields) → persist submitted |
| Start review / request revision / reject | Action or controller+policy; state machine in one place |
| **Approve** | **Dedicated Action** (see §16) |

### 6.3 Publishing / knowledge operations (future)

Create/update may stay in controllers. **Publish** / **Unpublish** should be explicit operations (Action or dedicated controller method) so `update` ≠ `publish` when ACL is split.

### 6.4 Approve Membership Application — contract (no code)

The operation **must** guarantee:

1. Caller is authorized (`membership.applications.approve` or super-admin bypass).
2. Application exists and is in an **approvable** state (`under_review`, per 1D).
3. Transition to `approved` is the only status write on that path.
4. Member is created **or** the already-created Member for this application is reused (**exactly once**).
5. Application approval and Member write are in **one DB transaction**.
6. Repeat approve does **not** create a second Member.
7. Public/request bags cannot supply status, reviewer, member id, roles, or card-issued.
8. No User created; no Spatie `member` role; no Koperasi member.

Controllers **invoke** this Action; they do not reimplement it.

---

## 7. Authorization Enforcement Contract

| Concern | Enforcement layer |
|---|---|
| Can enter admin membership routes | **Route middleware** `auth` + `permission:membership.applications.view` (or members.view) |
| Can approve application | **Middleware** (permission) **and** **Policy/Action** (`approve`); never Vue |
| Application is eligible for approval | **Application operation** (state machine), not a permission name |
| Can edit CMS page | **Middleware** `cms.update` today; later `cms.pages.update` + optional Policy |
| Can publish CMS page | **Target:** distinct permission + dedicated toggle/publish method. **Today:** same `cms.update` — **SPLIT LATER** |
| Can view membership identity document | **Policy** + membership document permission; **not** `media.view`; **not** Inertia dump of all files |
| Can delete referenced media | **Application rule** (block/warn — **DECISION REQUIRED**) + `media.delete` |
| Can submit public application | **Public** + throttle/validation; **not** a Spatie permission |
| Menu shows Membership | **Vue UX only** |
| Cannot assign `super-admin` | **Policy / UserController rule** (exists partially today) |

`Gate::before` for `super-admin` stays unless a security ADR changes it.

---

## 8. ACL Migration Map

**Do not change `config/acl.php` or seeders in this phase.**

| CURRENT NAME | TARGET CONCEPT | LATER ACTION |
|---|---|---|
| `users.view\|create\|update\|delete` | Identity staff accounts | **KEEP** |
| `roles.manage` | ACL administration | **KEEP** |
| `cms.view` | `cms.pages.view` (+ blocks/landing view) | **SPLIT LATER** |
| `cms.create` | `cms.pages.create` (+ blocks) | **SPLIT LATER** |
| `cms.update` | `cms.pages.update` **and** `cms.pages.publish` | **SPLIT LATER** |
| `cms.delete` | `cms.pages.delete` (+ blocks) | **SPLIT LATER** |
| `heroes.*` | `cms.landing.*` (Hero; Welcome uses `cms.*` today) | **RENAME/SPLIT LATER** |
| `media.*` | Generic editorial Media | **KEEP** meaning; **constrain** so it never lists membership documents |
| `access settings` | Unused | **RETIRE** later (or bind to a real settings module) |
| Seeded role `user` | Not Member | **RETIRE** as product role (**DECISION REQUIRED** timing) |
| Stale DB `culture.*` / `artikel.*` | Presbi leftovers | **LEGACY PRESBI** — `acl:sync --clean` only when an operator chooses |
| *(none)* | `publishing.publications.*` | **ADD LATER** when Publishing is built |
| *(none)* | `knowledge.articles.*` | **ADD LATER** |
| *(none)* | `membership.applications.*` / `members.*` / `documents.*` | **ADD LATER** |
| *(none)* | `koperasi.*` / `community.*` | **DEFERRED** — reserve names, do not seed empty groups without a feature |
| Target roles `platform-admin`, `editor`, `membership-admin` | Phase 1D | **ADD LATER** with features; do not invent now |

Safe rule: **add** new groups when a vertical slice ships; **rename/split** `cms.*` in a dedicated ACL ADR with dual-read if needed. Never `acl:sync --clean` as a side effect of feature work.

---

## 9. Membership Document Security

### 9.1 Current File Manager (risk baseline)

- Shared `File` listing for anyone with `media.view`.
- Picker JSON (`admin.files.json`) returns URLs.
- Soft delete / restore / force-delete exist.
- Retrieval is **URL-based**, not membership-authorized download.
- Suitable for **covers and editorial images**, not KTP/payment proof.

### 9.2 Contract for membership documents

| Rule | Contract |
|---|---|
| Disk | **Private / non-public** (not web-served `/storage` object URLs) |
| Browse | **Not** in generic File Manager or Tiptap picker |
| Retrieve | **Authorized application endpoint** (staff `membership.documents.view`) |
| Public | **No** unauthenticated predictable URL |
| Who | `membership-admin` (and super-admin); **not** `editor` via `media.view` |
| Applicant upload | Allowed on **submit** (and revision channel if product says so); does **not** grant library browse |
| Metadata vs bytes | Application owns **meaning** (what the file is); storage owns **bytes** |
| Delete/retention | **DECISION REQUIRED** (policy); architecture must allow delete without putting files on the public disk |
| Listing | Membership-admin application/member UI only |

Applicant upload ≠ applicant can later enumerate all documents.

Do not implement disks or move files in this phase.

---

## 10. Generic Media Contract

**Serves:** CMS Page covers/inline images, Publication/Knowledge covers and inline editorial media, Hero backgrounds, site assets.

**Allows:** upload, browse (staff `media.view`), attach, replace, soft-delete/restore/force-delete as today.

**Should gain (later):** reference awareness before destroy (**DECISION REQUIRED** block vs warn).

**Must NEVER store or list:**

- identity documents / verification files
- membership payment proofs as PII
- future koperasi-sensitive records
- private member files

Public URLs for **published editorial** images are acceptable. That is the opposite of membership documents.

---

## 11. Route Architecture and Reserved Segments

### 11.1 Current organization

- Public: `/`, CMS aliases, catch-all `/{slug}` **last**, language switch.
- Auth: Fortify/Jetstream `/login`, `/user/*`, `/dashboard`.
- Admin: `/admin/*` + `permission:`.
- API: `GET /api/user`.

### 11.2 Target security classes (no URLs implemented)

| Class | Examples |
|---|---|
| **Public** | Home, published CMS, publication list/detail, knowledge list/detail, membership **info**, **POST application** |
| **Authenticated staff** | Dashboard, `/admin` content, `/admin` membership, `/admin` users/roles |
| **Capability-based (loginless)** | Reserved prefix (recommend `access`); **not** public CMS; **not** Member Portal. See Phase 1E-A |
| **Member self-service** | Reserved concept; **no Core V1 routes**. Distinct from capability-based revision |
| **Koperasi / Community** | Reserved bounded prefixes later |

### 11.3 Reserved first segments

**Already reserved (ADR 0003 / `config/cms.php`):**  
`admin`, `api`, `build`, `dashboard`, `email`, `forgot-password`, `hot`, `language`, `login`, `logout`, `register`, `reset-password`, `sanctum`, `storage`, `two-factor-challenge`, `up`, `user`.

**Must be reserved later before CMS pages occupy them** (Phase 1B/1C families):

| Segment | Why |
|---|---|
| `publikasi`, `berita`, `kegiatan`, `siaran-pers`, `liputan-media` | Publishing |
| `teknologi-tani` | Knowledge |
| `keanggotaan` | Membership public/intake |
| `koperasi` | Koperasi public + future ops |
| `program` | Other initiatives / future program index (1B) |
| `award` | AB2TI Awards public family (Phase 1E-B). **Do not** occupy as a CMS Page. Exact vs `/program/...` is **DECISION REQUIRED** |
| `galeri` | Gallery |
| `kontak` | Contact (if not a CMS slug policy) |
| `dokumen` | Downloads if used |
| `komunitas` | Future community |
| `anggota`, `membership` | Aliases / English; avoid CMS steal |
| `access` (or `secure` / `grant` if chosen) | Secure Capability Access redeem/session routes (Phase 1E-A). **Do not** edit `config/cms.php` until routes exist |

`/tentang` may remain CMS-owned.

**Collision risks:** creating a CMS Page `berita` or `keanggotaan` before routes exist (same class as `/sermons`). Catch-all stays **last**. Explicit module routes **above** it. `/sermons` and `/events` remain until an IA ADR (Constitution).

Admin membership vs admin CMS: same `/admin` prefix is fine; **permission middleware must differ**.

---

## 12. Inertia / Frontend Data Contract

### 12.1 Shared (today + keep)

`HandleInertiaRequests`: `locale`, flash, Ziggy, `auth.user`, dotted `auth.user.roles` / `auth.user.permissions`.

Those permission arrays are **UX**. Missing a key in Vue must not be the only guard.

### 12.2 Principles

- **Minimal props** per page: list endpoints do not send document binaries or KTP URLs.
- **Context resources:** application review payload ≠ public apply payload ≠ member list row.
- **Never serialize** identity-document URLs onto editor pages, File Manager, or public Inertia.
- **Status fields** (application status, member status, published) are **backend-controlled**; the client may display them, not assign `approved`.
- Validation: Inertia `errors` from server; client checks UX only.
- Public pages: no admin Inertia props.

Dotted `auth.user.permissions` keys are awkward but existing; changing them is optional cleanup, not a V1 feature.

---

## 13. Domain Dependency Rules

```
PLATFORM
  Identity  ACL  Secure Capability Access  CMS  Tiptap  Media(generic)  Layouts/Base

BUSINESS
  Publishing  --> Media(generic), Tiptap, Identity(staff)
  Knowledge   --> Media(generic), Tiptap, Identity(staff)
  Membership  --> Identity(staff reviewer);
                  private document capability;
                  Secure Capability Access (revision/upload purposes);
                  optional future User link
  Awards      --> Identity(staff awards-admin);
                  Tiptap (Award-owned content);
                  Media(generic, editorial);
                  may consume Secure Capability Access (entry revision);
                  NOT Membership Application; NOT CMS Page
  CMS         --> Media(generic), Tiptap, Identity(staff)

FUTURE
  Koperasi    --> Identity(staff); NOT Membership records;
                  may consume Secure Capability Access
  Community   --> Identity; Publishing/Knowledge (handoff);
                  NOT CMS Page as store;
                  may consume Secure Capability Access
```

| Allowed | Forbidden |
|---|---|
| Publishing → Media | Media → Membership |
| Knowledge → Media | CMS → Publishing (CMS does not own news) |
| CMS → Media | Publishing → Membership |
| Membership → private docs | Membership → Koperasi |
| Membership → Secure Capability Access | Capability → Spatie permission assignment |
| Publishing → Award Edition (reference) | Publishing owns Award identity / official results |
| Awards → Media (editorial) | CMS Page stores Award Edition / Entry / Recognition |
| Staff Identity → all admin | Member status → Spatie role |

**Platform dependency:** using Tiptap/Media/User.  
**Domain dependency:** Membership referencing a reviewing User id (staff).  
**Forbidden:** circular Membership ↔ Koperasi; File Manager querying applications.

---

## 14. Data Ownership Contract

No tables. Source of truth:

| Context | Owns |
|---|---|
| **Identity** | User, credentials, staff roles/permissions assignment |
| **Secure Capability Access** | Grant mechanics: secret/digest, purpose name, target binding, expiry/revocation/consumption, capability-session contract. **Not** identity. Details: Phase 1E-A |
| **Membership** | Membership Application, applicant-submitted data (party attributes), review/application status, AB2TI Member, member status, identity documents **meaning + access**; **owns interpretation** of membership purposes |
| **Publishing** | Publication, type, editorial status, publish time, publication slug/body |
| **Knowledge** | Knowledge Article, its status/slug/body |
| **AB2TI Awards** | Award Program, Award Edition, Award Entry, official Recognition; Award-owned editorial HTML. Details: Phase 1E-B |
| **CMS** | Page, informational HTML, page slug (CMS families), Hero, Welcome, BlockTemplate |
| **Media** | Generic editorial asset bytes + technical metadata |

**References (not ownership):** Publication may reference an Award Edition (or future Program) and a Media asset. Member may later reference a User (optional link). Application documents reference storage objects **owned in access terms by Membership**. Award Entry may later **reference** a Member if eligibility requires it; it does not become membership.

Do not store Members in `pages.content`.

---

## 15. Future Member–User Link Contract

Core V1: **no** Member login.

Ready-without-building:

- Member **without** User: required.
- User **without** Member: required (all staff today).
- Disabling User ≠ ending membership.
- Future inactive membership ≠ must disable User.
- Link is **explicit** (staff attach / invite / claim) — **not** inferred from matching email.
- Approval **never** auto-creates User.
- Membership is **not** a Spatie role `member`.

**Cardinality:** working assumption **optional one-to-one** (one Member ↔ at most one User; one User ↔ at most one Member). Family/shared logins = **DECISION REQUIRED**. Unlink must not delete Member.

No self-service design in this phase.

---

## 16. Membership Approval Transaction Contract

```
HTTP (staff)
  → auth + permission:membership.applications.approve
  → Policy: can approve this application
  → Command/FormRequest: application id only (no status from client)
  → ApproveMembershipApplication Action
       → DB transaction
            lock/load application
            if already approved: return existing Member (idempotent)
            else if not under_review: fail
            else: create Member once; set application approved;
                  record reviewer/time/notes (audit)
       → commit
  → Inertia redirect / resource without PII URLs unless authorized
```

| Topic | Contract |
|---|---|
| Transaction | Application row + Member insert/update **together** |
| Idempotency | Second approve of same application → same Member, no second insert |
| Concurrency | Row lock / unique “one member per approved application” **concept** (schema later) |
| Authorization | Middleware + Policy/Action; not Vue |
| Validation | Id + staff notes optional; **no** public bag |
| Audit | Who approved, when, application id, member id |
| Side effects | **None** of: User, member role, Koperasi |

Reject/revision are separate operations; they must not call Approve.

---

## 17. Technical Test Contract

Do **not** write these now. They **must** exist when the slice is implemented.

### Membership approval — FEATURE + SECURITY

- Unauthorized User cannot approve.
- `editor` cannot approve.
- `membership-admin` can approve (happy path).
- Invalid state (e.g. `submitted` or `rejected`) cannot approve.
- Approval creates **one** Member.
- Repeat approval does **not** create a second Member.
- User is **not** created.
- No `member` (or kernel `user`) role assigned as membership.
- Failure mid-way leaves **neither** approved-without-member nor member-without-approved (atomicity) — FEATURE / UNIT around the Action.

### Public application — SECURITY + FEATURE

- Public cannot set administrative status/approval fields.
- Protected documents are not retrievable without `membership.documents.view`.
- Documents absent from File Manager JSON.

### Publishing — FEATURE + SECURITY

- If permissions split: `update` without `publish` cannot publish.
- Unpublished content is not public.
- Unauthorized User cannot publish.

### Knowledge — FEATURE

- Knowledge article not listed as Publikasi by default.
- Unpublished knowledge not public.

### Media — SECURITY + ARCHITECTURE/BOUNDARY

- Membership document not in generic File Manager listing/picker.

### Routes — FEATURE / SECURITY

- Admin membership routes 403 without permission (extend `AdminAuthorizationTest` style).
- Catch-all cannot claim reserved product segments **once reserved**.

### UNIT

- Application state machine: allowed transitions only.
- CmsPath/reserved segments when new segments are added.

Classify: FEATURE, UNIT, ARCHITECTURE/BOUNDARY, SECURITY as tagged above.

---

## 18. Legacy / Presbi Coupling

| Item | Class |
|---|---|
| `/sermons`, `/events` routes, tests, nav labels | **SAFE TO REMOVE LATER** (needs IA ADR — **BLOCKING for URL cutover**, not for Membership schema) |
| `ServiceEvents.vue` naming | **SAFE TO REMOVE LATER** |
| Seeded role `user` + `media.view` | **BLOCKING BEFORE** membership **documents** in the same library; **SAFE** to leave until then if documents are isolated |
| `culture.*` / `artikel.*` stale DB rows | **SAFE TO REMOVE LATER** (operator `--clean`) |
| Church tables on old DBs | **SAFE TO REMOVE LATER** (`LOCAL_DATABASE_RECOVERY.md`); not in migrations |
| Jetstream Welcome cards, unused layouts | **SAFE TO REMOVE LATER** |
| Dual button/modal families | **GENERIC / KEEP** until UI consolidation |
| CMS + Tiptap + Media + Spatie ACL | **GENERIC / KEEP** |
| Dashboard auth-only | **UNCERTAIN** / MEDIUM risk; not Presbi domain |
| `access settings` | **SAFE TO REMOVE LATER** |
| Dotted Inertia auth keys | **GENERIC / KEEP** or later cleanup |

No congregation/jemaat/song **modules** remain in `app/`.

**Not blocking** Membership Action design: sermons aliases.  
**Blocking** putting PII in File Manager: role `user` + shared listing.

---

## 19. Technical Risks

1. Using File Manager for KTP — **CRITICAL**.  
2. Fat `Approve` in a controller — duplicates, non-atomic, request pollution.  
3. Splitting `cms.*` too early with `--clean` — breaks current admin.  
4. CMS Page occupying `/keanggotaan` before reservation.  
5. Vue permissions treated as security.  
6. Inferring Member↔User from email.  
7. `platform-admin` assigning `super-admin`.  
8. Publish via generic `update`.  
9. Circular Membership–Koperasi “convenience.”  
10. Introducing repositories/events “for DDD.”  
11. 1D “Applicant = state” leaking into schema as a status enum named applicant.  
12. Public `v-html` for untrusted HTML.

---

## 20. Decision Register

### CONFIRMED TECHNICAL DECISIONS

- Laravel-native modular monolith; no microservices/CQRS/event sourcing.
- Keep kernel CMS/Identity/Media; add business slices beside them.
- Actions for Approve (and similarly heavy commands); not a generic Services folder.
- Policies + middleware for membership/object rules; Vue is UX.
- Membership documents ≠ generic Media; private retrieval.
- Keep current ACL strings until a planned add/split; add `membership.*` / `publishing.*` / `knowledge.*` with those features.
- Catch-all last; extend `config/cms.php` before product URL families go live.
- Applicant is a party; application has states.
- Approve is atomic and idempotent; no User/role/koperasi side effects.
- Optional future Member–User link is explicit, not email-inferred.
- Constitution “no speculative layers” applies to Page CRUD, not to Approve.

### TECHNICAL ASSUMPTIONS

- Staff-only Users in Core V1.
- In-app association intake in Core V1.
- Spatie default media URLs are public-class for File Manager.
- Optional 1:1 Member–User later.
- V1 `editor` includes publish until junior-editor split.
- `/admin` prefix for all staff business UIs.

### DECISIONS REQUIRED BEFORE SCHEMA / IMPLEMENTATION

- Application intake: in-app vs remain external.  
- Applicant revision: staff-mediated vs tokenized form.  
- Manual/imported Member without Application.  
- Member deactivation / reverse approval.  
- `platform-admin` assigning `super-admin`.  
- Document retention/deletion.  
- Media delete if referenced (block vs warn).  
- Program as record vs CMS landing — **resolved for AB2TI Award:** first-class Awards context (Phase 1E-B). Other initiatives may remain CMS until named.  
- Exact public first-segment list to add to `config/cms.php`.  
- Cardinality of Member–User if not 1:1.  
- Whether CMS publish is split in the first membership/publishing slice or later.

### DEFERRED / FUTURE

- Member self-service and Account Link UI.  
- Koperasi workflows and `koperasi-admin`.  
- Community moderation.  
- ACL rename of `cms.*` / `heroes.*`.  
- Retire role `user`.  
- `/sermons` `/events` redirects.  
- Editor vs publisher split, scheduling, catalogs, finance.

---

## 21. Recommended Implementation Sequence

Do not start these in this phase.

```
1. This architecture contract          (done as documentation)
        ↓
2. Schema / data contract              (next — still no app features)
        ↓
3. Reserve product URL segments        (config/cms.php + tests; IA ADR as needed)
        ↓
4. ACL: ADD membership/publishing/knowledge permissions
   when those slices ship — do not mass-rename cms.* first
        ↓
5. Private document storage capability (before any PII upload)
        ↓
6. Membership vertical slice
   (public submit → staff review → Approve Action + tests)
        ↓
7. Publishing + Knowledge slices
   (reuse RichEditor + generic Media)
        ↓
8. CMS content/IA (Tentang, koperasi info pages)
        ↓
9. Compatibility (legacy redirects, ACL split, retire role user)
```

| Kind | Items |
|---|---|
| Prerequisite architecture | This document, then schema contract |
| Prerequisite security | Document storage isolation, approve invariants, no PII in File Manager |
| Schema work | After 1F; new migrations only |
| Feature implementation | Membership then publishing/knowledge |
| Migration/compatibility | Reserved paths, ACL split, sermons/events ADR |

---

## 22. Recommended Next Phase

**Phase 1F — Schema / Data Contract** (still no feature code): conceptual records and uniqueness (one Member per approved application), what is persisted vs derived, document metadata vs bytes, Publication vs Knowledge Article vs Page fields, **persisted capability-grant concepts** from Phase 1E-A, and **AB2TI Award Program / Edition / Entry / Recognition** from Phase 1E-B, **without** pretending to be migrations.

If a Phase 1F document already exists from before this addendum, **extend it** for Awards rather than treating its “no Program table” line as still authoritative for Award.

Do **not** next: implement Approve, seed `membership.*`, change disks, or rename permissions.

---

End of Phase 1E technical architecture contract.
