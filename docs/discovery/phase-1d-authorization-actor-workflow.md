# AB2TI 2.0 — Phase 1D Authorization, Actor & Workflow Model

**Status:** Architecture / discovery only. Not implementation. Not an ACL seeder. Not an ADR until recorded as such.  
**Date:** 2026-08-20  
**Primary inputs:** Phase 1A, Phase 1B, `docs/discovery/phase-1c-domain-model-feature-map.md`  
**Kernel facts:** `docs/project/ARCHITECTURE_BASELINE.md`, ADR 0002, `config/acl.php`, `routes/web.php`

This document defines **who may do what** and **how lifecycles change state** before any membership, publishing, or permission implementation.

Phase 1A–1C were not modified. No silent contradiction. Kernel gaps are reported, not fixed.

---

## 1. Executive Summary

Core V1 authorization is **staff capability-based**, not “every User is a Member.”

- **Visitor** submits a Membership Application **without** an account.
- **AB2TI Member** is created by **approval**, not by assigning a Spatie role.
- Staff authenticate as **Users** and receive a **small set of roles** composed from **permission namespaces** aligned to Phase 1C contexts.
- **Frontend menu hiding is UX.** **Route middleware, policies, and domain rules are security.**

Proposed Core V1 staff roles: **`super-admin`**, **`platform-admin`**, **`editor`**, **`membership-admin`**. Reject a catch-all `admin` that can both publish and approve members. Do not use the seeded kernel role name `user` as a product role.

Publishing, Knowledge, and CMS keep **separate** draft/published workflows even if they share Tiptap. Membership application status is **not** member status and **not** account status.

Koperasi and Community get **reserved permission namespaces** only. They are not Core V1 roles or workflows.

---

## 2. Architectural Principles

1. User/Account is authentication identity.
2. AB2TI Member is a business-domain identity.
3. A User is never automatically an AB2TI Member.
4. Membership Application and AB2TI Member are separate; approval creates a Member and does not assign “member” as a login role.
5. Platform roles must not replace application or member state.
6. Authorization is enforced **server-side**.
7. Hiding Vue menus is **not** authorization (`usePermission` / `useNavigation` are UX).
8. Permissions name **domain actions**, not menu labels.
9. Avoid role explosion; prefer permissions + few roles.
10. Users **may hold multiple roles**.
11. No church/Presbi semantics (`user` as jemaat, sermons as content).
12. Future `koperasi.*` and `community.*` namespaces stay independent of `membership.*`.
13. Tiptap, Media, CMS Page remain platform capabilities.
14. Feature-specific rules (approve membership, publish knowledge) stay in their context.
15. Identity documents are **not** covered by `media.view`.
16. Public self-registration of Users stays off (ADR 0002). Join is application intake.

---

## 3. Actor Model

Actors are product/runtime participants. **An actor name is not a Spatie role.**

| Actor | Represents | Kind | Relation to User | Core V1 | Auth required |
|---|---|---|---|---|---|
| **Anonymous / Public Visitor** | Anyone not signed in | Neither role nor member | None | Yes | No |
| **Authenticated User** | Someone with an Account | **Identity** | Is the User | Yes (staff) | Yes |
| **Membership Applicant** | Person filling/revising an application | **Business state** (role *on the application*) | Must **not** require User in V1 | Yes | **No** |
| **AB2TI Member** | Association identity after approval | **Business-domain identity** | Optional later link; none in V1 | Yes | **No** |
| **Content Editor** | Staff who author trusted public content | **Authorization role** (see `editor`) | Must be a User | Yes | Yes |
| **Membership Administrator** | Staff who review applications and members | **Authorization role** (see `membership-admin`) | Must be a User | Yes | Yes |
| **Platform Administrator** | Staff who manage Users and roles | **Authorization role** (see `platform-admin`) | Must be a User | Yes | Yes |
| **Super Administrator** | Break-glass platform operator | **Authorization role** (existing `super-admin`) | Must be a User | Yes (kernel) | Yes |

### 3.1 Evaluated, not distinct Core V1 actors/roles

| Name | Verdict | Why |
|---|---|---|
| **Publication Editor** | **Not a separate role in V1** | Same trusted editorial work as Knowledge; split later via `publishing.*` vs `knowledge.*` permissions, not a new role. |
| **Agricultural Knowledge Editor** | **Not a separate role in V1** | Same as above. |
| **Community Contributor** | **Future actor** | Community is not Core V1. Business state, not a staff role. |
| **Community Moderator** | **Future authorization role** | May later be a permission set; often the same human as `editor`. |
| **Koperasi Member** | **Future domain identity** | Not a User role. Parallel to AB2TI Member, different context. |
| **Koperasi Administrator** | **Future authorization role** | Must not share `membership.*`. |

Secretariat as a **job** may combine `editor` + `membership-admin` on one User. That is multiple roles, not a “secretariat” role.

---

## 4. User vs Applicant vs Member vs Role

These four must not collapse.

```
Visitor  --submits-->  Membership Application   (Applicant is a facet of this record)
                              |
                         staff approve
                              |
                              v
                       AB2TI Member            (domain identity, no User required)

User / Account  --optional later link-->  AB2TI Member
User / Account  --assigned-->  Spatie Role(s)  (staff capabilities only in V1)
```

| Concept | Answers |
|---|---|
| **User** | Can this person sign in? |
| **Applicant** | Is this person currently in an intake process? |
| **AB2TI Member** | Does the association recognize this person as a member? |
| **Role** | Which **staff capabilities** does this Account have? |

Forbidden:

- Treating login as membership.
- Assigning role `member` or kernel `user` to mean anggota.
- Using member status to grant `cms.update`.
- Creating a User as a side effect of approve.

Kernel collision: seeder role **`user`** (currently `media.view` only) is **not** an AB2TI Member and **must not** be reused as product language for anggota. See §19.

---

## 5. Role Strategy

### 5.1 Evaluation of the suggested set

| Suggested | Fit |
|---|---|
| `super-admin` | **Keep.** Already exists. `Gate::before` grants every ability. Break-glass, not daily editorial. |
| `admin` | **Reject as a catch-all.** Would mix publishing, membership approval, and user management. Excessive power (risk §18). |
| `editor` | **Accept** as the Content Editor role. |
| `membership-admin` | **Accept** as the Membership Administrator role. |

Add **`platform-admin`** so User/role administration is not only `super-admin`, and is **not** bundled with membership approval.

### 5.2 Core V1 roles (minimal)

| Role | Purpose | Permission composition (intent) | Must not |
|---|---|---|---|
| **`super-admin`** | Platform break-glass; bypass | All abilities via Gate | Be the only modeled way to work; be confused with Member |
| **`platform-admin`** | Staff accounts and role assignment | `users.*`, `roles.manage` | Approve members; publish content unless also `editor` |
| **`editor`** | Trusted public content | `cms.*` (pages, landing, blocks), `publishing.*`, `knowledge.*`, `media.*` (assets only), thin `programs.*` if used | See/approve membership applications; see identity documents |
| **`membership-admin`** | Association intake and member records | `membership.applications.*`, `membership.members.*`, `membership.documents.*` | Publish CMS/Publikasi/Knowledge; list public media library as a substitute for documents |

**Multiple roles:** allowed and expected (e.g. secretariat = `editor` + `membership-admin`).

**No Core V1 role** for Applicant, Member, Koperasi, or Community.

### 5.3 Future attachment without destabilizing V1

- Add **`koperasi-admin`** later with only `koperasi.*`.
- Add **`community-moderator`** later with only `community.*` (or grant `community.moderate` to `editor` without renaming `editor`).
- Member self-service later uses **Account Link + ownership policies**, not a Spatie role named `member`.
- Do not widen `membership-admin` to koperasi “for convenience.”

### 5.4 Kernel roles today (fact, not product)

| Kernel role | Fact |
|---|---|
| `super-admin` | Exists; Gate bypass |
| `user` | Exists; `media.view` only; **not** product membership |

Product should stop treating `user` as a meaningful staff role. Retire/replace in a later ACL ADR — **not this phase**.

---

## 6. Permission Namespace

Proposed **target** names for AB2TI 2.0. They are **not** implemented here. Current kernel names (`cms.view`, `heroes.view`, `access settings`) remain as **today’s code**.

Pattern: `{context}.{resource}.{action}`

Actions used only where the domain needs them: `view`, `create`, `update`, `delete`, `review`, `approve`, `reject`, `publish`, `unpublish`.

Not Core V1 unless justified: `archive`, `restore` (except existing media restore), `schedule`.

### 6.1 Platform identity

| Permission | Meaning |
|---|---|
| `users.view` | List/view staff accounts |
| `users.create` | Provision a User |
| `users.update` | Change name/email/roles (object rules still apply) |
| `users.delete` | Delete a User (not self, not last super-admin) |
| `roles.manage` | Maintain roles/permission assignment |

Keep these names close to the kernel. `access settings` stays unused; Jetstream profile is self-service, not this permission.

### 6.2 CMS (informational + site chrome)

| Permission | Meaning |
|---|---|
| `cms.pages.view` | List/edit-view informational pages |
| `cms.pages.create` | Create page |
| `cms.pages.update` | Edit content/slug/meta |
| `cms.pages.publish` | Toggle public visibility (`is_active` / published) |
| `cms.pages.delete` | Delete page |
| `cms.landing.update` | Hero / Welcome (maps today’s `heroes.*` + welcome-section) |
| `cms.blocks.view/create/update/delete` | Block templates (authoring tool) |

If implementation temporarily keeps coarse `cms.view|create|update|delete`, **publish must still be a distinct check** before a junior-editor split. V1 `editor` may hold both update and publish.

### 6.3 Publishing

| Permission | Meaning |
|---|---|
| `publishing.publications.view` | Staff list/detail including drafts |
| `publishing.publications.create` | Create draft |
| `publishing.publications.update` | Edit unpublished or published item |
| `publishing.publications.publish` | Publish / unpublish |
| `publishing.publications.delete` | Delete (rare; prefer unpublish) |

Types (Berita, Kegiatan, …) are **filters**, not permission names. No `publishing.news-menu-admin`.

### 6.4 Agricultural knowledge

| Permission | Meaning |
|---|---|
| `knowledge.articles.view` | Staff list/detail including drafts |
| `knowledge.articles.create` | Create draft |
| `knowledge.articles.update` | Edit |
| `knowledge.articles.publish` | Publish / unpublish |
| `knowledge.articles.delete` | Delete |

Separate namespace so Knowledge never inherits Publikasi listing rights accidentally.

### 6.5 Membership

| Permission | Meaning |
|---|---|
| `membership.applications.view` | Staff queue and application detail |
| `membership.applications.review` | Start review, write notes, request revision, edit **while under review** per workflow |
| `membership.applications.approve` | Approve → create Member |
| `membership.applications.reject` | Reject (terminal) |
| `membership.members.view` | Staff member list/detail |
| `membership.members.update` | Staff update of member profile / card issued flag |
| `membership.members.create` | **DECISION REQUIRED** — manual/import member without application |
| `membership.members.deactivate` | **DECISION REQUIRED** — not in Phase 1C Core V1 status set |
| `membership.documents.view` | View identity/payment artifacts |
| `membership.documents.manage` | Attach/replace application documents in staff flow |

There is **no** `membership.applications.create` for staff as a substitute for public submit (staff may capture an application **DECISION REQUIRED**). Public submit is **not** a Spatie permission; it is a public capability with abuse controls (1E/implementation).

### 6.6 Media (platform assets)

| Permission | Meaning |
|---|---|
| `media.view` | Browse **content** library (not membership documents) |
| `media.create` | Upload assets |
| `media.update` | Rename/restore as today |
| `media.delete` | Soft-delete / force-delete per existing File Manager rules |

**Invariant:** `media.*` **does not** authorize `membership.documents.*`.

### 6.7 Thin programs (Core V1 content)

If Award is a first-class Program record: `programs.programs.view|update|publish` for `editor`. If Award is CMS-like landing under a reserved URL, `cms.pages.*` may suffice. **DECISION REQUIRED** at implementation architecture (Phase 1C §25 item 16). Either way, not a `program-admin` role in V1.

### 6.8 Reserved future namespaces

```
koperasi.applications.*
koperasi.members.*
koperasi.documents.*
koperasi.services.*     # later

community.submissions.view
community.submissions.moderate
community.submissions.reject
```

Do not assign these to `membership-admin` or `editor` in V1. Do not design full action lists now.

### 6.9 Public and owner actions (not Spatie permissions)

| Action | Mechanism |
|---|---|
| Read published Page / Publication / Knowledge Article | Public |
| Submit Membership Application | Public (rate-limit, upload rules) |
| Applicant resume-on-revision | **DECISION REQUIRED** (tokenized link vs staff-mediated) — not a role |
| Member self-profile | **NEXT** — ownership policy after Account Link |

---

## 7. Authorization Matrix

Legend: **Yes** · **No** · **Own/self** · **DR** = DECISION REQUIRED · **n/a** = not in Core V1

Columns are **roles / situations**, not every actor name.

| Action | Public | Auth User (no staff role) | Applicant (no account) | Member (no account) | editor | membership-admin | platform-admin | super-admin |
|---|---|---|---|---|---|---|---|---|
| View published CMS Page / Publication / Knowledge Article | Yes | Yes | Yes | Yes | Yes | Yes | Yes | Yes |
| View draft Publication / Knowledge / unpublished Page | No | No | No | No | Yes | No | No | Yes |
| Create/update CMS Page | No | No | No | No | Yes | No | No | Yes |
| Publish/unpublish CMS Page | No | No | No | No | Yes (publish perm) | No | No | Yes |
| Create/update Publication | No | No | No | No | Yes | No | No | Yes |
| Publish Publication | No | No | No | No | Yes (publish perm) | No | No | Yes |
| Create/update Knowledge Article | No | No | No | No | Yes | No | No | Yes |
| Publish Knowledge Article | No | No | No | No | Yes (publish perm) | No | No | Yes |
| Browse/upload **content** media | No | No | No | No | Yes | No | No | Yes |
| Delete content media | No | No | No | No | Yes (delete perm) | No | No | Yes |
| Submit Membership Application | Yes | n/a as join | Yes | n/a | No (not via content) | May capture **DR** | No | Yes |
| View **own** application after submit | **DR** | n/a | **DR** (V1 default: No, staff-operated) | n/a | No | Yes (all) | No | Yes |
| Update own application after submit | No unless `revision_requested` + **DR** channel | n/a | Only in revision **DR** | n/a | No | Per workflow §8 | No | Yes |
| View any application | No | No | No | No | No | Yes | No | Yes |
| Review / request revision / reject | No | No | No | No | No | Yes | No | Yes |
| Approve application (creates Member) | No | No | No | No | No | Yes | No | Yes |
| View identity documents | No | No | **DR** own only if self-service | No | No | Yes | No | Yes |
| View members | No | No | No | No | No | Yes | No | Yes |
| Update member profile (staff) | No | No | No | n/a V1 | No | Yes | No | Yes |
| Member self-update profile | n/a | n/a | n/a | **NEXT / DR** | No | — | No | — |
| Deactivate member | n/a | n/a | n/a | n/a | No | **DR** | No | **DR** |
| Create/delete Users | No | No | No | No | No | No | Yes | Yes |
| Manage roles | No | No | No | No | No | No | Yes | Yes |
| Access `/dashboard` | No | Yes (kernel today) | No | No | Yes | Yes | Yes | Yes |
| Assign `super-admin` | No | No | No | No | No | No | **No** (object rule) | Yes |
| Koperasi operations | — | — | — | — | — | — | — | reserved |
| Community moderate | — | — | — | — | — | — | — | reserved |

**Authenticated user with no staff role** must not edit content. Kernel seeded `user` with `media.view` is **already a gap** (§18).

### 7.1 Owner / self semantics

**Core V1 working assumption (Phase 1C):** staff-operated membership. Applicant and Member **have no Account**. Therefore **there is no Owner/self for membership in V1** except:

- Public **create** of an application (not “view own queue”).
- **DECISION REQUIRED:** whether revision uses a **secret resume link** (then owner = token holder, not User) or staff transcribes changes.

**NEXT (after Account Link):**

| Question | Status |
|---|---|
| Can an applicant view their own application? | **DECISION REQUIRED** (token vs portal vs no) |
| Can they update after submit? | Only `revision_requested`; channel **DR** |
| Can a member update their own profile? | **DECISION REQUIRED** which fields |
| Which fields need admin re-verification? | **DECISION REQUIRED** (likely identity documents, name vs legal ID) |
| Who approves membership? | `membership.applications.approve` only |
| Who deactivates a member? | **DECISION REQUIRED** (not in 1C Core status set) |
| Who publishes content? | `*.publish` on editor (V1 composed into `editor`) |

Applicants must **never** set status to approved, assign roles, or mark card issued.

---

## 8. Membership Application Lifecycle

Aligned with Phase 1C §8.1. **Do not** add `draft` or `withdrawn` as required Core V1 states.

### 8.1 States

| State | Meaning | Who enters | Next | Applicant edit | Staff edit | Audit |
|---|---|---|---|---|---|---|
| **submitted** | Intake received; waiting | Visitor (public submit) | `under_review` | No | Notes only; not status to approved in one click without review **DR** | Record created; treat as start of audit trail |
| **under_review** | Staff examining | `membership-admin` (review) | `revision_requested`, `rejected`, `approved` | No | Yes (notes, documents if capturing) | Reviewer + timestamp |
| **revision_requested** | Applicant must correct | `membership-admin` | `under_review` (after resubmit) | **Yes, limited** via **DR** channel | Yes | Reason stored; prior payload retained |
| **rejected** | Terminal; no Member | `membership-admin` | none (new Application if reapply **DR**) | No | No (immutable except staff internal note **DR**) | Decision + reason |
| **approved** | Terminal; Member created | `membership-admin` | none | No | No status change; Member is now the living record | Decision; link to new Member |

Not in Core V1: `draft`, `withdrawn`, `expired`. Adding them is **DECISION REQUIRED**.

### 8.2 Allowed transitions

```
submitted → under_review
under_review → revision_requested | rejected | approved
revision_requested → under_review   (resubmit)
approved → (none)
rejected → (none)
```

### 8.3 Approval is not a User role change

On **approved**:

1. Application becomes terminal `approved` (immutable status).
2. Conceptual event `MembershipApplicationApproved`.
3. **AB2TI Member** is created (`MemberActivated`); profile copied from application data.
4. **No User is created.**
5. **No Spatie role is assigned to anyone as “member.”**
6. **No Cooperative Member is created.**
7. Card remains **not issued** until staff records issuance (`MembershipCardIssued`).

Reject emits `MembershipApplicationRejected` and does **not** create a Member.

### 8.4 Mass-assignment / field rules (conceptual)

Public submit may set applicant data and documents only — **never** status, reviewer, approval flags, member number, card issued.

Staff review may set notes and status transitions via **commands** (Request revision, Approve, Reject), not by posting `status=approved` as a generic update **without** the approve permission.

---

## 9. AB2TI Member Lifecycle

Separate from User authentication and from application status.

### 9.1 Core V1 status

Phase 1C: Member exists as **`active`** after approval. That is the only justified Core V1 membership status.

| Evaluated | Core V1? | Notes |
|---|---|---|
| **active** | **Yes** | Created on approve |
| inactive | **Future / DR** | Useful later; do not invent meaning now |
| suspended | **Future / DR** | Policy unknown |
| expired | **Future / DR** | No term on public site |
| resigned | **Future / DR** | Policy unknown |
| revoked | **Future / DR** | Do not use as silent “delete” |

**DECISION REQUIRED** whether mistaken approval can be reversed in V1. Until then, do not implement `deactivate` as a hidden membership death.

### 9.2 Account status vs membership status

| | Account (User) | Membership (AB2TI Member) |
|---|---|---|
| Meaning | May this person sign in? | Does the association recognize them? |
| Core V1 | Staff accounts only | Independent records |
| Disable User | Staff cannot log in | Member **unchanged** |
| Member inactive (future) | Linked User **need not** be disabled | Domain decision later |
| No User | Normal | Member still valid |

Membership **may exist without a login account.** Required.

### 9.3 Relationship to User

Core V1: **none required.**

Later Account Link: optional, explicit, must not delete Member on unlink. Linked User’s **roles** remain staff roles if any; membership does not grant `editor`.

---

## 10. Publication Workflow

Independent of CMS Page and of Knowledge Article.

### 10.1 States (Core V1)

| State | Public visibility |
|---|---|
| **draft** | No |
| **published** | Yes (list + detail) |

Unpublish returns to **draft** (or equivalent unpublished). Enough per Phase 1C. **No review queue in V1** (editor vs publisher is FUTURE). **Scheduling is FUTURE**, not Core V1.

`archived` is **FUTURE** (unpublish covers V1).

### 10.2 Ownership and permissions

- **Author/editor:** the User who creates/edits (attribution); **authorization** is `publishing.publications.*`, not “only own drafts” unless later **DR**.
- **Publish / unpublish:** `publishing.publications.publish`. V1 `editor` includes it.
- **Revisions/history:** **DECISION REQUIRED** / not required for V1 beyond timestamps and staff audit of publish.
- Types are data, not workflows.

Community HTML never enters this workflow until staff create a Publication (Phase 1C option B).

---

## 11. Knowledge Article Workflow

Same **shape** as Publication (**draft / published / unpublish**), **separate identity and permissions** (`knowledge.articles.*`).

- Must not appear in Publikasi lists by default.
- Same Tiptap/media platform; same “trusted staff only” rule.
- No review queue or scheduling in Core V1.
- Author/editor = staff User with knowledge permissions; V1 `editor` holds them.
- Archive/history: same as Publication (FUTURE / DR).

Do not merge with Publication because both have HTML.

---

## 12. CMS Page Workflow

Informational/platform content. Kernel today: `is_active` (public if true).

### 12.1 Conceptual mapping

| Kernel | Workflow language |
|---|---|
| `is_active = false` | Unpublished (not publicly reachable) |
| `is_active = true` | Published |

No review queue. No scheduling. Nested slugs remain CMS rules (ADR 0003 + Phase 1C reserved families).

### 12.2 Permissions

`cms.pages.update` edits; `cms.pages.publish` flips visibility. If V1 keeps a single `cms.update` as today, that **combines edit and publish** — a **MEDIUM** gap versus the target namespace (§18).

Hero/Welcome: landing chrome, `cms.landing.update` / existing `heroes.*`, not membership.

Block templates: authoring, not a publish workflow.

Pages must not store Members, applications, publications, or submissions.

---

## 13. Media Authorization

Media is **platform infrastructure** for **content assets**. Membership files are **domain documents**.

| Action | Who (Core V1) |
|---|---|
| Upload content assets | `media.create` (`editor`) |
| Browse content library | `media.view` (`editor`) |
| Attach existing asset to Page/Publication/Knowledge | `media.view` + update on the owning content |
| Delete / restore / force-delete | `media.update` / `media.delete` as kernel File Manager |
| Upload/view KTP, payment proof | `membership.documents.*` only |
| Public anonymous File Manager JSON | **No** |

### 13.1 Deletion and references

Deletion **must consider references** (page cover, inline HTML URLs, publication cover). Kernel today can force-delete; inlined CMS HTML can break (Phase 1A/1C). Target: warn or block delete of referenced **content** assets. **DECISION REQUIRED** how strict (block vs warn).

Do not put identity documents in the same listing as `getJson()` used by Tiptap.

### 13.2 Feature-specific ownership

Do **not** split File Manager into per-feature databases. Use **access overlay**: content library vs membership document store/visibility. Bytes may use the same technical media library **only if** queries and URLs are partitioned by class.

### 13.3 Inherited security concerns (generic media manager)

- Public disk URLs if guessed (`/storage/...`).
- `media.view` listing **all** files (will be **CRITICAL** once PII exists).
- AJAX `storeJson` / `getJson` as a broad picker.
- Uploads up to 20MB; MIME differs between `store` (any file) and `storeJson` (images).
- Seeded role `user` has `media.view`.
- No distinction of sensitive vs public in the File model today.

---

## 14. Server-Side Enforcement Model

**Frontend visibility is UX. Backend authorization is security.**

### 14.1 Intended responsibilities (future implementation)

| Layer | Responsibility |
|---|---|
| **Route middleware** | `auth` for staff areas; Spatie `permission:` for capability (current kernel pattern — keep). |
| **Policies** | Object rules: cannot edit super-admin User; cannot approve via generic update; document access; “own” application token later. **None exist today.** |
| **Gates / permissions** | Named capabilities in §6; `Gate::before` for `super-admin` remains a kernel fact. |
| **FormRequest** | Validate + `authorize()` for mutations; never accept `status`, `role=super-admin`, `is_member` from the client. **None exist today.** |
| **Application / domain layer** | Approve command: transaction (application terminal + Member created + events conceptually). Keep invariant: no koperasi side effect. Kernel today has no Services. |
| **Frontend props** | `auth.user.permissions` for menus/buttons only. |

Public intake: throttle, file type/size, no staff fields. Not “permission:create”.

### 14.2 Kernel: where this is not yet guaranteed

Discoverable **now** (do not fix in this phase):

| Area | Fact |
|---|---|
| Admin modules | Spatie `permission:` on `/admin/*` resource routes (Phase 0C-A) — **good** |
| Dashboard | `/dashboard` and `/admin/dashboard` are **auth-only**, no module permission |
| Policies / FormRequests | **Absent** |
| Super-admin | `Gate::before` full bypass |
| Vue | `usePermission` / `useNavigation` hide UI only |
| Users | `users.update` can `syncRoles` including `super-admin` if the actor has the permission (controller blocks non-super-admin **editing** a super-admin user, not necessarily **assigning** the role on create — create is permission-gated) |
| CMS publish | Same `cms.update` as edit (toggle route) |
| Media | Staff with `media.view` see all `File` rows |
| Public registration | Disabled (ADR 0002) — **good** |
| Tests | `AdminAuthorizationTest` covers guest redirect and 403 without permission on listed admin reads |

---

## 15. Route Security Boundaries

Do not change routes in this phase. Conceptual classes:

| Class | Who | Examples of capability |
|---|---|---|
| **PUBLIC** | Visitor | Home, published pages, published publications/knowledge, membership **information**, **submit** application |
| **AUTHENTICATED** | Any User | Login-gated: dashboard, Jetstream profile, 2FA. **Not** “can edit CMS.” |
| **MEMBER** | Linked Member User | **Empty in Core V1.** Future self-service only. |
| **ADMIN** | Staff + permission | `/admin` content, membership, users — **further split by permission** |
| **SPECIALIZED BUSINESS CONTEXT** | Future | Koperasi admin, community moderation — own prefix/permission family |

### 15.1 How organization should reflect contexts later

- Public content routes vs public **application submit** (POST) — still public, different abuse profile.
- Admin **content** (cms / publishing / knowledge / media / landing) vs admin **membership** (applications / members / documents). Same `/admin` host is fine; **middleware must differ**.
- Do not put membership JSON under `files.json`.
- Future: `koperasi` and `community` admin groups; public koperasi **pages** remain public CMS-like.

URL strings are IA (Phase 1B/1C); this section is **security class**, not sitemap.

---

## 16. Future Koperasi Boundary

Enough to keep the architecture clean:

- Namespace: `koperasi.*` only.
- Future role: `koperasi-admin` (or permissions on an existing User), **not** `membership-admin`.
- Uses platform Identity (staff Users) and platform ACL pattern.
- Cooperative Member is **not** `membership.members`.
- `CooperativeApplicationApproved` must not grant `membership.*` or create AB2TI Member.
- Public koperasi **copy** is CMS/`editor` in Core V1 — that is **content**, not koperasi operations.
- Do not design cooperative application workflow here.

---

## 17. Future Community Boundary

- Namespace: `community.*`.
- Contributor is not `editor`. Moderator may be `editor` + `community.submissions.moderate` later, or a dedicated role.
- Submissions never write CMS Page HTML.
- Handoff creates a Publication or Knowledge Article under **publishing/knowledge publish** rules.
- Uses Identity if contributors log in later; Core V1 has no community routes.
- Must not reuse `membership.applications.*` for community intake.

---

## 18. Authorization & Security Risks

Analysis only. Severity if AB2TI domains are built **on the kernel without the overlays in this document**.

| ID | Risk | Severity | Notes |
|---|---|---|---|
| R1 | Frontend-only authorization | **HIGH** (mitigated on current admin modules) | Vue still not sufficient; new routes must get `permission:` + policies |
| R2 | Catch-all `admin` role | **HIGH** | Rejected in §5; do not add |
| R3 | `super-admin` Gate bypass | **MEDIUM** | Intentional; limit who holds the role; platform-admin cannot assign it |
| R4 | No Policies / FormRequests | **HIGH** for new domains | Object-level rules (documents, approve command) need them |
| R5 | Dashboard auth-only | **MEDIUM** | Any logged-in User including seeded `user` |
| R6 | Seeded `user` + `media.view` | **HIGH** → **CRITICAL** when PII files exist | Name collision with “member”; library access |
| R7 | File Manager lists all files / public URLs | **CRITICAL** for membership documents | Must not share picker with KTP |
| R8 | Applicant posting approval fields | **CRITICAL** if intake is a generic update | Commands only; never client-set status=approved |
| R9 | Member modifying protected fields (future portal) | **HIGH** (NEXT) | Ownership ≠ approve/card/status |
| R10 | Publish without explicit permission | **MEDIUM** | Today `cms.update` includes toggle |
| R11 | Delete referenced media | **MEDIUM** | Broken public pages |
| R12 | Account disabled confused with membership ended | **HIGH** (conceptual) | Separate statuses (§9) |
| R13 | `users.create` assigning `super-admin` | **HIGH** | Must restrict role assignment set |
| R14 | Permission name drift (`cms.view` vs `cms.pages.view`) | **MEDIUM** | Migrate via ACL ADR, don’t run `--clean` casually |
| R15 | Editor with membership queue access “for convenience” | **HIGH** | Violates context split |
| R16 | Direct `/storage` guess of uploads | **HIGH** | Sensitive files need authorized download |
| R17 | Mass assignment on User/Page | **MEDIUM** | Keep fillable tight; FormRequest later |
| R18 | Community HTML in `v-html` | **CRITICAL** if built wrong | Forbidden by 1C invariant |
| R19 | Koperasi using `membership.*` | **HIGH** (future) | Namespace discipline |
| R20 | Middleware gap on new controllers | **CRITICAL** | Repeat 0C-A pattern + tests |

---

## 19. Decision Register

### 19.1 CONFIRMED DECISIONS (from 1A–1C + this phase)

- User ≠ Applicant ≠ Member ≠ Role.
- Core V1 membership is staff-operated; Applicant/Member need no Account.
- Approve creates Member; does not create User or Cooperative Member; does not assign a member login role.
- Public User registration remains off.
- Server-side permission middleware is the admin pattern to extend.
- Frontend permission props are UX only.
- `editor` must not see membership documents via `media.view`.
- Publication, Knowledge Article, and CMS Page have separate identities and permission families.
- Content V1 workflow is draft/published (pages: is_active), no scheduling, no editorial review queue.
- Application V1 states: submitted → under_review → revision_requested | rejected | approved.
- Member V1 status: active only.
- Koperasi/Community: reserved namespaces, no V1 roles/workflows.
- Reject catch-all `admin` role.
- Multiple staff roles per User are allowed.

### 19.2 ASSUMPTIONS (proceed unless overturned)

- First-release authenticators are **staff only**.
- In-app association intake is in Core V1 (not Google-Form-only).
- Legacy application packet is the V1 minimum (contact, photo, identity document, payment proof).
- V1 `editor` includes publish permissions (no junior-editor split yet).
- Applicant **cannot** view the staff queue; revision channel is not a full portal.
- Award/Program uses `editor` capabilities, not a program-admin role.
- Kernel `super-admin` bypass remains.

### 19.3 DECISIONS REQUIRED BEFORE IMPLEMENTATION

Organizational / product (do not invent):

1. Confirm staff-only login for V1.  
2. Confirm in-app application vs remaining external form.  
3. How applicants complete **revision_requested** (staff-mediated vs tokenized public form).  
4. Whether staff may **create** a Member without an Application (import).  
5. Whether V1 needs **deactivate** / reverse approval.  
6. Whether `platform-admin` may assign only a **safe role set** (not `super-admin`).  
7. Strictness of media delete when referenced (block vs warn).  
8. Whether CMS publish is split from update in the first ACL change.  
9. Program as first-class record vs CMS landing (repeats 1C).  
10. Application field list if legacy packet is insufficient.

### 19.4 FUTURE / DEFERRED

- Member/Applicant Accounts and Owner/self matrix.  
- Which member fields are self-editable.  
- Member statuses beyond active.  
- Editor vs publisher split; scheduling; archive; revision history product.  
- `koperasi-admin`, `community-moderator`.  
- Retire kernel role `user`.  
- Gated public/member PDFs.  
- Community submission auth.  
- Email verification.

---

## 20. Recommended Next Architecture Step

**Phase 1E — Technical architecture contract** (still no feature build): map contexts to module/folder conventions, how new permissions will be added to `config/acl.php` without breaking current `cms.*` / `heroes.*`, Policy vs middleware split, document-storage vs File Manager, reserved URL segments, and test invariants for Approve.

Do **not** in the next step: implement membership, write seeders for `membership.*`, migrate roles, or enable registration.

---

### Discovery notes (not contradictions)

- Phase 1C forbade naming permission strings; this phase **proposes** them as the authorization contract. That is sequential, not a conflict.
- Current kernel `cms.update` including page toggle is coarser than `cms.pages.publish`; target state is documented, not implemented.
- Seeded role `user` remains a **contamination risk** for product language; 1C already flagged it.

End of Phase 1D.
