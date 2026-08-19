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

## 2. `culture.*` / `artikel.*` permissions

| Field | Content |
|---|---|
| Priority | **P1** before any ACL/roles product work |
| Issue | `config/acl.php` defines `culture.*` and `artikel.*`. No matching modules, routes, or nav entries remain. |
| Evidence | `config/acl.php`; no controllers/pages for those names |
| Risk | Role UI exposes empty permission groups; future modules may reuse names incorrectly |
| Recommended phase | 0C hardening (trim unused groups) **or** first module that actually needs them |
| Decision needed | Delete unused groups vs reserve names for AB2TI content types |
| Phase 0B | Not changed |
| Phase 0C-A | Not changed (no unused-group trim) |

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

## 5. `/{slug}` CMS catch-all

| Field | Content |
|---|---|
| Priority | **P1** (correctness/routing); treat as **P0** if new public routes are added before it is tightened |
| Issue | `Route::get('/{slug}')` with `where('slug', '.*')` is last but overly greedy (nested paths). |
| Evidence | `routes/web.php` |
| Risk | Future routes or static paths can be swallowed; harder-to-reason 404 vs CMS |
| Recommended phase | 0C routing hardening, before new public modules |
| Decision needed | Allowed slug pattern; reserved path denylist |
| Phase 0B | Not changed |
| Phase 0C-A | Not changed |

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
| Priority | **P1** before hero/landing content work |
| Issue | `Hero::registerMediaCollections()` calls `registerMediaConversions()` instead of declaring `hero-backgrounds`. Landing and admin still use that collection name. |
| Evidence | `app/Models/Hero.php`; `routes/web.php` welcome closure; `HeroController` |
| Risk | Missing conversions/collections; fragile media copies |
| Recommended phase | 0C media hardening |
| Decision needed | None beyond fixing collection registration |
| Phase 0B | Not changed |
| Phase 0C-A | Not changed |

## 8. `npm run build` script issue

| Field | Content |
|---|---|
| Priority | **RESOLVED** (0C-A) for the npm script. SSR remains out of scope. |
| Issue | `package.json` `"build"` repeated `vite build` / `--ssr` flags. `npx vite build` worked. `composer setup` invokes `npm run build`. |
| Previous condition | Broken npm build script. |
| Resolution | `"build": "vite build"` (client production build only). No SSR introduced. Unused `@tailwindcss/vite` not addressed. |
| Validation | `npm run build` in this phase. |
| Phase 0B | Not changed |
| Phase 0C-A | npm script resolved; `composer setup` still runs `migrate --force` (do not run unasked) |

## 9. Historical church tables in developer DB

| Field | Content |
|---|---|
| Priority | **P1** before the next schema change on a dirty local DB; never a silent DROP |
| Issue | Phase 0A deleted church *migrations*. Databases migrated earlier MAY still have `congregations`, `letters`, `songs`, etc. |
| Evidence | Phase 0A report; Constitution §5; no DROP was run |
| Risk | Confusion (“schema drift”); accidental reuse; unsafe manual drops |
| Recommended phase | 0C documented leftover-table procedure (opt-in, disposable DB only unless operator consents) |
| Decision needed | Ignore leftovers vs operator-run cleanup script vs documented SQL for volunteers |
| Phase 0B | Not changed; active DB not inspected destructively |
| Phase 0C-A | Not changed; active DB not modified |

## 10. `BaseSearchSelect` / `DashboardStatCard` design-system status

| Field | Content |
|---|---|
| Priority | **P2** |
| Issue | Both components remain; no callers after Phase 0A. Constitution forbids auto-promoting them. |
| Evidence | `resources/js/Components/Base/BaseSearchSelect.vue`; `resources/js/Components/DashboardStatCard.vue` |
| Risk | Dead code vs premature deletion of useful primitives |
| Recommended phase | When an AB2TI UI task would reuse them, or a dedicated UI consolidation task |
| Decision needed | Keep as candidates vs delete in a chore |
| Phase 0B | Not changed |
| Phase 0C-A | Not changed |

## 11. `bacon/bacon-qr-code` dependency status

| Field | Content |
|---|---|
| Priority | **P2** |
| Issue | Letter/QR code domain was removed. Package is not a direct `composer.json` require; it remains in the lockfile as a leftover transitive. Constitution forbids removing it in 0B. |
| Evidence | `composer.lock`; no `QrCodeService` |
| Risk | Unused code in vendor; noise |
| Recommended phase | 0C dependency audit (`composer why bacon/bacon-qr-code`) then remove only if unused |
| Decision needed | Confirm no remaining transitive need |
| Phase 0B | Not changed |
| Phase 0C-A | Not changed |

## 12. Remaining Laravel / Jetstream placeholder branding

| Field | Content |
|---|---|
| Priority | **P2** |
| Issue | `VITE_APP_NAME` / `APP_NAME` default “Laravel”; `Components/Welcome.vue` Jetstream marketing copy; Inertia title fallback. Phase 0A already replaced PresbiApp/GoldenApp chrome with “AB2TI 2.0”. |
| Evidence | `.env.example`, `resources/js/app.js`, `resources/js/ssr.js`, `Components/Welcome.vue` |
| Risk | Public/admin chrome still says Laravel if env is default |
| Recommended phase | 0C copy/env example, or first branding pass |
| Decision needed | Official public site name string |
| Phase 0B | Not changed |
| Phase 0C-A | Not changed |

---

## Additional verified items (not in the original 12, still backlog)

These were confirmed in the Architecture Baseline and SHOULD be handled with the numbered items, not forgotten:

- ACL vs nav permission name drift (`files.view` vs `media.view`, `settings.view` vs `access settings`) — **aligned in 0C-A** to `media.view` / `cms.view` / auth-only profile. `access settings` still unused.
- `ExampleTest` GET `/` — **resolved in 0C-A** (`RefreshDatabase`).
- `HandleInertiaRequests` dotted `auth.user.roles` keys — still open (**P2**).
- Dual Jetstream vs Base button/modal families — **P2** with item 10.
- Tailwind v3 vs unused `@tailwindcss/vite` v4 — **P2** with remaining item 8/11 work.
- `composer setup` runs `migrate --force` — operational hazard; never run unasked.

---

## Recommended Phase 0C-B order (remaining Foundation Hardening)

0C-A completed items 3, 4 (registration), 6, 8 (npm script), and ExampleTest isolation.

1. **Catch-all slug constraint** and reserved paths (item 5).
2. **Hero media collection** fix (item 7).
3. **Document leftover-table procedure** for dirty developer DBs (item 9) — no silent DROP.
4. **Unused ACL groups** `culture.*` / `artikel.*` (item 2) if still unused.
5. **Dependency audit** bacon-qr-code (item 11); Tailwind vite plugin mismatch.
6. **Branding/env example** (item 12).
7. **IA decision ticket** for `/sermons` `/events` (item 1) — do not rename until decided.
8. **Unused Base components** stay until a real use case (item 10).
9. **MustVerifyEmail** remains deferred until a product decision.

Product/IA decisions (verification, public URLs) MUST be recorded as ADRs when made.
