# AB2TI 2.0 — Phase 1G ACL / Authorization Migration Plan

**Status:** Authorization contract only. Not ACL implementation. Not seeders. Not routes. **PASS / CLOSED** after capability-namespace reconciliation.  
**Date:** 2026-08-20  
**Closed inputs:** Phase 1A–1F (1F **PASS / CLOSED**). **1E-A** wins on capability vs Spatie vs grants **and** on canonical identifier format (`<domain>.<action>`). **1E-B** wins on Awards.

This plan defines the staff capability surface and a safe migration order. It does **not** modify `config/acl.php`, roles, middleware, Vue, or tests.

**Closure correction:** the `{context}.{resource}.{action}` naming convention formerly in this document is **rejected**. It conflicted with CLOSED Phase 1E-A. Canonical identifiers use exactly one semantic separator: `<domain>.<action>`. New domains MAY be compound kebab-case (`membership-applications.approve`). Existing platform two-part names (`users.view`, `roles.manage`, `cms.update`, `heroes.update`, `media.view`) remain valid.

---

## 1. Executive Summary

Today’s ACL is **Spatie Permission**, sourced from `config/acl.php`, synced by `php artisan acl:sync`, enforced mainly by **route middleware** `permission:…` on `/admin/*` (except dashboard: **auth only**). `Gate::before` grants **every** ability to role `super-admin`. Vue `usePermission` / `useNavigation` duplicate that bypass for **UX only**. There are **no** Policies, FormRequests, or Gates besides `Gate::before`.

Canonical capability identifiers are **`<domain>.<action>`** (exactly one semantic separator). Kernel names stay two-part (`users.view`, `cms.update`, `media.view`). New Core V1 domains use the same shape with a **compound kebab-case domain** (`membership-applications.approve`, `publishing-publications.publish`, `awards-recognitions.manage`). **Rejected:** `{context}.{resource}.{action}` (`membership.applications.approve`). **No** wildcard or prefix authorization (`membership-*`, `awards-editions.*`, `users.*` as checks).

Three channels stay separate: **PUBLIC** (state + validation), **STAFF ACL** (this plan), **SECURE CAPABILITY GRANT** (not Spatie).

**Add** membership / publishing / knowledge / awards capabilities **when those slices ship**. **Keep** current platform names until a dedicated CMS/heroes split. **Do not** `acl:sync --clean` as a side effect of feature work.

**Readiness:** **PASS / CLOSED.** Canonical capability names follow 1E-A. Role composition and cleanup timing remain product/operations decisions, not authorization design blockers.

---

## 2. Authoritative Inputs

Inspected in-repo: `config/acl.php`, `config/permission.php`, `RolesAndPermissionsSeeder`, `acl:sync` (`SyncPermissions`), `User` (`HasRoles`), `AppServiceProvider` (`Gate::before`), `routes/web.php`, `bootstrap/app.php` middleware aliases, `HandleInertiaRequests`, `usePermission.js`, `useNavigation.js`, `UserController` super-admin object rule, `AdminAuthorizationTest` + `InteractsWithAcl`, ADRs/Constitution/baseline, Phase 1D–1F.

**Naming conflict:** Phase 1D’s `{context}.{resource}.{action}` examples and this document’s former three-part names are **superseded**. Phase **1E-A** (`<domain>.<action>`, one separator; compound kebab-case domain allowed) is authoritative. Phase 1D is not reopened.

No `app/Policies`, no `app/Http/Requests`. Sanctum token `can('read')` in API token tests is **not** staff ACL.

---

## 3. Locked Authorization Invariants

1. Capability-first; roles are bundles, not `if role ==`.  
2. Identifiers: canonical, English, **not localized**.  
3. Unknown / malformed capability → **fail closed**.  
4. Capabilities are **opaque identifiers**. No wildcards, no prefix auth, no `startsWith`, no `domain.*` / `membership-*` / `awards-editions.*` checks. Every effective capability is explicitly registered and checked.  
5. Navigation is UX; mutations are server-side.  
6. Public routes are not fake Spatie permissions.  
7. Grants ≠ Spatie; no `token → arbitrary permission`.  
8. User ≠ Member ≠ Applicant ≠ Award Participant ≠ role.  
9. Approve Member **must not** assign role `member`.  
10. Route / Policy ability / registry name aligned where practical.  
11. Legacy aliases, if any, are temporary.  
12. Editorial media capabilities (`media.view` / `create` / `update` / `delete`) never authorize membership documents.

---

## 4. Three Authorization Channels

| Channel | Subject | Governs |
|---|---|---|
| **A. PUBLIC** | Unauthenticated visitor | Published content; Award archive; Membership submit; Award Entry when `registration_open`. Routes + domain state + validation + rate limit. **No Spatie.** |
| **B. STAFF ACL** | Authenticated User | Canonical capabilities. Primary 1G scope. |
| **C. SECURE CAPABILITY** | Bearer grant (1E-A) | Purpose-scoped loginless ops (e.g. application revision). **Not** login, role, or Spatie. |

A staff User opening a grant URL must **not** union Channel B with Channel C (1E-A).

---

## 5. Current ACL Inventory

### 5.1 Source of truth today

`config/acl.php` → `acl:sync` / seeder `firstOrCreate` → Spatie `permissions` table → cache (`permission:cache-reset`).  
**Drift risk:** DB may contain rows **not** in config (`culture.*`, `artikel.*` after 0C-B) until `--clean`. Role UI can show stale names. Config is intended authority; DB can lag.

### 5.2 Permissions (config)

| Name | Classification |
|---|---|
| `users.view` / `create` / `update` / `delete` | **PLATFORM — KEEP** |
| `roles.manage` | **PLATFORM — KEEP** |
| `cms.view` / `create` / `update` / `delete` | **KEEP** (coarse); **RENAME / MIGRATE later** to compound-domain names such as `cms-pages.view` + landing/blocks |
| `heroes.view` / `create` / `update` / `delete` | **KEEP**; **RENAME / MIGRATE later** to compound-domain names such as `cms-landing.view` |
| `media.view` / `create` / `update` / `delete` | **PLATFORM — KEEP** (editorial only) |
| `access settings` | **UNUSED / VERIFY** → **LEGACY — RETIRE** (never referenced by routes/nav) |
| Stale DB `culture.*` / `artikel.*` | **LEGACY — RETIRE** (`acl:sync --clean` when operator chooses) |

Pattern today: `{module}.{crud}` except `roles.manage` and unused `access settings` (space, not dotted).

### 5.3 Roles (seeded)

| Role | Fact | Classification |
|---|---|---|
| `super-admin` | Exists; `Gate::before` all abilities | **PLATFORM / RETAIN** (break-glass) |
| `user` | `media.view` only; demo `user@user.com` | **LEGACY / REMOVE LATER** — **not** Member. Timing **DR** |

Not seeded: `platform-admin`, `editor`, `membership-admin`, `awards-admin`.

### 5.4 Enforcement today

- `/admin/*` (except dashboards): `auth:sanctum` + `permission:…`  
- `/dashboard`, `/admin/dashboard`: **auth only**  
- Public CMS/landing: no ACL  
- Controllers: almost no `authorize()`; UserController **role** checks for editing/deleting super-admin  
- Vue: exact string match + super-admin bypass  
- Inertia: `auth.user.permissions` (all permission names), `auth.user.roles`  
- Tests: `InteractsWithAcl` syncs **config**; guest redirect; 403 without permission; happy path with named permission  

### 5.5 CONFLICTING / VERIFY

- Frontend **and** Gate both bypass for `super-admin` — intentional if documented; UX must not be the only gate.  
- `cms.update` includes page **publish toggle** (`pages.toggle`) — trust mix; split later.  
- Welcome Section update uses `cms.update`; edit GET uses `cms.view`; not in sidebar (URL-only).  
- Seeder hardcodes `user`/`super-admin` assignments; permission list **is** from config (good). Role composition is **not** in config.

---

## 6. Authoritative Capability Registry

**Target:** `config/acl.php` remains the **single authoritative registry** of canonical capability identifiers (extend groups; optional later metadata keys).

| Consumer | Derives from |
|---|---|
| Spatie rows | `acl:sync` from config |
| Role composition | Config (future `acl.role_permissions` or equivalent) — **not** a second hardcoded seeder list |
| Route middleware / Policy | Same strings |
| Navigation | Explicit capability lists **copied from registry names**, not prefix scan; later may read a `modules` key in config |
| Vue `can()` | Shared Inertia list of names from Spatie (synced from config) |
| Tests | `InteractsWithAcl` already flattens config |

**Forbidden:** Vue-only permission lists that do not exist in config; Policy constants that diverge; seeder inventing names.

Metadata (group, module, risk) may live beside names in config later; identifiers stay the `name` strings.

---

## 7. Capability Naming Rules

**Canonical form (1E-A; authoritative):**

```
<domain>.<action>
```

There is exactly **one** semantic separator (a single `.`) between domain and action.

- **domain:** lowercase stable identifier. MAY be a **compound kebab-case** identifier (`membership-applications`, `awards-editions`). No localization. No wildcard or prefix semantics in the string.
- **action:** verb of **authority** (`view`, `create`, `update`, `delete`, `review`, `approve`, `reject`, `publish`, `manage`, `operate`).

Existing canonical two-part platform identifiers remain valid: `users.view`, `users.create`, `users.update`, `users.delete`, `roles.manage`, `cms.view`, `cms.create`, `cms.update`, `cms.delete`, `heroes.view`, `heroes.create`, `heroes.update`, `heroes.delete`, `media.view`, `media.create`, `media.update`, `media.delete`.

**Rejected (conflicts with 1E-A):** `{context}.{resource}.{action}` / three-part dotted names.

| Rejected | Canonical |
|---|---|
| `membership.applications.approve` | `membership-applications.approve` |
| `membership.members.view` | `membership-members.view` |
| `membership.documents.view` | `membership-documents.view` |
| `publishing.publications.publish` | `publishing-publications.publish` |
| `knowledge.articles.publish` | `knowledge-articles.publish` |
| `awards.programs.manage` | `awards-programs.manage` |
| `awards.editions.operate` | `awards-editions.operate` |
| `awards.entries.review` | `awards-entries.review` |
| `awards.recognitions.manage` | `awards-recognitions.manage` |

**Not used as capabilities:** `admin.*`, `superadmin.*`, `manage-all`, or any `{context}.{resource}.{action}` string.

`view` = staff list/detail (not public read).  
`publish` = publish **and** unpublish unless split later.  
`manage` = only where CRUD+ is one trust level (`roles.manage`, `awards-programs.manage`, `membership-documents.manage`, `awards-recognitions.manage`).  
`operate` = Award edition **lifecycle** (open/close/complete), distinct from copy `update`.

Identifiers are not translated. They are **opaque**: do not parse a capability string to infer authority (no `startsWith`, no prefix match, no domain/resource wildcard).

### 7.1 Documentation shorthand (not capabilities)

Expressions such as `users.*`, `cms.*`, `media.*`, `heroes.*`, `membership-applications.*`, `awards-editions.*` may appear in this document **only as prose shorthand** meaning “the currently registered concrete capabilities in this group.”

They are **not** capabilities. They must never be seeded, assigned, checked, or used in middleware.

Forbidden authorization semantics (conceptual equivalents):

- `membership-*`
- `awards-*`
- `awards-editions.*`
- `users.*` as a check
- `canAnyPrefix('awards.')` / `canAnyPrefix('awards-')`

Every effective capability must be explicitly registered and checked.

---

## 8. Capability Granularity

Not one permission per HTTP route. Not one `*.manage` per context.

**Split when:** different trust, destructive, privacy, approval, publication, org responsibility.

**Do not split:** Berita vs Kegiatan; publish vs unpublish (V1); Award entry eligible vs ineligible (both `review`).

**Do split:** membership `review` vs `approve`; `media.view` vs `membership-documents.view`; Award `awards-editions.update` vs `awards-editions.operate` vs `awards-recognitions.manage`; publishing `update` vs `publish`.

---

# Membership

## 9. Membership Capability Surface

| Capability | Why separate |
|---|---|
| `membership-applications.view` | Queue/detail (PII metadata). Not public. |
| `membership-applications.review` | Start review, notes, request revision. Not approve. |
| `membership-applications.approve` | **CRITICAL** — creates Member |
| `membership-applications.reject` | Terminal; distinct from approve |
| `membership-members.view` | Member master |
| `membership-members.update` | Profile / card issued. **No** deactivate in V1 (1F) |
| `membership-documents.view` | Private bytes. **Not** `media.view` |
| `membership-documents.manage` | Staff attach/replace |

**Not in Core V1 registry:** `membership-applications.create` (public submit); `membership-members.create` / `deactivate` (1D/1F DR). Add later if product requires.

Public submit and grant revision are **not** staff capabilities.

---

## 10. Membership Authorization Matrix

| OPERATION | CHANNEL | STAFF CAPABILITY | DOMAIN STATE | OWNER | NOTES |
|---|---|---|---|---|---|
| Public submit application | PUBLIC | — | n/a (create) | Validation + throttle | Not Spatie |
| Applicant revision | SECURE CAPABILITY | — | `revision_requested` | Grant purpose + Membership op | Not staff ACL |
| Staff list applications | STAFF | `membership-applications.view` | — | Middleware | |
| Staff view application | STAFF | `membership-applications.view` | — | Policy optional | Minimize props |
| Staff view private documents | STAFF | `membership-documents.view` | — | Policy + download endpoint | Not File Manager |
| Start review / notes / request revision | STAFF | `membership-applications.review` | `submitted` / `under_review` / `revision_requested` as 1F | Action | Permission ≠ valid transition |
| Reject | STAFF | `membership-applications.reject` | `under_review` | Action | |
| Approve | STAFF | `membership-applications.approve` | `under_review` only | Approve Action | Idempotent; no User/role |
| List/view Members | STAFF | `membership-members.view` | — | Middleware | |
| Update Member / card | STAFF | `membership-members.update` | Member exists | Action | |
| Deactivate Member | — | **not V1** | — | — | 1F deferred |
| Staff upload document | STAFF | `membership-documents.manage` | Application exists | Action | |

---

# Publishing & Knowledge

## 11. Publishing Capability Surface

| Capability | Authority |
|---|---|
| `publishing-publications.view` | Staff list/detail including drafts |
| `publishing-publications.create` | Create draft |
| `publishing-publications.update` | Edit body/meta |
| `publishing-publications.publish` | Publish / unpublish |
| `publishing-publications.delete` | Delete (prefer unpublish) |

`update` ≠ `publish` (editorial split ready; V1 `editor` may hold both). Types are filters, not permissions. Public read of `published` is PUBLIC.

---

## 12. Knowledge Capability Surface

Independent namespace. Tiptap reuse ≠ shared ACL.

| Capability | Authority |
|---|---|
| `knowledge-articles.view` | Staff including drafts |
| `knowledge-articles.create` | Create draft |
| `knowledge-articles.update` | Edit |
| `knowledge-articles.publish` | Publish / unpublish |
| `knowledge-articles.delete` | Delete |

Publishing permissions **do not** authorize Knowledge.

---

# Awards

## 13. Awards Capability Surface

1E-B left `awards-admin` vs `editor` and publish vs manage **open**. Capabilities are split **even if** a role later receives several.

| Capability | Authority | Distinct because |
|---|---|---|
| `awards-programs.manage` | Program identity + **set current edition** | Current pointer is HIGH; not CMS |
| `awards-editions.view` | Staff list/detail including `draft` | |
| `awards-editions.update` | Create/edit edition copy (Tiptap), not lifecycle | Editor may get this without operating the cycle |
| `awards-editions.operate` | Lifecycle: announced → open → close → **complete** | Completing publishes archive; HIGH |
| `awards-entries.view` | Entry queue (participant PII) | Not `editions.view` |
| `awards-entries.review` | Eligible / ineligible + notes | Not Recognition |
| `awards-recognitions.manage` | Record/correct official results | CRITICAL; not scoring |

No `awards-entries.create` for public submit. No judging/score permissions in V1.

---

## 14. Awards Authorization Matrix

| OPERATION | CHANNEL | STAFF CAPABILITY | DOMAIN STATE | NOTES |
|---|---|---|---|---|
| Browse Award / historical editions / winners | PUBLIC | — | Edition not `draft`; results when `completed` | Not current-pointer-only |
| Submit Entry | PUBLIC | — | `registration_open` | Not Spatie |
| Future Entry revision | SECURE CAPABILITY | — | Domain-revisable | Purpose `award-entry-revision` |
| Manage Program | STAFF | `awards-programs.manage` | — | |
| Switch current edition | STAFF | `awards-programs.manage` | Same-program invariant (1F) | Lock program |
| Create/edit Edition copy | STAFF | `awards-editions.update` | — | |
| Change Edition lifecycle / complete | STAFF | `awards-editions.operate` | Allowed 1F transitions | Complete ≠ reopen (default deny) |
| Review Entry / eligibility | STAFF | `awards-entries.view` and `awards-entries.review` | Entry exists | |
| Record Recognition | STAFF | `awards-recognitions.manage` | Same-edition if entry set | Not a score |
| Correct Recognition | STAFF | `awards-recognitions.manage` | Audit timestamps | Destructive-ish |
| Public results appear | PUBLIC after | `awards-editions.operate` (complete) | `completed` | Recording ≠ publishing |

---

# Platform

## 15. CMS Authorization

**KEEP** the registered CMS and Hero capabilities (`cms.view` / `create` / `update` / `delete`, `heroes.view` / `create` / `update` / `delete`) for V1 platform routes.

They **must not** mutate Membership, Awards lifecycle/Recognition, Publication status, or Knowledge status. Shared Tiptap does not grant `cms.update` on a Publication — Publication uses `publishing-publications.update`.

Later split (not blocking new domains; naming still `<domain>.<action>`): `cms-pages.view` (etc.), `cms-landing.view` (etc.), `cms-blocks.view` (etc.), distinct publish. Do **not** introduce `cms.pages.view`.

Welcome: keep `cms.view` / `cms.update` mapping as today until landing split.

---

## 16. Tiptap Boundary

No `tiptap.edit-everything`.

| Edited resource | Required capability |
|---|---|
| CMS Page / blocks / welcome | registered `cms.view` / `create` / `update` / `delete` (current) |
| Hero | registered `heroes.view` / `create` / `update` / `delete` |
| Publication | `publishing-publications.update` |
| Knowledge Article | `knowledge-articles.update` |
| Award Program / Edition HTML | `awards-programs.manage` / `awards-editions.update` |

---

## 17. Media Authorization

**KEEP** `media.view|create|update|delete` for File Manager.

| Does | Does not |
|---|---|
| Editorial browse/upload/restore/force-delete | Membership documents |
| Hero/page/publication covers via picker | Award Entry PII (none in V1) |

Future Award private files: Awards-owned capabilities, not `media.view`.

---

## 18. Identity / User Administration

**KEEP** `users.view` / `create` / `update` / `delete` and `roles.manage`. Object rules: non-super-admin cannot edit/delete super-admin (partially in `UserController` today — **Policy** later).

Never use `users.update` to change Member records. Never use `membership-members.update` to assign Spatie roles.

Jetstream profile/password/2FA: **self-service**, not `access settings`.

---

# Roles & Legacy

## 19. Role Model

| Role | Class | Notes |
|---|---|---|
| `super-admin` | **RETAIN** | Gate bypass; not daily editor |
| `user` | **REMOVE LATER** | Not Member; `media.view` only |
| `platform-admin` | **ADD LATER** | registered user capabilities + `roles.manage` (prose shorthand `users.*` is not a capability) |
| `editor` | **ADD LATER** | CMS + media + publishing + knowledge; **not** membership docs; **not** Award entry/recognition by default |
| `membership-admin` | **ADD LATER** | Membership capabilities |
| `awards-admin` | **ADD LATER** | Awards capabilities; **ROLE COMPOSITION DECISION** whether `editor` also gets `awards-editions.update` |
| Catch-all `admin` | **REJECT** (1D) | |

Application code checks **capabilities**, not `hasRole('editor')`.

---

## 20. Super Admin Review

**Current:** `Gate::before`: `hasRole('super-admin')` → `true` for **every** ability (including future ones). Vue `can()` same. Spatie middleware uses Gate, so bypass applies to `permission:` routes.

**Fail-closed:** violated **for that role only**, by design (break-glass). Unknown capabilities still fail for everyone else.

**Retain** as explicit architecture (1E). Required: tests that super-admin can access new modules; tests that non-super-admin cannot; never assign `super-admin` via public/membership flows; UserController (later Policy) restricts who may **assign** the role.

Not wildcard permission strings — it is a **role-level Gate bypass**. Do not add `*` permissions.

---

## 21. Legacy Permission Migration

| OLD | USAGE | TARGET | ACTION |
|---|---|---|---|
| `users.view` / `create` / `update` / `delete` | Routes + nav | same | **KEEP** |
| `roles.manage` | Routes + nav | same | **KEEP** |
| `cms.view` / `create` / `update` / `delete` | Pages, blocks, welcome | later `cms-pages.view` (etc.) / blocks | **KEEP** now; **SPLIT** later with dual-read if needed |
| `heroes.view` / `create` / `update` / `delete` | Hero CRUD | later `cms-landing.view` (etc.) | **KEEP** now; **RENAME** later |
| `media.view` / `create` / `update` / `delete` | File Manager | same meaning | **KEEP** |
| `access settings` | None | none | **RETIRE** (later; unused) |
| DB `culture.*` / `artikel.*` | Role UI if present | none | **NO TARGET — LEGACY**; `--clean` operator |
| *(none)* | — | membership / publishing / knowledge / awards sets | **ADD** with slices |

**No aliases** unless a split ADR needs temporary dual names. Canonical name is the only long-term name.

---

## 22. ACL Migration Order

Adapted to this repo (`acl.php` + `acl:sync` + route middleware + Vue exact match):

1. Extend **canonical registry** in `config/acl.php` (add groups; do not `--clean`).  
2. `acl:sync` **without** `--clean` (add rows; keep stale until planned).  
3. `permission:cache-reset` (command already does this).  
4. Seed/compose **new roles** when product agrees (`platform-admin`, `editor`, …) — assign **new** capabilities; do not strip existing staff until mapped.  
5. Add **server-side** `permission:` / Policy on **new** routes as those slices ship.  
6. Add **nav** items with **explicit** capability lists.  
7. Tests: 403 without capability; happy path; state machine still denies.  
8. Only later: CMS/heroes rename with dual-read; then drop old names.  
9. `--clean` stale Presbi rows **only** as a consented ops step.  
10. Retire role `user` and `access settings` when product agrees.

**Avoid:** removing `cms.view` before nav/routes updated; hiding menus before backend exists; `--clean` mid-feature; granting `media.view` as substitute for documents.

---

# Enforcement

## 23. Server-Side Enforcement Contract

| Layer | Use for |
|---|---|
| Route `permission:` | Module HTTP surface (kernel pattern — **keep** for new admin groups) |
| `authorize()` / Policy | Object rules (this Application; super-admin User; document download) |
| FormRequest | Validation; `authorize()` only if it **delegates** to Policy — do not fork rules |
| Application Action | **Domain state** (approvable? registration_open? same-edition?) |
| Gate::before | super-admin only |

**ACL:** may this User attempt this authority?  
**DOMAIN:** is this resource in a legal state?

Do not put state machines in Spatie names.

---

## 24. Route Authorization Contract

Do not modify routes now. **Authenticated ≠ authorized.** `/admin` is not a permission.

### Existing (fact)

| Route / action | Channel | Capability | Enforcement |
|---|---|---|---|
| `welcome`, CMS aliases, `pages.show` | PUBLIC | — | `is_active` on Page |
| `login` etc. | Identity | — | Fortify |
| `dashboard`, `admin.dashboard` | STAFF auth | **none** | `auth:sanctum` only |
| `admin.users.*` | STAFF | `users.view\|create\|update\|delete` | middleware |
| `admin.roles.*` | STAFF | `roles.manage` | middleware |
| `admin.files.*` | STAFF | `media.view` / `create` / `update` / `delete` | middleware |
| `admin.heroes.*` | STAFF | `heroes.view` / `create` / `update` / `delete` | middleware |
| `admin.pages.*`, blocks, welcome | STAFF | `cms.view` / `create` / `update` / `delete` | middleware |

### Future Core V1 (conceptual)

| Action | Channel | Capability | Enforcement |
|---|---|---|---|
| Public membership POST | PUBLIC | — | throttle + FormRequest |
| Grant redeem / revision POST | SECURE CAPABILITY | — | Grant + domain |
| Admin membership applications | STAFF | `membership-applications.view` / `review` / `approve` / `reject` as in §9 | middleware + Approve Policy/Action |
| Document download | STAFF | `membership-documents.view` | Policy; not `files.json` |
| Admin members | STAFF | `membership-members.view` / `update` | middleware |
| Admin publications | STAFF | `publishing-publications.view` / `create` / `update` / `publish` / `delete` | middleware + publish method |
| Admin knowledge | STAFF | `knowledge-articles.view` / `create` / `update` / `publish` / `delete` | middleware |
| Admin awards | STAFF | Awards capabilities as in §13 | middleware + Action invariants |
| Public award GET / entry POST | PUBLIC | — | edition status |

---

## 25. Navigation Contract

| Module shows if User has **any of** (explicit list) |
|---|
| Users | `users.view` |
| Roles | `roles.manage` |
| CMS / Pages / Blocks | `cms.view` |
| Heroes | `heroes.view` |
| File Manager | `media.view` |
| Membership | `membership-applications.view` **or** `membership-members.view` |
| Publishing | `publishing-publications.view` |
| Knowledge | `knowledge-articles.view` |
| Awards | `awards-editions.view` **or** `awards-programs.manage` **or** `awards-entries.view` **or** `awards-recognitions.manage` |
| Dashboard | authenticated (today) |

**No** `canAnyPrefix('awards.')` or `canAnyPrefix('awards-')`. Hidden menu ≠ 403 on URL.

---

## 26. Inertia / Frontend Capability Sharing

**Keep** sharing permission **name** arrays. Target `can()`: exact match; unknown → false; **no** prefix; super-admin bypass **UX only**.

Do not ship a second registry in Vue. Optional later: share `modules` booleans computed **server-side** from explicit lists in `acl.php`.

Dotted Inertia keys `auth.user.permissions` are awkward but existing (1E); optional cleanup, not a 1G blocker.

---

# Access Channel Separation

## 27. Secure Capability vs Staff ACL

| PROPERTY | STAFF ACL | SECURE CAPABILITY |
|---|---|---|
| Subject | Authenticated User | Bearer grant holder |
| Authentication | Session login | Redeem secret → capability session |
| Authority source | Spatie permission from registry | Purpose + target + digest |
| Duration | Until role/permission revoked | `expires_at` / revoke / consume |
| Scope | Staff module | One purpose + one resource |
| Role | Composition of capabilities | None |
| Target | Often a class of resources + Policy | Bound id |
| Public use | No | Yes (loginless) |
| Examples | `membership-applications.approve` | `membership-application-revision` |
| Cannot | Act as grant on participant route | Call Approve / File Manager |

---

## 28. Public Access vs ACL

| Operation | Not a Spatie permission | Gated by |
|---|---|---|
| Read published Page / Publication / Knowledge / completed Award | — | Visibility fields |
| POST Membership Application | **not** `membership-applications.create` | Validation, throttle |
| POST Award Entry | **not** `awards-entries.create` | `registration_open` + validation |
| Browse Award archive | — | Edition not `draft` |

Staff-created applications, if ever added, would be a **new** staff capability — not public submit.

---

# Target ACL

## 29. Core V1 Capability Registry

Identifiers below are canonical `<domain>.<action>` strings. Compact `| action` in earlier drafts meant the same domain; every name here is a full identifier.

### PLATFORM (existing — KEEP)

| CAPABILITY | OWNER | AUTHORITY | ENFORCEMENT | UI |
|---|---|---|---|---|
| `users.view` | Identity | List/view Users | middleware | Users menu |
| `users.create` | Identity | Provision User | middleware | |
| `users.update` | Identity | Update User/roles | middleware + Policy | |
| `users.delete` | Identity | Delete User | middleware + Policy | |
| `roles.manage` | ACL | Maintain roles | middleware | Roles menu |
| `cms.view` | CMS | View pages/blocks/welcome | middleware | CMS |
| `cms.create` | CMS | Create pages/blocks | middleware | |
| `cms.update` | CMS | Edit + today’s publish toggle | middleware | |
| `cms.delete` | CMS | Delete pages/blocks | middleware | |
| `heroes.view`–`delete` | CMS landing | Hero CRUD | middleware | Hero |
| `media.view`–`delete` | Media | File Manager | middleware | Files |

`access settings`: not in target set (retire).

### MEMBERSHIP (ADD with slice)

- `membership-applications.view`
- `membership-applications.review`
- `membership-applications.approve`
- `membership-applications.reject`
- `membership-members.view`
- `membership-members.update`
- `membership-documents.view`
- `membership-documents.manage`

### PUBLISHING (ADD)

- `publishing-publications.view`
- `publishing-publications.create`
- `publishing-publications.update`
- `publishing-publications.publish`
- `publishing-publications.delete`

### KNOWLEDGE (ADD)

- `knowledge-articles.view`
- `knowledge-articles.create`
- `knowledge-articles.update`
- `knowledge-articles.publish`
- `knowledge-articles.delete`

### AWARDS (ADD)

- `awards-programs.manage`
- `awards-editions.view`
- `awards-editions.update`
- `awards-editions.operate`
- `awards-entries.view`
- `awards-entries.review`
- `awards-recognitions.manage`

---

## 30. Capability Semantics

**users.view** — List and view staff User accounts.  
**users.create** — Create a staff User and assign an existing role (object rules still apply).  
**users.update** — Update a staff User’s profile/roles except where Policy forbids (e.g. super-admin).  
**users.delete** — Delete a staff User except self/last super-admin as Policy defines.  
**roles.manage** — Create/update roles and their permission assignments from the registry.  

**cms.view** — View CMS pages, block templates, and welcome editor.  
**cms.create** — Create CMS pages and block templates.  
**cms.update** — Edit CMS pages/blocks/welcome and (today) toggle page visibility.  
**cms.delete** — Delete CMS pages and block templates.  
**heroes.view / create / update / delete** — Staff CRUD for landing heroes.  

**media.view** — Browse **editorial** File Manager (not membership documents).  
**media.create** — Upload editorial assets.  
**media.update** — Rename/restore editorial files.  
**media.delete** — Soft-delete / force-delete editorial files.  

**membership-applications.view** — Staff list/detail of Membership Applications including applicant metadata; does not by itself authorize document **bytes**.  
**membership-applications.review** — Attempt review workflow (start review, notes, request revision) on an Application; domain transitions still apply.  
**membership-applications.approve** — Attempt approval of an eligible Application; creates at most one Member; domain rules still apply.  
**membership-applications.reject** — Attempt rejection of an eligible Application; no Member is created.  
**membership-members.view** — Staff list/detail of AB2TI Members.  
**membership-members.update** — Update Member master contact/card flags; not status deactivate in V1.  
**membership-documents.view** — Retrieve private membership document bytes.  
**membership-documents.manage** — Staff attach/replace membership documents.  

**publishing-publications.view** — Staff list/detail of Publications including drafts.  
**publishing-publications.create** — Create a Publication draft.  
**publishing-publications.update** — Edit Publication content/metadata (not necessarily publish).  
**publishing-publications.publish** — Publish or unpublish a Publication.  
**publishing-publications.delete** — Delete a Publication (prefer unpublish).  

**knowledge-articles.view** — Staff list/detail of Knowledge Articles including drafts.  
**knowledge-articles.create** — Create a Knowledge Article draft.  
**knowledge-articles.update** — Edit Knowledge Article content.  
**knowledge-articles.publish** — Publish or unpublish a Knowledge Article.  
**knowledge-articles.delete** — Delete a Knowledge Article.  

**awards-programs.manage** — Update Award Program and set current edition (same-program invariant still applies).  
**awards-editions.view** — Staff list/detail of Award Editions including drafts.  
**awards-editions.update** — Create/edit Edition editorial content without changing lifecycle.  
**awards-editions.operate** — Attempt Edition lifecycle transitions including complete; domain transitions still apply.  
**awards-entries.view** — Staff list/detail of Award Entries (participant data).  
**awards-entries.review** — Mark Entry eligible/ineligible and write review notes.  
**awards-recognitions.manage** — Record or correct official Award Recognitions (not scoring); same-edition invariant still applies.

---

## 31. Role Composition Proposal

**ROLE COMPOSITION REQUIRES PRODUCT/OPERATIONS DECISION.** Capabilities above are not blocked.

| CURRENT | TARGET | CAPABILITIES (intent; not wildcard checks) | NOTES |
|---|---|---|---|
| `super-admin` | same | Gate bypass (all) | Do not enumerate every permission on the role |
| `user` | retire later | today `media.view` | Not Member |
| — | `platform-admin` | `users.view`, `users.create`, `users.update`, `users.delete`, `roles.manage` | No approve, no documents, no Award results |
| — | `editor` | registered `cms.*`, `heroes.*`, `media.*`, `publishing-publications.*`, `knowledge-articles.*` in §29 (shorthand only) | **Not** membership capabilities; **not** `awards-entries.review` / `awards-recognitions.manage`. **DR:** `awards-editions.update`? |
| — | `membership-admin` | all membership-applications / membership-members / membership-documents capabilities in §29 | Not media as document substitute |
| — | `awards-admin` | all awards-programs / awards-editions / awards-entries / awards-recognitions capabilities in §29 | May also hold `media.view` if they pick covers |

Multiple roles on one User are expected.

---

## 32. Privilege Risk Classification

| Level | Capabilities |
|---|---|
| **LOW** | `cms.view`, `publishing-publications.view`, `knowledge-articles.view`, `awards-editions.view`; dashboard auth (not `awards-entries.view`) |
| **MODERATE** | `cms.create`, `cms.update`, `publishing-publications.create` / `update`, `knowledge-articles.create` / `update`, `awards-editions.update`, `media.create`, `media.update`, `users.view` |
| **HIGH** | `cms.delete`, `media.delete`, `publishing-publications.publish`, `knowledge-articles.publish`, `membership-applications.view` / `review` / `reject`, `membership-documents.view` / `manage`, `membership-members.view` / `update`, `awards-entries.view` / `review`, `awards-editions.operate`, `awards-programs.manage`, `users.create` / `update` / `delete` |
| **CRITICAL** | `membership-applications.approve`, `awards-recognitions.manage`, `roles.manage`, super-admin bypass |

---

## 33. Destructive Action Review

Distinct authority and later UX confirm (not designed now): `media.delete` / force-delete; `cms.delete`; `publishing-publications.delete` / `knowledge-articles.delete`; `membership-applications.reject`; Recognition **correct**; `awards-editions.operate` complete; current-edition switch; `roles.manage`; User delete. Reopen completed Edition: default **deny** (1F) even with `operate`. Member deactivate: not V1.

---

## 34. Audit Boundary

Authorization ≠ audit. Sensitive ops should use 1F history: application transitions; approve/reject; grant issue/revoke; Award eligibility `decided_at`; Recognition recorded/updated; current-edition `set_at/by`; Spatie role assignment (Spatie events optional later). No new audit framework in 1G.

---

# Verification & Migration Safety

## 35. Authorization Test Contract

Do not write tests now. When implementing:

- Permitted staff 200/allowed; other staff 403; guest redirect on admin.  
- Public POST only when domain allows; closed Award edition 403/422.  
- Unknown capability false; no prefix match tests.  
- UI hide ≠ skip server test (call URL).  
- Grant cannot approve; staff session cannot hit grant-only mutation without grant.  
- Approve permission + wrong state → deny.  
- Recognition + wrong edition/entry → deny (1F FK + op).  
- `media.view` cannot GET membership document.  
- super-admin allowed; assigning super-admin restricted.  
- `acl:sync` from config; tests use `InteractsWithAcl`.

---

## 36. ACL Drift Detection

Laravel-native:

- PHPUnit: every `config('acl.permissions')` flatten name appears in a **registry snapshot test**; route middleware names ⊆ registry.  
- `acl:sync` without `--clean` reports obsolete DB rows (already).  
- Nav permission strings: test file or config `modules` lists ⊆ registry.  
- Fail CI if Vue hardcodes a name not in config (optional grep test).  

No static-analysis platform required.

---

## 37. Config / Database Source of Truth

```
config/acl.php  →  acl:sync  →  Spatie permission rows  →  cache
```

DB must not grow independently designed names. `--clean` **removes** DB names not in config **and detaches them from roles** — only when operators intend that. Feature work: **add** to config, sync **without** `--clean`.

---

## 38. Migration Safety

- Deploy: config + code that **understands new names** before requiring them on existing routes.  
- Adding permissions: sync add-only; existing roles keep old perms.  
- Then assign new perms to roles.  
- Then attach middleware to **new** routes.  
- Renames: dual-check period or keep old names (CMS split).  
- Cache reset after sync (command does this).  
- Mixed deploy: new code should not require permissions not yet synced.  
- Rollback: keep new permissions in DB (harmless extras); do not `--clean` on rollback.  
- Seeder: `firstOrCreate` users — do not reset production roles.  
- Never `--clean` on production as part of a feature PR.

---

## 39. Legacy Cleanup Boundary

| Item | Class |
|---|---|
| registered `users.*`, `roles.manage`, `media.*`, `cms.*`, `heroes.*` (shorthand — see §7.1) | **SAFE PLATFORM RETENTION** |
| `access settings` | **REMOVE DURING LATER CLEANUP** |
| Role `user` | **LEGACY AUTHORIZATION** / retire later |
| DB `culture.*` / `artikel.*` | **LEGACY AUTHORIZATION** — `--clean` consented |
| Church domain permissions in code | **None present** |
| Using `media.view` for KTP | **BLOCKING CONFLICT** if implemented — forbidden by contract |

Do not delete now.

---

# Closure

## 40. Open Decision Classification

| Question | Class |
|---|---|
| Exact strings in §29 | **Settled in this plan** (not an ACL DESIGN BLOCKER) |
| `awards-admin` vs `editor` getting `awards-editions.update` | **ROLE COMPOSITION DECISION** |
| `publish` vs `manage` for Awards | **Settled:** `awards-editions.operate` vs `awards-editions.update` vs `awards-recognitions.manage` |
| Membership review vs approve split | **Settled:** split |
| When to seed `platform-admin` / `editor` / … | **NON-BLOCKING OPERATIONS** |
| Retire role `user` | **NON-BLOCKING OPERATIONS** |
| Retire `access settings` / `--clean` stale rows | **NON-BLOCKING OPERATIONS** |
| CMS/heroes rename timing | **NON-BLOCKING** (dedicated ADR) |
| Staff capture Membership Application | **FEATURE IMPLEMENTATION** (no V1 capability) |
| Member deactivate | **FEATURE** (no V1 capability) |
| Recognition without Entry product rule | **FEATURE** (schema nullable; ACL still `awards-recognitions.manage`) |
| Extra `awards-results.publish` (or rejected `awards.results.publish`) | **Not needed** — `awards-editions.operate` completes |

No **ACL DESIGN BLOCKER** for naming the registry.

---

## 41. Target Authorization Map

```
PUBLIC
  +-- published CMS / Publication / Knowledge
  +-- Award landing + historical editions + completed results
  +-- Membership Application submit
  +-- Award Entry submit (registration_open)

SECURE CAPABILITY GRANT
  +-- membership-application-revision
  +-- (future) award-entry-revision
  ✗ Spatie  ✗ roles  ✗ Approve  ✗ File Manager

AUTHENTICATED STAFF USER
  +-- config/acl.php registry → Spatie rows
        +-- users.view|create|update|delete / roles.manage
        +-- cms.view|create|update|delete / heroes.view|create|update|delete / media.view|create|update|delete
        +-- membership-applications.view|review|approve|reject
        +-- membership-members.view|update
        +-- membership-documents.view|manage
        +-- publishing-publications.view|create|update|publish|delete
        +-- knowledge-articles.view|create|update|publish|delete
        +-- awards-programs.manage
        +-- awards-editions.view|update|operate
        +-- awards-entries.view|review
        +-- awards-recognitions.manage

ROLE (bundle only)
  super-admin     → Gate::before
  platform-admin  → identity/ACL
  editor          → content (+ optional Award copy DR)
  membership-admin
  awards-admin

✗ role "member"  ✗ prefix awards. / awards-  ✗ token→permission
```

---

## 42. Authorization Decision Register

### CONFIRMED

- Three channels; grants ≠ Spatie.  
- `config/acl.php` + `acl:sync` remains the registry pipeline.  
- New names: `<domain>.<action>` (compound kebab-case domain allowed). Kernel two-part KEEP. `{context}.{resource}.{action}` **rejected**.  
- Capabilities are opaque identifiers. Prose `users.*` / `cms.*` / `media.*` is shorthand for registered names only — never seeded, assigned, or checked.  
- Membership: review ≠ approve; documents ≠ media.  
- Publishing ≠ Knowledge namespaces.  
- Awards: `awards-editions.update` ≠ `awards-editions.operate` ≠ `awards-recognitions.manage`; `awards-programs.manage` sets current edition.  
- Public submit is not Spatie.  
- super-admin Gate bypass **retained**, explicit.  
- Catch-all `admin` role rejected.  
- Add capabilities when slices ship; no `--clean` in feature work.  
- Navigation explicit lists.

### TARGET CAPABILITIES

Section 29.

### LEGACY MIGRATION

Section 21. CMS/heroes split later. Stale DB rows operator `--clean`.

### ROLE COMPOSITION ASSUMPTIONS

Section 31. `editor` does **not** get membership docs or Award recognition/entry review unless product assigns extra roles.

### DECISIONS REQUIRED BEFORE ACL IMPLEMENTATION

Not required to **write** the registry into config, but required before **assigning production roles**:

- Compose/seed `platform-admin`, `editor`, `membership-admin`, `awards-admin`.  
- Whether `editor` includes `awards-editions.update`.  
- Timing to retire `user` and `access settings`.  

Capability **implementation on a slice** can proceed with super-admin + direct `givePermissionTo` in tests without final org roles.

### DEFERRED

Koperasi/community namespaces (do not seed empty). Member self-service. Judging permissions. CMS publish split. Prefix-free module helper in config. Inertia key rename.

---

## 43. Implementation Readiness

**PASS / CLOSED**

Canonical capability set, identifier format (`<domain>.<action>`), and migration order are defined. Role packaging and CMS rename timing do not block adding registry groups or protecting new routes.

Do **not** implement ACL in this phase.

---

## 44. Recommended Next Phase

An **explicit implementation task** (not started here): extend `config/acl.php` with the first vertical-slice group (likely Membership **or** Publishing) + routes/Policies/tests — **or** a reserved-URL / schema migration task per 1F, still without `--clean`.

Do not begin that from this document automatically.

---

End of Phase 1G ACL / authorization migration plan.
