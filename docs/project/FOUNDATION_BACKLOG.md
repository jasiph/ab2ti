# AB2TI 2.0 Foundation Backlog

Residual items from Phase 0A. Phase 0B classified them. Phase 0C-A resolved the security/test/build subset below. History is retained.

Priority key:

- **P0** — Foundation blocker, security, or correctness before further product work is safe
- **P1** — Must resolve before the relevant AB2TI module or public IA work
- **P2** — Cleanup / maintainability
- **DEFERRED** — Needs an explicit product or information-architecture decision
- **RESOLVED** — Completed in a named phase; kept for audit

---

---

## 1. `/sermons` and `/events` CMS aliases

| Field | Content |
|---|---|
| Priority | **DEFERRED** (IA) / becomes **P1** before public URL redesign |
| Issue | `routes/web.php` maps `/sermons` and `/events` to CMS pages with those slugs. Public nav labels them “Farming Tech” and “News”. Footer still links `/sermons`. |
| Evidence | `routes/web.php`, `useNavigation.js`, `FooterSection.vue`, `lang/*/sources/menu.json` |
| Risk | Confusing IA; accidental breakage if renamed without redirects |
| Recommended phase | After Constitution; with Information Architecture, not during hardening of auth |
| Decision needed | Keep aliases, add redirects from better URLs, or replace once page slugs exist |
| Phase 0B | Not changed |
| Phase 0C-A | Not changed |
| Phase 0C-B | **DEFERRED TO PHASE 1 INFORMATION ARCHITECTURE.** Not renamed, redirected, or relabeled. Tests still cover `/sermons` and `/events`. |

## 2. `culture.*` / `artikel.*` permissions

| Field | Content |
|---|---|
| Priority | **RESOLVED** (0C-B) |
| Issue | `config/acl.php` defined `culture.*` and `artikel.*` with no matching modules. |
| Previous condition | Empty permission groups in the Role UI catalog. |
| Resolution | Groups removed from `config/acl.php`. Seeder no longer assigns them. `acl:sync` was **not** run against the active development database; stale rows MAY remain until an operator runs sync `--clean` on a DB they maintain. |
| Phase 0B | Not changed |
| Phase 0C-A | Not changed (no unused-group trim) |
| Phase 0C-B | Resolved in config only |

## 3. Server-side authorization incomplete / UI-driven

| Field | Content |
|---|---|
| Priority | **RESOLVED** (0C-A) |
| Issue | `/admin/*` was `auth` + `verified` only. Spatie permissions filtered Vue menus. Any authenticated user who knew a URL could hit admin controllers. |
| Evidence | Previous: `routes/web.php` without `permission:` middleware. Current: Spatie `permission:` middleware on admin module routes; `bootstrap/app.php` aliases; `tests/Feature/Admin/AdminAuthorizationTest.php`. |
| Risk | Privilege escalation (mitigated for mapped admin modules). Dashboard remains auth-only by design. |
| Previous condition | UI-only authorization; nav used `files.view` / `settings.view` which were not in `acl.php`. |
| Resolution | Route-level Spatie `permission:` checks using existing `config/acl.php` names (`users.*`, `roles.manage`, `media.*`, `heroes.*`, `cms.*`). Nav aligned to those names. `access settings` still unused. |
| Validation | Feature tests: guest redirect, 403 without permission, 200/redirect with permission, super-admin bypass, one read + one mutation per major module class. In-memory SQLite (`RefreshDatabase`). `acl:sync` was **not** run on the active development database. |
| Phase 0B | Not changed |
| Phase 0C-A | Resolved |

## 4. Public registration / `MustVerifyEmail`

| Field | Content |
|---|---|
| Priority | Registration: **RESOLVED** (0C-A). Email verification: **DEFERRED** |
| Issue | Fortify registration was enabled. `User` does not implement `MustVerifyEmail`, so `verified` middleware did not require verification. |
| Evidence | `config/fortify.php` (registration feature removed). ADR `0002-disable-public-registration.md`. `User` still does not implement `MustVerifyEmail`. Admin routes no longer attach `verified`. |
| Risk | Open sign-up (mitigated). Misleading verification (mitigated on admin routes; MustVerifyEmail still deferred). |
| Previous condition | Public GET/POST `/register` enabled; `verified` middleware present but ineffective. |
| Resolution | Public self-registration disabled via Fortify. Public register links hidden unless `canRegister`. Ineffective `verified` middleware removed from dashboard/admin groups. `MustVerifyEmail` **not** implemented. |
| Validation | `RegistrationTest` (disabled branch, including POST `/register` 404). Login and password-reset tests remain. |
| Phase 0B | Not changed |
| Phase 0C-A | Registration resolved; MustVerifyEmail still deferred |
| Phase 0C-B | Fortify email-verification **feature disabled** (no active behavior). MustVerifyEmail still **not** implemented. |

## 5. `/{slug}` CMS catch-all

| Field | Content |
|---|---|
| Priority | **RESOLVED** (0C-B) |
| Issue | Catch-all used `where('slug', '.*')` and could swallow system first segments. |
| Previous condition | GET `/register` hit CMS catch-all; reserved namespaces were not validated on Page save. |
| Resolution | Canonical reserved list in `config/cms.php`; `App\Support\CmsPath` used by route constraint and Page validation (ADR 0003). Nested slugs remain allowed. |
| Validation | `tests/Feature/Cms/CmsRoutingTest.php`, `tests/Unit/CmsPathTest.php`. |
| Phase 0B | Not changed |
| Phase 0C-A | Not changed |
| Phase 0C-B | Resolved |

## 6. FileManager restore / forceDelete failures

| Field | Content |
|---|---|
| Priority | **RESOLVED** (0C-A) |
| Issue | Routes `admin.files.restore` and `admin.files.force-delete` existed. `FileManagerController` had no `restore`/`forceDelete`. Feature tests failed. |
| Evidence | Controller methods now present; `File` uses SoftDeletes + Spatie Media. |
| Previous condition | Missing controller methods; 500s. |
| Resolution | Restore only trashed records; force-delete only trashed records; Spatie `forceDelete` cleans media; restore does not duplicate media. Authorized with `media.update` / `media.delete`. |
| Validation | `tests/Feature/Admin/FileManagerTest.php` on in-memory SQLite. |
| Phase 0B | Not changed |
| Phase 0C-A | Resolved |

## 7. Hero media collection

| Field | Content |
|---|---|
| Priority | **RESOLVED** (0C-B) |
| Issue | `Hero::registerMediaCollections()` called `registerMediaConversions()` instead of declaring `hero-backgrounds`. |
| Previous condition | Collection not explicitly registered. |
| Resolution | `addMediaCollection('hero-backgrounds')`; conversions stay in `registerMediaConversions`. Multiple backgrounds. Hero media deletes are scoped to that hero. |
| Validation | `tests/Feature/Admin/HeroMediaTest.php`. |
| Phase 0B | Not changed |
| Phase 0C-A | Not changed |
| Phase 0C-B | Resolved |

## 8. `npm run build` script issue

| Field | Content |
|---|---|
| Priority | **RESOLVED** (0C-A) for the npm script. SSR remains out of scope. |
| Issue | `package.json` `"build"` repeated `vite build` / `--ssr` flags. `npx vite build` worked. `composer setup` invokes `npm run build`. |
| Previous condition | Broken npm build script. |
| Resolution | `"build": "vite build"` (client production build only). No SSR introduced. |
| Validation | `npm run build`. |
| Phase 0B | Not changed |
| Phase 0C-A | npm script resolved; `composer setup` still ran `migrate --force` |
| Phase 0C-B | `composer setup` uses `php artisan migrate` **without** `--force`. Unused `@tailwindcss/vite` removed. |

## 9. Historical church tables in developer DB

| Field | Content |
|---|---|
| Priority | **RESOLVED** (0C-B documentation). Leftover tables themselves are not dropped. |
| Issue | Phase 0A deleted church *migrations*. Databases migrated earlier MAY still have leftover tables. |
| Resolution | Documented in `docs/project/LOCAL_DATABASE_RECOVERY.md`. No automatic DROP. Preferred path: new disposable DB + canonical migrate. |
| Phase 0B | Not changed; active DB not inspected destructively |
| Phase 0C-A | Not changed; active DB not modified |
| Phase 0C-B | Procedure documented; **no** active DB mutation |

## 10. `BaseSearchSelect` / `DashboardStatCard` design-system status

| Field | Content |
|---|---|
| Priority | **DEFERRED UNTIL REAL AB2TI USE CASE** |
| Issue | Both components remain; no callers after Phase 0A. Not promoted to the design system. Not removed. |
| Phase 0B | Not changed |
| Phase 0C-A | Not changed |
| Phase 0C-B | Confirmed still unused; deferred |

## 11. `bacon/bacon-qr-code` dependency status

| Field | Content |
|---|---|
| Priority | **RESOLVED** (0C-B — keep) |
| Issue | Appeared leftover after letter/QR domain removal. |
| Resolution | `composer why bacon/bacon-qr-code` → **transitive** via `laravel/fortify` (2FA QR). **Kept.** Not a direct require. |
| Phase 0B | Not changed |
| Phase 0C-A | Not changed |
| Phase 0C-B | Kept |

## 12. Remaining Laravel / Jetstream placeholder branding

| Field | Content |
|---|---|
| Priority | **RESOLVED** (0C-B) for application chrome defaults |
| Issue | Default app name / Inertia title fallback said “Laravel”; unused Jetstream Welcome component had marketing copy. |
| Resolution | `.env.example` `APP_NAME="AB2TI 2.0"`; `config/app.php` default; Inertia title fallbacks; `app.blade.php`; unused `Welcome.vue` heading/intro. No UI redesign. README remains Laravel skeleton (not app chrome). |
| Phase 0B | Not changed |
| Phase 0C-A | Not changed |
| Phase 0C-B | Application identity defaults updated |

---

## Additional verified items (not in the original 12, still backlog)

These were confirmed in the Architecture Baseline and SHOULD be handled with the numbered items, not forgotten:

- ACL vs nav permission name drift — **aligned in 0C-A**. `access settings` still unused (**P2**, not a foundation blocker).
- `ExampleTest` GET `/` — **resolved in 0C-A**.
- `HandleInertiaRequests` dotted `auth.user.roles` keys — still open (**P2**, Jetstream also shares `auth.user`).
- Dual Jetstream vs Base button/modal families — **P2** with unused Base candidates; not a foundation blocker.
- Tailwind v3 vs `@tailwindcss/vite` v4 — **removed unused package in 0C-B**.
- `composer setup` `--force` — **removed in 0C-B**.

---

## Remaining after Phase 0C-B (intentional deferrals)

Foundation hardening 0C-A + 0C-B is complete. Remaining items need Phase 1 product/IA knowledge:

1. `/sermons` and `/events` information architecture.
2. `MustVerifyEmail` product decision (feature flag is off; do not implement until decided).
3. `BaseSearchSelect` / `DashboardStatCard` — wait for a real AB2TI use case.
4. Optional later: `access settings` unused permission, dotted Inertia auth keys, Jetstream vs Base component duplication, Laravel skeleton README.
