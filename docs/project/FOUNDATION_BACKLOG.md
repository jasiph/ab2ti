# AB2TI 2.0 Foundation Backlog

Residual items carried from Phase 0A. **None of these are being resolved in Phase 0B.** Phase 0B only classifies them.

Priority key:

- **P0** — Foundation blocker, security, or correctness before further product work is safe
- **P1** — Must resolve before the relevant AB2TI module or public IA work
- **P2** — Cleanup / maintainability
- **DEFERRED** — Needs an explicit product or information-architecture decision

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

## 3. Server-side authorization incomplete / UI-driven

| Field | Content |
|---|---|
| Priority | **P0** |
| Issue | `/admin/*` is `auth` + `verified` only. Spatie permissions filter Vue menus. Any authenticated user who knows a URL can hit admin controllers. |
| Evidence | `routes/web.php`; no `app/Policies`; no `$this->authorize()` in admin controllers; `usePermission.js` |
| Risk | Privilege escalation; public registration makes this worse |
| Recommended phase | **0C first** |
| Decision needed | Permission middleware vs policies vs both; mapping of `files.view` vs `media.view` |
| Phase 0B | Not changed |

## 4. Public registration / `MustVerifyEmail`

| Field | Content |
|---|---|
| Priority | Registration: **P0**. Email verification: **DEFERRED** product flag, **P1** once registration policy is known |
| Issue | Fortify registration is enabled. `User` does not implement `MustVerifyEmail`, so `verified` middleware does not require verification. |
| Evidence | `config/fortify.php`; `app/Models/User.php`; `routes/web.php` middleware `verified` |
| Risk | Open sign-up to an admin CMS; false sense of verification |
| Recommended phase | 0C (registration policy) then verification behavior |
| Decision needed | Invite-only vs open register; whether email verification is required |
| Phase 0B | Not changed |

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

## 6. FileManager restore / forceDelete failures

| Field | Content |
|---|---|
| Priority | **P0** (correctness — routes and tests disagree with controller) |
| Issue | Routes `admin.files.restore` and `admin.files.force-delete` exist. `FileManagerController` has no `restore`/`forceDelete`. Feature tests fail. |
| Evidence | `routes/web.php`; `FileManagerController`; `tests/Feature/Admin/FileManagerTest.php` |
| Risk | 500s in UI; red test suite hides later regressions |
| Recommended phase | 0C |
| Decision needed | Implement methods vs remove routes/tests |
| Phase 0B | Not changed |

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

## 8. `npm run build` script issue

| Field | Content |
|---|---|
| Priority | **P0** (build/deploy correctness) |
| Issue | `package.json` `"build"` repeats `vite build` / `--ssr` flags. `npx vite build` works. `composer setup` invokes `npm run build`. |
| Evidence | `package.json`; Phase 0A `npx vite build` PASS |
| Risk | Production/setup builds fail or thrash |
| Recommended phase | 0C |
| Decision needed | Client-only vs real SSR pipeline (SSR entry exists but i18n is not wired) |
| Phase 0B | Not changed |

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

---

## Additional verified items (not in the original 12, still backlog)

These were confirmed in the Architecture Baseline and SHOULD be handled with the numbered items, not forgotten:

- ACL vs nav permission name drift (`files.view` vs `media.view`, `settings.view` vs `access settings`) — attach to **item 3**.
- `ExampleTest` GET `/` fails without `RefreshDatabase` — attach to **item 6** quality work or a small test-hardening task in 0C.
- `HandleInertiaRequests` dotted `auth.user.roles` keys — attach to **item 3**.
- Dual Jetstream vs Base button/modal families — **P2** with item 10.
- Tailwind v3 vs unused `@tailwindcss/vite` v4 — **P2** with item 8.
- `composer setup` runs `migrate --force` — **P0** operational hazard; address with item 8 / docs, never by running it unasked.

---

## Recommended Phase 0C order (Foundation Hardening)

Do **not** start 0C in this phase. Proposed order:

1. **Server-side authorization** for all `/admin` mutating (and sensitive read) routes; align ACL names with navigation (items 3, 2 as encountered).
2. **Registration policy** (item 4): disable or gate sign-up per product decision; then `MustVerifyEmail` if required.
3. **File Manager restore/forceDelete** implement or remove; make related tests honest (item 6).
4. **Repair `npm run build`** (or stop calling it from `composer setup`); decide SSR yes/no (item 8).
5. **Catch-all slug constraint** and reserved paths (item 5).
6. **Hero media collection** fix (item 7).
7. **Document leftover-table procedure** for dirty developer DBs (item 9) — no silent DROP.
8. **Dependency audit** bacon-qr-code (item 11); Tailwind vite plugin mismatch.
9. **Branding/env example** (item 12).
10. **IA decision ticket** for `/sermons` `/events` (item 1) — do not rename until decided.
11. **Unused Base components** stay until a real use case (item 10).

Product/IA decisions (registration, verification, public URLs) MUST be recorded as ADRs when made.
