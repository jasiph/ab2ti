# AB2TI 2.0 Architecture Baseline

This document describes **current reality** after Phase 0A (legacy Presbi/church domain removal). It is not a target architecture.

Captured from repository inspection on 2026-08-19.

Source commit: `09a69e691bc646405e21f1f4c4fb35876cffef27`

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
| `@tailwindcss/vite` | `^4.0.0` | **not wired** in `vite.config.js` |
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
- `Http/Middleware` (`SetLocale`, `HandleInertiaRequests`)
- `Models` (6): `User`, `Page`, `BlockTemplate`, `Hero`, `WelcomeSection`, `File`
- `Actions/Fortify` and `Actions/Jetstream` (auth only)
- `Providers`: `AppServiceProvider`, `FortifyServiceProvider`, `JetstreamServiceProvider`
- `Console/Commands`: `acl:sync`, `lang:merge`

Not present:

- `app/Http/Requests`
- `app/Policies`
- `app/Services`
- `app/Enums`, `app/Repositories`, `app/Observers`

Dominant pattern: **route → controller → inline `$request->validate()` → Eloquent (+ Spatie Media) → `Inertia::render()`**.

Admin controllers are resource-style CRUD. There is no domain service/action layer beyond Fortify/Jetstream.

---

## Authentication

- **Fortify** guard `web`; Jetstream stack `inertia`; Jetstream guard `sanctum`.
- Features enabled: registration, password reset, email verification (Fortify feature flag), profile/password update, two-factor authentication, API tokens, account deletion.
- Features disabled: Jetstream teams, Jetstream profile photos, terms/privacy feature flag.
- `User` does **not** implement `MustVerifyEmail` (commented import). Admin/dashboard routes use middleware `verified`, which is a no-op unless the user implements `MustVerifyEmail`.
- Login rate limit: 5/minute (Fortify).
- Session driver in `.env.example`: database; `SESSION_ENCRYPT=false`.

This is session SPA authentication, not a token-first public API. `routes/api.php` only exposes `GET /api/user` behind `auth:sanctum`.

---

## Authorization

Spatie Permission is installed. Permissions are defined in `config/acl.php` and synced with `php artisan acl:sync`.

Current groups: `users`, `roles`, `cms`, `settings`, `culture`, `media`, `heroes`, `artikel`.

`Gate::before` grants every ability to role `super-admin`.

**Limitation (verified):** `/admin/*` is protected by `auth:sanctum` + Jetstream session + `verified` only. Controllers do not call policies or `$this->authorize()`. Vue `usePermission` / `useNavigation` hide menus. **Authorization is primarily UI visibility, not server-side enforcement.**

Additional drift: navigation uses permission names that are not in `acl.php` (`files.view`, `settings.view`). ACL has `media.view` and `access settings`. `culture.*` and `artikel.*` have no corresponding modules.

---

## Frontend

Inertia Vue 3 SPA.

- Entry: `resources/js/app.js` (client), `resources/js/ssr.js` (SSR entry exists; i18n is not wired in SSR).
- Pages resolved from `resources/js/Pages/**/*.vue`.
- Ziggy for named routes.
- Composables: `usePermission`, `useNavigation`, `useConfirm`, `useUndoToast`.
- No Pinia/Vuex stores. No TypeScript.

Shared Inertia props (`HandleInertiaRequests`): `locale`, flash (`success`/`error`/`warning`/`info`), Ziggy, plus PHP keys `auth.user.roles` and `auth.user.permissions` (literal dotted keys; Jetstream also shares `auth.user`).

---

## Layout System

| Layout | Role | Status |
|---|---|---|
| `AppLayout.vue` | Authenticated admin (topbar, sidebar, toast, confirm) | Canonical for admin pages |
| `FrontendLayout.vue` | Public site | Canonical for public pages |
| Auth cards (`AuthenticationCard`) | Login/register/password/2FA | Canonical for Fortify views |
| `GuestLayout.vue` | Minimal wrapper | Present; not the auth page wrapper |
| `WebLayout.vue` | Alternate public/app hybrid | Present; not used by current pages |

---

## Design-System Candidates

Theme tokens live in `resources/css/app.css` (AB2TI green/gold/terracotta + dark mode) and `tailwind.config.js` semantic colors (`background`, `surface`, `primary`, `secondary`, `accent`, `text`, `border`).

`resources/js/Components/Base/`:

| Component | Status |
|---|---|
| `BaseButton` | Used |
| `BaseCard` | Used |
| `BaseInput` | Used |
| `BaseSelect` | Used |
| `BaseList` | Used |
| `BaseModal` | Used |
| `BasePagination` | Used |
| `BaseSearchSelect` | Present; **no remaining callers** after Phase 0A |
| `DashboardStatCard` | Present under `Components/`; **no remaining callers** after dashboard neutralization |

Jetstream leftovers still exist alongside Base: `PrimaryButton`, `SecondaryButton`, `DangerButton`, `Modal`, `DialogModal`, `ConfirmationModal`.

`BaseSearchSelect` and `DashboardStatCard` are **not** declared canonical. They are unused candidates.

---

## CMS

- `pages`: slug (unique), HTML `content`, `meta_title`, `meta_description`, JSON `settings`, `is_active`, optional Spatie cover collection `page-cover`.
- `block_templates`: reusable HTML snippets for Tiptap (`html_content`, `is_active`).
- `heroes`: landing hero + Spatie collection `hero-backgrounds` (collection registration is incomplete; conversions exist).
- `welcome_sections`: landing welcome copy/CTA/`image_url`.
- Public renderer: `PageController@show` → `Frontend/Page/Show.vue` (`v-html` of `page.content`).
- Shortcuts: `/about`, `/sermons`, `/events`, `/contact` map to page slugs of the same name.
- Catch-all: `GET /{slug}` with `where('slug', '.*')` registered **last** in `routes/web.php`.

There is no posts/news/tags/menu CMS, no scheduling, no draft workflow beyond `is_active`.

---

## Media / Files

- `files` table + Spatie `media` (polymorphic).
- `File` model: collection `default`, conversion `thumb` (300×300, non-queued), soft deletes.
- `FileManagerController`: index/create/store/destroy/getJson/storeJson.
- Routes exist for `restore` and `forceDelete`; **controller methods are missing**. Tests covering those methods fail.
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

- Jetstream/Fortify feature tests (auth, profile, password, 2FA, API tokens, registration, browser sessions, account deletion)
- `tests/Feature/Admin/FileManagerTest.php`
- Placeholder `ExampleTest` (unit + feature GET `/`)

Known failures (Phase 0A, pre-existing relative to this governance phase):

1. `FileManagerTest` restore / force-delete — missing controller methods.
2. `ExampleTest` GET `/` — no `RefreshDatabase`; query hits `heroes` on empty in-memory DB.

No Pest. No frontend/browser tests. No CMS/authorization tests.

Quality commands that actually exist:

- `composer test` → `php artisan config:clear` + `php artisan test`
- `npm run dev` → `vite`
- `npx vite build` — verified working in Phase 0A
- `npm run build` — **broken** (repeated `vite build` / `--ssr` flags)

---

## Build Pipeline

| Command | Reality |
|---|---|
| `composer dev` | `artisan serve` + queue listen + pail + `npm run dev` |
| `composer setup` | install, copy `.env`, `key:generate`, **`migrate --force`**, `npm install`, `npm run build` |
| `npm run build` | Corrupted script; do not treat as the production build |
| `npx vite build` | Client production build works |
| CI/CD | None (no `.github/workflows`, no GitLab CI) |
| Docker | Sail package only; no compose file |

README is still the default Laravel skeleton README.

---

## Known Technical Debt

Verified from source and Phase 0A validation. Not a wish list.

1. Admin authorization is UI-only.
2. Public registration is enabled.
3. `MustVerifyEmail` is not implemented; `verified` middleware is ineffective.
4. CMS catch-all `/{slug}` uses `.*`.
5. `/sermons` and `/events` are leftover CMS aliases.
6. ACL vs navigation permission name mismatch (`files.view` / `settings.view`).
7. `culture.*` and `artikel.*` permissions have no modules.
8. FileManager restore/forceDelete routes without methods; tests fail.
9. Hero media collection registration is incorrect (`registerMediaCollections` calls conversions).
10. `npm run build` is unusable; `composer setup` depends on it and on `migrate --force`.
11. `HandleInertiaRequests` shares dotted `auth.user.*` keys.
12. Dual button/modal component families (Jetstream vs Base).
13. Tailwind v3 runtime vs unused `@tailwindcss/vite` v4 dependency.
14. `bacon/bacon-qr-code` remains in the lockfile after letter-domain removal (transitive; not a direct `composer.json` require).
15. Historical church tables may remain in developer databases.
16. `BaseSearchSelect` / `DashboardStatCard` unused.
17. Default app name / some Jetstream copy still say “Laravel”.
18. No Form Requests, no Policies, no project README, no CI.

See `docs/project/FOUNDATION_BACKLOG.md` for classification and recommended hardening order.
