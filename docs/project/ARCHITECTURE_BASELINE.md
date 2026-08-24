# AB2TI 2.0 Architecture Baseline

This document describes **current reality** after Phase 0A (legacy Presbi/church domain removal). It is not a target architecture.

Captured from repository inspection on 2026-08-19.

Source commit: `8b6d413b47585d697f1dd4e9893ea46a252e6194` (Phase 0C-A). Phase 0C-B updates CMS reserved paths, Hero media, ACL/config hygiene, and local-DB procedure.

---

## Runtime / Framework Stack

Discovered from `composer.json`, `composer.lock`, and `package.json`.

### PHP / Laravel (lockfile)

| Package | Constraint | Locked |
|---|---|---|
| PHP | `^8.2` | (runtime requirement) |
| `laravel/framework` | `^12.0` | `v12.49.0` |
| `inertiajs/inertia-laravel` | `^2.0` | `v2.0.19` |
| `laravel/jetstream` | `^5.4` | `v5.4.0` |
| `laravel/sanctum` | `^4.0` | `v4.3.0` |
| `spatie/laravel-medialibrary` | `^11.19` | `11.19.0` |
| `spatie/laravel-permission` | `^6.24` | `6.24.0` |
| `tightenco/ziggy` | `^2.0` | `v2.6.0` |
| `symfony/intl` | `^8.0` | present |
| PHPUnit | `^11.5.3` | present (dev) |
| Laravel Pint | `^1.24` | present (dev); **no** `pint.json` |
| Laravel Sail | `^1.41` | present (dev); **no** `docker-compose` in repo |

Fortify is installed via Jetstream (not a direct `composer.json` require).

### Frontend (`package.json`)

| Package | Constraint | Notes |
|---|---|---|
| Vue | `^3.3.13` | JavaScript, not TypeScript (`jsconfig.json` only) |
| `@inertiajs/vue3` | `^2.0` | |
| Tailwind CSS | `^3.4.0` | `tailwind.config.js` + `@tailwind` directives |
| `@tailwindcss/vite` | (removed in 0C-B; was unused vs Tailwind 3) |
| Vite | `^7.0.7` | `vite.config.js` uses `laravel-vite-plugin` + `@vitejs/plugin-vue` |
| Tiptap | `^3.20.1` | CMS editor |
| `laravel-vue-i18n` | `^2.8.0` | |
| `browser-image-compression` | `^2.0.2` | File Manager / page cover uploads |

No ESLint, Prettier, or Pest config files. Editor formatting: `.editorconfig` (4-space indent, LF).

Default `.env.example` uses **SQLite**, database cache/queue/session. MySQL is configured in `config/database.php` but not selected by the example env.

---

## Backend Foundation

Laravel 11+/12 application structure (`bootstrap/app.php`).

Present under `app/`:

- `Http/Controllers` and `Http/Controllers/Admin`
- `Http/Middleware` (`SetLocale`, `HandleInertiaRequests`, capability headers / revision-grant middleware)
- `Models` (16): `User`, `Page`, `BlockTemplate`, `Hero`, `WelcomeSection`, `File`, `MembershipApplication`, `MembershipApplicationTransition`, `MembershipDocument`, `Member`, `SecureCapabilityGrant`, `Publication`, `KnowledgeArticle`, `Lapak`, `LapakListing`, `LapakCategory`
- `Actions/Fortify` and `Actions/Jetstream` (auth) plus `Actions/Membership` (Phase 2A/3B/3C, account link, site-access activation), `Actions/Publishing` (Phase 2B), `Actions/Knowledge` (Phase 2C), and `Actions/Lapak` (Phase 4C.1)
- `Http/Requests/Membership`, `Http/Requests/Publishing`, `Http/Requests/Knowledge`, and `Http/Requests/Lapak`
- `Policies` (Membership + `PublicationPolicy` + `KnowledgeArticlePolicy` + Lapak policies)
- `Enums` (Membership + `PublicationType` / `PublicationStatus` + `KnowledgeArticleStatus` + `LapakModerationStatus` / `LapakListingAvailability` / `LapakPriceMode`)
- `Support`: `CmsPath`, `Cms` (`CmsPageViews`), `Http` (`ApplicationErrorPage`), `Auth` (`PostAuthenticationHome`), `PublicDelivery`, `Membership`, `SecureCapability`, `Publishing`, `Knowledge`, `Lapak`, `Html/EditorialHtml`, `Editorial` (`EditorialContent`, `TemplateBlockRegistry`)
- `Providers`: `AppServiceProvider`, `FortifyServiceProvider`, `JetstreamServiceProvider`
- `Console/Commands`: `acl:sync`, `lang:merge`

Not present:

- `app/Services`
- `app/Repositories`, `app/Observers`

Dominant pattern: **route → controller → inline `$request->validate()` or FormRequest → Eloquent (+ Spatie Media) → `Inertia::render()`**.

Membership approval/reject/review/submit, Publication publish/unpublish, and Knowledge Article publish/unpublish use **named Actions**. Membership status writes go through `MembershipApplicationLifecycle`. Admin CMS controllers remain resource-style CRUD.

---

## Authentication

- **Fortify** guard `web`; Jetstream stack `inertia`; Jetstream guard `sanctum`.
- Features enabled: password reset, profile/password update, two-factor authentication, API tokens, account deletion.
- **Public self-registration is disabled** (`Features::registration()` is not listed). See ADR `0002-disable-public-registration.md`. The only Member-facing account-creation path is a valid `member-account-link` invitation (`POST /akun/tautkan/{secret}/aktivasi`). Staff may still create Users through admin user management; that does not link a Member.
- **Fortify email verification feature is disabled.** `User` does **not** implement `MustVerifyEmail`. Email verification remains a deferred product decision. Provisioned Member accounts follow the same rule.
- Features disabled: Jetstream teams, Jetstream profile photos, terms/privacy, Fortify registration, Fortify email verification.
- Login rate limit: 5/minute (Fortify).
- Session driver in `.env.example`: database; `SESSION_ENCRYPT=false`.

This is session SPA authentication, not a token-first public API. `routes/api.php` only exposes `GET /api/user` behind `auth:sanctum`.

---

## Authorization

Spatie Permission is installed. Permissions are defined in `config/acl.php` and synced with `php artisan acl:sync`.

- `acl:sync` is **additive**: create missing canonical permissions; report stale DB names; do not delete.
- `acl:sync --clean` is **explicit cleanup**: detach stale permissions from roles/users, then delete the permission rows. Roles and users are kept.
- `acl:sync --dry-run` reports either mode without writing.

`Gate::before` grants every ability to role `super-admin`.

Current groups: `users`, `roles`, `cms`, `settings`, `media`, `heroes`, plus Membership, Publishing, Knowledge, and Lapak groups in `config/acl.php`.

**Pre-4C.4 admin shell:** Authentication is not admin authorization. `User::isPlatformStaff()` (`super-admin`, `membership-admin`, `editor`) is the server-side staff boundary. Middleware alias `platform-staff` (`EnsurePlatformStaff`) wraps `/dashboard` and the entire `/admin` group. Guests are redirected to login. Authenticated non-staff receive branded HTTP 403. Member ownership (`/akun/lapak`) does not grant admin-shell access. Granular Spatie `permission:` middleware and Policies still control module actions after the staff boundary. `/dashboard` remains a staff-only alias of the same `DashboardController` as `/admin/dashboard`. `config/fortify.php` `home` is still `/dashboard`, but post-authentication destinations are `PostAuthenticationHome` / `LoginResponse` (staff → `admin.dashboard`, linked Member → `account.lapak.index`, other Users → `welcome`). Non-staff logins ignore administrative `url.intended`.

**Phase 0C-A:** `/admin/*` mutating and module-read routes use Spatie `permission:` middleware (aliases registered in `bootstrap/app.php`). Guests are redirected to login by `auth:sanctum`. Authenticated users without the mapped permission receive HTTP 403.

Vue `usePermission` / `useNavigation` still hide menus (UX only). `auth.can_access_admin` matches `isPlatformStaff()` and is UX only. Navigation permission names were aligned to ACL: File Manager uses `media.view`; Pages / Block Templates / Welcome Section use `cms.view`; Hero Manager uses `heroes.view`. Jetstream `profile.show` is not gated by `access settings`.

**Phase 0C-B:** unused `culture.*` / `artikel.*` groups were removed from `config/acl.php`. Developer databases previously synced MAY still contain those permission rows until an intentional `acl:sync --clean`. Still unused in config: `access settings`.

Do **not** run `acl:sync` or `--clean` against the active development database as a side effect of feature work. Tests seed permissions on the in-memory SQLite test database. Operator databases can be inspected with `acl:sync --dry-run`.

---

## Frontend

Inertia Vue 3 SPA.

- Entry: `resources/js/app.js` (client), `resources/js/ssr.js` (SSR entry exists; i18n is not wired in SSR).
- Pages resolved from `resources/js/Pages/**/*.vue`.
- Ziggy for named routes.
- Composables: `usePermission`, `useNavigation`, `useConfirm`, `useUndoToast`.
- No Pinia/Vuex stores. No TypeScript.

Public crawler metadata is rendered from the `seo` Inertia prop in `resources/views/partials/public-delivery-meta.blade.php` (not Vue `<Head>`). See ADR 0004.

Shared Inertia props (`HandleInertiaRequests`): `locale`, flash (`success`/`error`/`warning`/`info`), Ziggy, PHP keys `auth.user.roles` and `auth.user.permissions` (literal dotted keys; Jetstream also shares `auth.user`), `auth.can_access_admin` (`User::isPlatformStaff()`), `auth.linked_to_member`, `canRegister`, `organization` (public name/short name/secretariat contact from `config/organization.php`; no social accounts), and `seo` (public-delivery metadata; controllers may override).

---

## Layout System

| Layout | Role | Status |
|---|---|---|
| `AppLayout.vue` | Authenticated admin (topbar, sidebar, toast, confirm) | Canonical for admin pages |
| `FrontendLayout.vue` | Public site | Canonical for public pages (skip link, header, `BaseModal` mobile nav, `PublicFooter`) |
| Auth cards (`AuthenticationCard`) | Login/register/password/2FA | Canonical for Fortify views |
| `GuestLayout.vue` | Minimal wrapper | Present; not the auth page wrapper |
| `WebLayout.vue` | Alternate public/app hybrid | **Removed in Phase 4C.4** (unused; stale Dashboard links) |

---

## Design-System Candidates

Theme tokens live in `resources/css/app.css` and `tailwind.config.js`: brand `primary` / `secondary` / `accent`, surfaces, `text-main` / `text-muted`, `border`, plus status `success` / `warning` / `danger` / `info` and `focus`. Canonical UI typeface is **Inter** (`font-sans`). Interface contract: `docs/implementation/pre-phase-2a-b-ui-ux-hci-interface-contract.md`.

`resources/js/Components/Base/`:

| Component | Status |
|---|---|
| `BaseButton` | **Canonical** for new AB2TI screens |
| `BaseCard` | Canonical |
| `BaseInput` | Canonical |
| `BaseSelect` | Canonical |
| `BaseList` | Canonical admin list (prefer over dense tables) |
| `BaseModal` | Canonical dialog |
| `BasePagination` | Canonical |
| `BaseSearchSelect` | Present; **no remaining callers** — use for long searchable lists |
| `DashboardStatCard` | Present; unused — do not use until a real dashboard |

Jetstream leftovers (`PrimaryButton`, `SecondaryButton`, `DangerButton`, `Modal`, `DialogModal`, `ConfirmationModal`) remain on Fortify/profile. **Do not use them on new domain screens.** Canonical confirm: `ConfirmDialog` + `useConfirm`.

Public nav: `NavLink` uses brand tokens (not Jetstream indigo). Public destinations are a single named-route contract in `useNavigation().frontendMenus` (navbar, mobile `BaseModal`, footer): Beranda → Tentang AB2TI → Publikasi → Teknologi Tani → Lapak Anggota → Keanggotaan → Kontak. Labels are i18n keys; templates translate once. The primary navbar is site navigation only. Theme, language, and authorized Dashboard/Admin access live in the existing public top-bar utility cluster (`PublicTopbarUtilities`). Membership application is a contextual CTA on `/keanggotaan` and the homepage invite, not a global navbar button. Desktop nav yields to the mobile menu below the `xl` (1280px) breakpoint. Koperasi and Community are not listed.

Public layout rhythm: `PublicContainer` / `PublicSection` / `SectionHeading`. Homepage composition is `WelcomeController` (not a route closure). Secretariat contact is `config/organization.php` (Phase 1A address/phone/fax/email/WhatsApp). No invented social URLs.

`BaseModal` is the canonical AB2TI dialog: `role="dialog"`, `aria-modal`, focus moves in on open, Tab/Shift+Tab cycle inside, Escape and backdrop close only when `closeable`, focus restores to the opener. Jetstream `Modal` / `DialogModal` remain on Fortify/profile security confirmations.

Production HTML errors for 403, 404, and 419 (and 500 when `APP_DEBUG=false`) render Inertia `Errors/Show` via `App\Support\Http\ApplicationErrorPage`. HTTP status codes are preserved. Copy is generic Indonesian. `APP_DEBUG=true` keeps Laravel diagnostics for 500. Error responses are noindex.

---

## CMS

- `pages`: slug (unique), HTML `content`, `meta_title`, `meta_description`, JSON `settings`, `is_active`, Spatie collections `page-cover` (featured) and `social-og` (optional dedicated share image); conversions `thumb` and `social-og` (1200×630 JPEG crop, non-queued).
- `block_templates`: reusable HTML snippets for Tiptap (`html_content`, `is_active`). Snippet HTML is normalized through the shared editorial pipeline on save and when offered to the picker.
- `heroes`: landing hero + Spatie collection `hero-backgrounds` (explicitly registered; conversions `large` and `thumb`; multiple files).
- `welcome_sections`: landing welcome copy/CTA/`image_url`.
- Public renderer: `PageController@show` loads an active page, then `CmsPageViews::publicDetail()` (title, slug, rendered content, cover URL/alt, related_links). Rendering still goes through `EditorialContent::render`. `Frontend/Page/Show.vue` receives the shaped array, not the Eloquent `Page` (no media internals, `settings`, or `is_active`).
- Shortcuts: `/about` and `/contact` map to page slugs of the same name. Presbi aliases `/sermons` and `/events` are retired (ADR 0005): reserved, 404, no redirects. First segment `kontak` is reserved so CMS cannot occupy a future Indonesian contact path; public contact remains `/contact`.
- Catch-all: `GET /{slug}` last; constraint from `config/cms.php` via `App\Support\CmsPath` (ADR 0003). Nested slugs allowed. Reserved first segments cannot be CMS pages.
- Page create/update validation rejects reserved first segments and allows nested slugs (`about/team`).
- Admin CMS routes register only implemented resource verbs. Unused `show` actions are not registered, so `/admin/{resource}/create` is not captured by `{id}`. Users/Roles follow the same rule (modal create/edit; no GET create/show pages).
- Hero list status uses `PATCH admin/heroes/{hero}/toggle` (`heroes.update`); it does not PUT the index projection through `update`.
- CMS admin navigation: File Manager, Hero Manager, Welcome Section, Pages, Block Templates.

There is no posts/news/tags/menu CMS, no scheduling, no draft workflow beyond `is_active`. Editorial news/activity content is **Publication** (Publishing bounded context), not a CMS Page.

---

## Media / Files

- `files` table + Spatie `media` (polymorphic).
- `File` model: collection `default`, conversion `thumb` (300×300, non-queued), soft deletes.
- `FileManagerController`: index/create/store/destroy/getJson/storeJson/**restore**/**forceDelete**.
- Restore: `File::onlyTrashed()->findOrFail` then `restore()`. JSON payload includes the presented `file` (id, urls, mime, size) so the library undo toast can reinsert the row. Media is retained on soft-delete and is not duplicated.
- Force-delete: only **trashed** records; `forceDelete()` inside a transaction so Spatie removes related `media` rows/files.
- `store()` accepts any file up to 20MB; `storeJson()` restricts image MIME types.
- Disks: Laravel `public` (linked) and `local` (`storage/app/private`). S3 is configured but unused by default.
- Image compression happens in Vue before upload.

File Manager is platform infrastructure, distinct from any future AB2TI document domain.

---

## Internationalization

- Locales: `id` and `en`. Switcher stores `locale` in session (`/language/{language}`).
- Middleware `SetLocale` applies session locale.
- Source strings: `lang/{locale}/sources/*.json`.
- Compile: `php artisan lang:merge {locale}` writes `lang/{locale}.json`.
- Vue: `laravel-vue-i18n` loads `lang/${lang}.json`.
- `id` sources currently: dashboard, general, hero, landing, menu, navigation.
- `en` sources currently: dashboard, hero, landing, navigation (no `general.json`).

---

## Database

Canonical **forward** schema is the migration graph, not whatever happens to exist in a developer database.

Current migrations (after Phase 0A):

- Laravel defaults: `users`, `password_reset_tokens`, `sessions`, `cache`, `jobs` (+ batches/failed_jobs)
- `personal_access_tokens`
- Spatie permission tables
- `files` (+ soft deletes)
- `media`
- `heroes`
- `pages`
- `block_templates`
- `welcome_sections`
- Empty no-op: `2026_02_13_234325_add_heroes_permissions.php`

Temporary isolated migrate (Phase 0A) produced only those tables.

**Important:** Phase 0A removed church-domain *migration files*. It did **not** drop tables on existing developer databases. A local SQLite/MySQL database that was migrated before cleanup **may still contain** `congregations`, `letters`, `songs`, and related tables. Those leftovers are not canonical AB2TI schema.

---

## Testing

PHPUnit 11 (`phpunit.xml`): Unit + Feature suites. In-memory SQLite for tests.

Present:

- Jetstream/Fortify feature tests (auth, profile, password, 2FA, API tokens, registration disabled, browser sessions, account deletion)
- `tests/Feature/Admin/FileManagerTest.php`
- `tests/Feature/Admin/AdminAuthorizationTest.php`
- `tests/Feature/Admin/HeroMediaTest.php`
- `tests/Feature/Cms/CmsRoutingTest.php`
- `tests/Feature/PublicDelivery/PublicMetadataHttpTest.php`
- `tests/Unit/PublicDelivery/PublicMetadataResolverTest.php`
- Placeholder `ExampleTest` (unit + feature GET `/` with `RefreshDatabase`)

Known failures (Phase 0A, **resolved in Phase 0C-A**):

1. FileManager restore / force-delete — implemented; tests pass on the in-memory SQLite suite.
2. `ExampleTest` GET `/` — uses `RefreshDatabase` so `heroes` exists.

Quality commands that actually exist:

- `composer test` → `php artisan config:clear` + `php artisan test`
- `npm run dev` → `vite`
- `npx vite build` / **`npm run build`** — client production Vite build (`vite build`)

---

## Build Pipeline

| Command | Reality |
|---|---|
| `composer dev` | `artisan serve` + queue listen + pail + `npm run dev` |
| `composer setup` | install, copy `.env` if missing, `key:generate`, `php artisan migrate` (**no `--force`**), `npm install`, `npm run build`. Intended for a new local checkout; do not run against a populated developer DB unasked. |
| `npm run build` | Client production Vite build (`vite build`); SSR not wired |
| `npx vite build` | Same client production build |
| CI/CD | None (no `.github/workflows`, no GitLab CI) |
| Docker | Sail package only; no compose file |

README is still the default Laravel skeleton README.

---

## Known Technical Debt

Verified from source and Phase 0A/0C-A validation. Not a wish list.

1. ~~Admin authorization is UI-only.~~ **Resolved in 0C-A** (route `permission:` middleware). ~~Dashboard remains auth-only.~~ **Resolved in Pre-4C.4:** admin shell requires `platform-staff` (`User::isPlatformStaff()`); module ACL remains a second layer.
2. ~~Public registration is enabled.~~ **Resolved in 0C-A** (Fortify registration disabled; ADR 0002).
3. `MustVerifyEmail` is not implemented; Fortify email-verification **feature is disabled**. Product decision remains deferred.
4. ~~CMS catch-all `/{slug}` uses `.*`.~~ **Resolved in 0C-B** (reserved first segments; ADR 0003).
5. ~~`/sermons` and `/events` remain CMS aliases.~~ **Retired in Phase 2C.1** (ADR 0005): reserved first segments, no redirects.
6. `access settings` remains unused; Jetstream profile is self-service.
7. ~~`culture.*` / `artikel.*` in acl.php.~~ **Removed from config in 0C-B**. `acl:sync --clean` (with `--dry-run`) is the operator cleanup path. Stale Presbi permission rows MAY remain in operator databases until a consented `--clean`.
8. ~~FileManager restore/forceDelete routes without methods.~~ **Resolved in 0C-A**.
9. ~~Hero media collection registration incorrect.~~ **Resolved in 0C-B** (`hero-backgrounds`).
10. ~~`npm run build` is unusable.~~ **Resolved in 0C-A**. ~~`composer setup` `--force`.~~ **Removed in 0C-B**.
11. `HandleInertiaRequests` shares dotted `auth.user.*` keys.
12. Dual button/modal component families (Jetstream vs Base). Simple auth/profile submit buttons now use `BaseButton`. Jetstream `DialogModal` / `ConfirmationModal` / `DangerButton` remain on 2FA disable, account deletion, other-browser logout, password confirmation, and API tokens.
13. ~~Tailwind v3 vs unused `@tailwindcss/vite` v4.~~ **Removed unused package in 0C-B**.
14. `bacon/bacon-qr-code` is **kept** (transitive via `laravel/fortify` for 2FA QR codes).
15. Historical church tables may remain in developer databases — see `docs/project/LOCAL_DATABASE_RECOVERY.md`.
16. `BaseSearchSelect` / `DashboardStatCard` unused — **deferred until a real AB2TI use case**.
17. README is still the default Laravel skeleton README (not application chrome).
18. Form Requests and Policies exist for Membership (Phase 2A) and Publishing (Phase 2B). Other admin modules still validate inline. No CI.
19. Site-wide OG fallback is a functional placeholder JPEG (`public/images/og/ab2ti-og-default-v1.jpg`); brand-approved 1200×630 artwork is still required.
20. Jetstream button/modal family still used on Fortify/profile **security confirmations**; new screens must use Base (Pre-Phase 2A-B).
21. ~~Branded 404/403/419/500 pages are not implemented.~~ **Resolved in Phase 2E** (`ApplicationErrorPage` → Inertia `Errors/Show`).

See `docs/project/FOUNDATION_BACKLOG.md` for classification and recommended hardening order.

---

## Phase 2A — Membership (current reality)

Implemented 2026-08-20. Details: `docs/implementation/phase-2a-membership-vertical-slice.md`.

| Area | Reality |
|---|---|
| Tables | `membership_applications`, `membership_application_transitions`, `membership_documents`, `members`. MySQL: transitions FKs/indexes use explicit `mat_*` names; documents composite index is `md_application_type_idx` (Laravel auto names exceed MySQL’s 64-character identifier limit). PHPUnit still uses in-memory SQLite and does not catch this. |
| Public routes | `/keanggotaan`, `/keanggotaan/daftar`, POST same, `/keanggotaan/daftar/terkirim` |
| Admin routes | `/admin/membership/applications`, `/admin/membership/members`, private document download |
| Permissions | Canonical `<domain>.<action>` in `config/acl.php` (nine concrete Membership capabilities; no wildcard) |
| Reserved CMS | `keanggotaan`, `anggota`, `akses` |
| Documents disk | `membership_documents` → `storage/app/private/membership-documents` |

Approval is `App\Actions\Membership\ApproveMembershipApplication`: lock, one Member via `CreateAb2tiMemberFromApplication`, no User, no Spatie `member` role. Status changes go through `MembershipApplicationLifecycle`.

---

## Phase 3A — Membership domain foundation (current reality)

Implemented 2026-08-21. Details: `docs/implementation/phase-3a-membership-domain-foundation.md`.

This is not a second membership system. It hardens the Phase 2A domain so User ≠ Application ≠ Member remains enforceable without expanding 3B/3C/3D.

| Area | Reality |
|---|---|
| Aggregates | `MembershipApplication` (intake) and `Member` (AB2TI Member). No Applicant table. Account link (`members.user_id`) is not created by approval. Staff later grant site access; see Member site-access provisioning |
| Lifecycle | `MembershipApplicationLifecycle` + enum allow-list; invalid transitions rejected. Phase 3C resubmit is `revision_requested → submitted` |
| Member creation | Only `ApproveMembershipApplication` → `CreateAb2tiMemberFromApplication` |
| Identifiers | Opaque unique `reference_code` and technical unique `membership_number`; not sequential IDs; card format still open |
| Secure Capability | Purpose name `membership-application-revision`; platform table implemented in Phase 3C |
| Koperasi | Not in this context |

Public and admin Membership HTTP/UI remain the Phase 2A routes. Phase 3B product-completes the public intake on those routes.

---

## Phase 3B — Public membership application (current reality)

Implemented 2026-08-21. Details: `docs/implementation/phase-3b-public-membership-application.md`.

Same routes and aggregates as Phase 2A. Public submit still creates only a `MembershipApplication` in `submitted`.

| Area | Reality |
|---|---|
| Intake nonce | Session `membership_intake_token` + short cache lock; not a revision/capability token |
| Duplicate policy | Email not unique; accidental same-form repeat POST is blocked; business duplicates deferred |
| Receipt | Session `membership_reference_code` only; not authorization |
| Documents | Optional; private disk; orphan bytes cleaned up if the submit transaction fails |

---

## Phase 3C — Staff review, decision, and applicant revision (current reality)

Implemented 2026-08-21. Details: `docs/implementation/phase-3c-staff-review-decision-revision.md`.

Staff review remains the Phase 2A admin routes. Applicant revision is loginless via Secure Capability, not a second intake and not a Membership edit-token table.

| Area | Reality |
|---|---|
| Queue | Status filter + name/email/reference search; actionable statuses first |
| Decisions | Start review / approve / reject unchanged; request-revision added |
| Resubmit landing | `revision_requested → submitted`; staff must start review again |
| Grant table | `secure_capability_grants` (digest at rest, purpose + target bind, expiry/revoke/consume) |
| Public revision | `/akses/{secret}` redeem → `/akses/revisi`; reserved CMS segment `akses` |
| Delivery | Copy-once staff URL; no email/WhatsApp |
| ACL | Added `membership-applications.request-revision` to `membership-admin` only |

Secure Capability Grant is a **platform** capability (Identity ≠ Applicant ≠ Member ≠ Role ≠ Grant). Domain state still wins.

---

## Phase 3D-A — Membership identity & Kartu Anggota product contract

Documentation only (2026-08-22). **Not implemented.** Details: `docs/discovery/phase-3d-a-membership-identity-card-product-contract.md`.

Kartu Anggota AB2TI implementation and Member Portal are **deferred beyond the initial RC**. Current `membership_number` remains a technical unique string. Current `card_issued` remains a schema flag without an RC staff workflow.

---

## Phase 3E — Membership operational closure for RC

Implemented 2026-08-22. Details: `docs/implementation/phase-3e-membership-rc-closure.md`.

Staff Member registry/detail hardened. Contact correction is the only Member HTTP update. Card checkbox removed from UI. **PHASE 3 MEMBERSHIP: CLOSED FOR RC.**

---

## Phase 4A — Homepage & global public shell (current reality)

Implemented 2026-08-22. Details: `docs/implementation/phase-4a-homepage-public-shell.md`.

Not a new bounded context. Public IA remains a composition of Membership, Publishing, Knowledge, and CMS.

| Area | Reality |
|---|---|
| Shell | One `FrontendLayout` (skip link, utility topbar, header, `BaseModal` mobile nav, footer) |
| Nav | Unchanged named-route contract; membership header CTA → `/keanggotaan` |
| Homepage | `WelcomeController`; hero CMS; welcome CMS; structural pathways; published previews; membership invite |
| Contact | `config/organization.php` shared as Inertia `organization` |
| Footer | `Layouts/Partials/PublicFooter.vue` |
| SEO | Unchanged `PublicMetadataResolver::forWelcome()` |
| Lapak | Public classified family `/lapak` (Phase 4C.3). Authenticated linked members see **Lapak Saya** (`/akun/lapak`). CMS first segments `lapak` and `akun` remain reserved. |

---

## Phase 4B — Public editorial & knowledge experience (current reality)

Implemented 2026-08-22. Details: `docs/implementation/phase-4b-public-editorial-knowledge-experience.md`.

Publishing and Knowledge remain separate bounded contexts. Public archives/detail now share the Phase 4A shell, `PublicContainer` rhythm, `EditorialContent` rendering, and `PublicMetadataResolver`.

| Area | Reality |
|---|---|
| Publication archive | `/publikasi` and `/{type}` with `q` search (title/excerpt), type routes as the only filters, pagination 12, `published()` only |
| Publication detail | `/{type}/{slug}` + up to 3 related published cards of the same type |
| Knowledge archive | `/teknologi-tani` with `q` search; **no taxonomy** (none exists) |
| Knowledge detail | `/teknologi-tani/{slug}` + up to 3 further-reading published cards |
| Cards | `PublicationViews::publicCard` / `KnowledgeArticleViews::publicCard` (thumb conversion when present) |
| Body | `EditorialContent::render` into `EditorialBody.vue` |
| Search helper | `PublicContentSearch` (shared LIKE constrain; not a new domain) |

---

## Phase 4C.1 — Lapak Anggota domain & admin moderation (current reality)

Implemented 2026-08-22. Details: `docs/implementation/phase-4c1-lapak-domain-admin-foundation.md`.

Classified member-benefit marketplace foundation. Not e-commerce. No public storefront. No member self-service.

| Area | Reality |
|---|---|
| Invariant | One `Member` owns at most one `Lapak` (`lapaks.member_id` unique + `CreateLapak`) |
| Listings | `LapakListing` belongs to `Lapak`, classified by platform `LapakCategory` |
| Moderation | `draft` / `pending_review` / `published` / `rejected` / `suspended` |
| Availability | Listing `available` / `unavailable`, independent of moderation |
| Price | `fixed` / `from` / `negotiable` / `hidden` + optional IDR amount/unit |
| Admin | `/admin/lapak/storefronts`, `/listings`, `/categories` |
| Permissions | `lapak.view`, `lapak.manage`, `lapak.moderate` on existing `membership-admin` |
| Identity | Account ↔ Member link added in Phase 4C.2 (`members.user_id`). No Spatie `member` role |
| Public | Public classified family `/lapak` added in Phase 4C.3. CMS first segments `lapak` and `akun` remain reserved |

---

## Phase 4C.2 — Member account linking & Lapak Saya workspace (current reality)

Implemented 2026-08-22. Details: `docs/implementation/phase-4c2-member-account-linking-lapak-workspace.md`.

Platform identity link plus authenticated owner workspace. Not a membership role. Public storefront is Phase 4C.3.

| Area | Reality |
|---|---|
| Schema | `members.user_id` nullable unique FK → `users` (`nullOnDelete`); `members.linked_at` |
| Cardinality | One User ↔ at most one Member (DB unique on `user_id`) |
| Linking | Staff-issued Secure Capability purpose `member-account-link` at `/akun/tautkan/{secret}` (existing User confirms, or guest activates a new User). Details: `docs/implementation/member-account-provisioning-site-access.md` |
| Workspace | `/akun/lapak` (“Lapak Saya”); owner writes use `ownsLapak` / `ownsListing`, not `lapak.*` |
| Media | Direct upload only; File Manager (`media.*`) remains admin |
| Public | Classified public family `/lapak` (Phase 4C.3) |

---

## Phase 4C.3 — Public Lapak experience (current reality)

Implemented 2026-08-22. Details: `docs/implementation/phase-4c3-public-lapak-experience.md`.

Public classified marketplace for member storefronts and listings. Not e-commerce. No cart, checkout, payment, or orders.

| Area | Reality |
|---|---|
| Routes | `lapak.index` `/lapak`; `lapak.show` `/lapak/{slug}`; `lapak.listings.show` `/lapak/{slug}/produk/{listing}` |
| Eligibility | Reuses `Lapak::publicEligible()` / `LapakListing::publicEligible()`; unavailable listings 404 |
| Search | `PublicLapakSearch`: `q` + `kategori` query, LIKE on public fields only, paginate 12, sort `published_at desc, id desc` |
| Privacy | Public seller identity is Lapak name. Contact is Lapak fields only via `LapakPublicContact`. No Member/User fallback |
| SEO | `PublicMetadataResolver::forPublicInformationalPage` / `forLapak` / `forLapakListing`; canonical strips query |
| Nav / home | `frontendMenus` includes Lapak Anggota; homepage pathway + up to 3 latest public listings |

---

## Pre-4C.4 — Admin shell authorization boundary (current reality)

Implemented 2026-08-23. Details: `docs/implementation/pre-4c4-admin-shell-authorization-boundary-repair.md`.

```text
Authentication != Admin authorization.
Admin shell requires platform-staff authorization.
Administrative operations additionally require ACL/Policy authorization.
Member ownership does not grant admin-shell access.
```

`/dashboard` is a staff-only alias of `/admin/dashboard`. Jetstream `/user/profile` and `/user/api-tokens` remain self-service (not staff-gated). Phase 4C.4 completed this contract’s post-login destination repair without reopening `/dashboard` to non-staff.

---

## Phase 4C.4 — Lapak RC hardening & closure (current reality)

Implemented 2026-08-23. Details: `docs/implementation/phase-4c4-lapak-rc-hardening-closure.md`. Phase 4C is closed as a classified Lapak V1.

```text
PUBLIC   /lapak          classified discovery only
OWNER    /akun/lapak     User → linked Member → own Lapak → own Listings
ADMIN    /dashboard + /admin/*   platform-staff + granular ACL/Policies
PUBLICATION  owner submits → staff moderates → eligibility scopes deliver
TRANSACTION  outside AB2TI
```

| Area | Reality |
|---|---|
| Post-login | Staff → `/admin/dashboard`; linked Member → `/akun/lapak`; other Users → `/` |
| Owner slugs | Owner updates without an explicit slug keep the existing public slug |
| Concurrency | Owner writes `lockForUpdate()` and refuse stale content/availability changes after pending/suspend |
| Contact | `LapakPublicContact` builds `wa.me` / `tel:` / `mailto:` only; no AB2TI contact endpoint |
| i18n nav | ID `Teknologi Tani` / EN `Agricultural Technology` via `$t('Teknologi Tani')` |
| Test runner | `composer test` → `php -d memory_limit=512M vendor/bin/phpunit` (`phpunit.xml` `memory_limit=512M`) |

---

## Phase 4D — Public experience hardening (current reality)

Implemented 2026-08-23. Details: `docs/implementation/phase-4d-public-experience-hardening.md`. Phase 4D is closed as an audit-and-corrective pass. It does not add a business domain.

```text
PUBLIC DISCOVERY  GET /robots.txt + GET /sitemap.xml
ELIGIBILITY       published() / publicEligible() / active CMS pages
SEO TITLES        existing JSON keys via __() after SetLocale
```

| Area | Reality |
|---|---|
| robots.txt | Laravel `PublicDiscoveryController@robots` (static `public/robots.txt` removed). Disallows admin, account, capability, auth, locale-switch, and membership intake prefixes. Points `Sitemap:` at the canonical origin |
| sitemap.xml | `PublicSitemap` lists public hubs plus published Publications/Knowledge, public-eligible Lapak/Listings, and active non-reserved CMS pages. Excludes drafts, workspaces, `/akses`, `/akun`, `/admin`, login, and `/keanggotaan/daftar` |
| Informational SEO titles | Knowledge, Publications, Lapak, and Membership hubs use `__()` / existing keys so English is not hardcoded Indonesian |
| Public CTA hrefs | Homepage Hero/Welcome URLs pass `SafePublicHref` (http/https or same-origin path only) |
| i18n | English public Knowledge copy uses **Agricultural Technology**. Official org name remains Indonesian. Nav key `Teknologi Tani` is unchanged |

---

## Member site-access provisioning (current reality)

Implemented 2026-08-23. Details: `docs/implementation/member-account-provisioning-site-access.md`. Not a Lapak reopen. Not Koperasi or Community.

```text
User  = website authentication identity
Member = verified AB2TI membership identity
User ↔ Member via members.user_id (unique, nullable) + members.linked_at
```

| Area | Reality |
|---|---|
| Principle | `User != Member`. Email/phone match is not authorization and never auto-links |
| Admin | Member detail **Akses Situs**: Belum aktif / undangan aktif / Aktif / kedaluwarsa or dicabut. CTA **Berikan Akses Situs** requires `membership-members.update` |
| Capability | Reuse purpose `member-account-link`, target `member`, TTL 168h, digest-only, sibling revoke, consume on success. Grant `payload` holds intended login email; existing-account invitations also store `intended_user_id` |
| Existing User | Invitation bound to that User’s id. Confirm requires `auth id === intended_user_id`. No silent bind. Email match is not authorization |
| New User | Invitation → password setup → atomic User create + link. Public `/register` remains closed. No `intended_user_id` until the User exists |
| Unlink | Revoke unused invitations only. Breaking an established link is not offered |
| Lapak | Activation does not create a Lapak. An existing Member Lapak becomes owner-accessible after link |

---

## CMS admin surface (current reality)

Stabilized 2026-08-22. Details: `docs/implementation/cms-admin-stabilization-audit.md`.

Admin CMS remains Pages, Block Templates, Heroes, Welcome Section, and File/Media. Split permission groups register only implemented resource verbs so `/create` is not captured as `{id}`. Tiptap (`RichEditor`) remains the editorial editor; Block Templates remain named HTML snippets, not a page builder.

---

## Phase 2B — Publishing (current reality)

Implemented 2026-08-20. Details: `docs/implementation/phase-2b-publishing-editorial-vertical-slice.md`.

| Area | Reality |
|---|---|
| Table | `publications` (not `pages`) |
| Types | `berita`, `kegiatan`, `siaran_pers`, `liputan_media` |
| Lifecycle | `draft` ↔ `published` via named Actions; no scheduling |
| Public routes | `/publikasi`, `/{type}`, `/{type}/{slug}` with type prefixes `berita`, `kegiatan`, `siaran-pers`, `liputan-media` |
| Admin routes | `/admin/publishing/publications` |
| Permissions | `publishing-publications.{view,create,update,publish,delete}` |
| Reserved CMS | `publikasi`, `berita`, `kegiatan`, `siaran-pers`, `liputan-media` |
| Editor | Spatie role `editor` is seeded (CMS + heroes + media + publishing; **not** Membership). Knowledge capabilities were added in Phase 2C. |
| Public metadata | `PublicMetadataResolver::forPublication()` → `PublicMetadataInput` (`ogType: article`) |

Body storage is Tiptap HTML. Write and public read go through `App\Support\Editorial\EditorialContent` (free-form sanitizer `EditorialHtml` + Template Block registry). Cover/social images copy from the File Manager onto Publication Spatie collections `publication-cover` and `social-og`.

---

## Editorial content safety (platform invariant)

Rich editorial features **MUST** use the shared Editorial Content Safety Pipeline (`EditorialContent`). Complex elements **SHOULD** use approved structured Template Blocks and trusted renderers rather than unrestricted raw HTML.

Preference order: (1) standard Tiptap node, (2) existing approved Template Block, (3) new reusable approved Template Block with an explicit registry entry, (4) raw HTML only as an explicit last resort.

Do not loosen global sanitization to admit a new presentation. Unknown `data-block-type` values must not resolve PHP/Vue classes. Details: `docs/implementation/phase-2b1-editorial-content-safety-template-blocks.md`.

---

## Phase 2C — Agricultural Knowledge / Teknologi Tani (current reality)

Implemented 2026-08-20. Details: `docs/implementation/phase-2c-agricultural-knowledge-vertical-slice.md`.

| Area | Reality |
|---|---|
| Table | `knowledge_articles` (not `publications`, not `pages`) |
| Taxonomy | None in V1 |
| Lifecycle | `draft` ↔ `published` via named Actions; no scheduling |
| Public routes | `/teknologi-tani`, `/teknologi-tani/{slug}` |
| Admin routes | `/admin/knowledge/articles` |
| Permissions | `knowledge-articles.{view,create,update,publish,delete}` |
| Reserved CMS | `teknologi-tani` |
| Editor | Spatie role `editor` now includes Knowledge in addition to CMS + heroes + media + publishing; **not** Membership |
| Public metadata | `PublicMetadataResolver::forKnowledgeArticle()` → `PublicMetadataInput` (`ogType: article`) |

Knowledge Article reuses `RichEditor`, Template Blocks, `EditorialContent`, File Manager/Media, and Public Delivery. It does **not** inherit Publication. Cover/social images use Spatie collections `knowledge-cover` and `social-og`.

---

## Phase 2D — Public experience & CMS convergence (current reality)

Implemented 2026-08-21. Details: `docs/implementation/phase-2d-public-experience-cms-convergence.md`.

This is not a new bounded context. Membership, Publishing, Knowledge, and CMS remain separate. Public IA is a composition of those owners.

| Area | Reality |
|---|---|
| Public nav | Named-route contract in `useNavigation().frontendMenus`; consumed by navbar, `BaseModal` mobile nav, and `PublicFooter` |
| Homepage | `WelcomeController`: Hero + Welcome (CMS/hero tables) + structural pathways + latest Publications (limit 3, published) + latest Knowledge (limit 3, published) + latest Lapak listings (limit 3, public-eligible) + Membership CTA to `/keanggotaan`. Empty preview sections remain visible. No Unsplash fallbacks, no Laravel version props, no ServiceEvents |
| CMS public props | `CmsPageViews::publicDetail()` |
| Editorial HTML | Unchanged shared `EditorialContent` pipeline for CMS, Publication, and Knowledge |
| Public metadata | Unchanged `PublicMetadataInput` → `PublicMetadataResolver` |
| Reserved CMS | Existing families plus `kontak` |

---

## Platform error pages and ACL operations (current reality)

Implemented 2026-08-21. Details: `docs/implementation/phase-2e-platform-hardening-operational-readiness.md`.

| Area | Reality |
|---|---|
| HTML errors | Inertia `Errors/Show` for 403, 404, 419 always; 500 when `APP_DEBUG=false` |
| JSON errors | Unchanged framework responses |
| 401 / 422 / 429 / 503 | Framework-appropriate; no dedicated branded pages |
| ACL sync | Additive; `--clean` explicit; `--dry-run` report-only |
| Auth submit buttons | `BaseButton` on login/forgot/reset/confirm/verify/2FA challenge/register and profile save |
| Auth security modals | Jetstream `DialogModal` retained |

---

## Phase 5A — RC readiness (current reality)

Implemented 2026-08-23. Details: `docs/release/rc-readiness.md`. Feature freeze. Not a product phase. Phase 5B rehearsal and runbook: `docs/release/production-release.md`. Production cutover was **not** executed from this workspace.

```text
RC        AB2TI 2.0 public + staff + member workspace
BOOTSTRAP migrate --force → acl:sync → RolesAndPermissionsSeeder (no demo users in production) → ab2ti:provision-operator
QUEUE     none required (sync)
CRON      none required
```

| Area | Reality |
|---|---|
| Timezone | `Asia/Jakarta` |
| Demo seed users | Development/testing only; skipped when `APP_ENV=production` |
| First operator | `php artisan ab2ti:provision-operator {email} --password=` (no repository default password) |
| HTTP route cache | Production routes are controller actions; PHPUnit error routes exist only during tests |
| Decision | RC approved with documented non-blocking deviations |

---

## Phase 5B — production cutover (current reality)

Prepared 2026-08-23. Runbook: `docs/release/production-release.md`. Checklist: `docs/release/production-cutover-checklist.md`. Feature freeze. Not a product phase. Production was not mutated from this environment.

```text
RELEASE   AB2TI 2.0 RC (working tree on feat/pre-phase-2a-b-ui-ux; HEAD 38c952a is foundation-only)
STATUS    READY FOR OPERATOR CUTOVER — PRODUCTION EXECUTION NOT PERFORMED
BACKUP    operator-owned; required before migrate --force
```

| Area | Reality |
|---|---|
| Production access | Not available in this workspace |
| Hosting model | Not defined in-repo (in-place or release-directory + symlink) |
| Rehearsal | Isolated SQLite: 24 migrations, `acl:sync` 40, production seeder, `ab2ti:provision-operator`, Laravel caches |
| Queue / cron | None required |
| Content | `/about` and `/contact` remain CMS-backed; 404 until published |
