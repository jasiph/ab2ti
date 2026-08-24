# AB2TI 2.0 — Production release runbook

**Release identifier:** AB2TI 2.0 RC  
**Phase:** 5B runbook + immutable RC snapshot (2026-08-24)  
**Date of this runbook:** 2026-08-24  
**Deployment outcome:** **PRODUCTION EXECUTION NOT PERFORMED**

This is the operator cutover runbook. It is not a record of a live production deploy.

Never put secrets, passwords, API tokens, or database credentials in this file.

---

## 1. Release identifier / commit

| Item | Value |
|---|---|
| Product label | **AB2TI 2.0 RC** |
| Git branch | `feat/pre-phase-2a-b-ui-ux` |
| Foundation parent | `38c952afee952a05c3d09f160b9bf789b1ab0c06` (`docs: finalize AB2TI foundation baseline`) — **not** the RC |
| Canonical RC | Git tag **`ab2ti-2.0-rc`** on the `release: AB2TI 2.0 RC` commit. Resolve SHA with `git rev-parse ab2ti-2.0-rc` |
| Bootstrap hotfix | Included (`bootstrap/app.php` does not call `config()` inside `withMiddleware()`; `TrustProxies::at()` in `AppServiceProvider::boot()`; opt-in `TRUSTED_PROXIES` only) |
| Remote | `ab2ti` → `git@github.com:jasiph/ab2ti.git` — **not pushed** by this finalization |
| Tag | Local annotated `ab2ti-2.0-rc` (do not push unless authorized) |

The immutable RC is that tagged commit (Phases 2–5B working tree + bootstrap hotfix). Do not describe the foundation SHA as the release revision.

**Do not ship:**

| Path | Why |
|---|---|
| `.env` | Secrets; never copy the development file onto production |
| `storage/media-library/` | Local media conversions |
| Isolated rehearsal SQLite | Development-only |
| Accidental pager dump (`hip_application_transitions_table')`) | Removed during RC finalization; never application source |

---

## 2. Phase 5A RC baseline

| Check | Result |
|---|---|
| Phase 5A | PASS WITH DOCUMENTED DEVIATION; RC APPROVED |
| `composer test` | PHPUnit 11.5.50 — **accepted RC baseline after bootstrap hotfix: Tests 459, Assertions 4238, Skipped 5** (supersedes 457 / 4232) |
| `npm run build` | PASS (Vite 7.3.1) |
| `vendor/bin/pint --dirty` | PASS |
| `vendor/bin/pint --test` | FAIL on 23 historical files (non-runtime; do not mass-format) |
| Isolated migrate | 24 migrations PASS |
| `acl:sync` | 40 canonical permissions PASS |
| Laravel | 12.49.0 |
| PHP | workspace 8.4.1; Composer constraint `^8.2` |
| Node / npm | 22.16.0 / 10.9.2 |
| Timezone | `Asia/Jakarta` |
| Production DB target | MySQL/MariaDB |
| RC blockers | NONE |

Canonical RC document: `docs/release/rc-readiness.md`.

---

## 3. Deployment architecture

**Unknown in this environment.** The repository has no Forge/Ploi/Envoy/Docker/CI deploy definition. Phase 5B does not invent one.

Use whichever model the operator already has.

**A. In-place on one host** (document root = this tree’s `public/`):

1. `php artisan down` if the site already receives traffic.
2. Update files, install PHP deps, build or unpack assets.
3. Migrate, ACL, caches, `php artisan up`.

**B. Release directory + symlink** (preferred when already operated that way):

1. Build the new release in a sibling directory.
2. Migrate against shared `.env` / shared `storage/`.
3. Atomically point `current` at the new release.
4. Reload PHP-FPM if required.

Document root **must** be `public/`. Queue worker / Horizon / application cron: **not required** for this RC (`QUEUE_CONNECTION=sync`; scheduler only has `inspire`).

---

## 4. Prerequisites

Before any production mutation:

- [ ] Durable RC snapshot (commit/tag or archive of this working tree minus do-not-ship extras)
- [ ] Production host access — **not available in the Phase 5B workspace**
- [ ] Production MySQL/MariaDB that can run migrations
- [ ] Confirmed pre-migration backup (see §5)
- [ ] Production `.env` filled from `.env.example` by the operator (never overwrite a known-good production `.env` with `.env.example`)
- [ ] PHP `^8.2` with `pdo_mysql`, `mbstring`, `openssl`, `tokenizer`, `xml`, `ctype`, `fileinfo`, `filter`, `hash`, plus `gd` or `imagick`
- [ ] Composer 2.x **or** a `vendor/` tree from `composer install --no-dev` on this lockfile
- [ ] Node 20+ / npm **only if** assets are built on the host; otherwise ship `public/build` from the same RC `npm run build`
- [ ] Writable `storage/` and `bootstrap/cache/` for the PHP/web user
- [ ] TLS in front of the app; `APP_URL` is the public HTTPS origin
- [ ] First-operator email + strong password (not stored in git)

This workspace: PHP 8.4.1, required extensions present, Composer 2.8.12, Node 22.16.0, npm 10.9.2, `storage/` and `bootstrap/cache/` writable, `public/storage` already linked. That is **not** production access.

This workspace `.env` is `APP_ENV=local`, `APP_DEBUG=true`, MySQL host `172.200.0.2` (unreachable here), `MAIL_MAILER=log`. Do not treat it as production configuration.

---

## 5. Backup procedure

**PRODUCTION CUTOVER = BLOCKED until a verified backup exists.**

Phase 5B could not take or inspect a production backup.

Operator (platform-native method preferred):

1. Record engine/version (`SELECT VERSION();`).
2. Consistent dump, for example:

```bash
mysqldump --single-transaction --routines --triggers \
  --hex-blob --databases "$DB_DATABASE" \
  > "ab2ti-pre-5b-$(date -u +%Y%m%dT%H%M%SZ).sql"
```

3. Record timestamp, schema name, backup path/bucket, who can restore.
4. Verify the dump is non-empty. Optional: restore onto a **non-production** instance.
5. **Do not** restore onto production merely to test.

Restore (disaster only): `php artisan down`, restore the dump onto the same schema, deploy the matching code revision, `php artisan optimize:clear`, `php artisan up`.

Never use `php artisan migrate:fresh`, `migrate:reset`, or `db:wipe` on production.

---

## 6. Environment checklist

| Variable | Production expectation |
|---|---|
| `APP_ENV` | `production` |
| `APP_DEBUG` | `false` |
| `APP_KEY` | already unique; **never regenerate** on an existing install |
| `APP_URL` | real public `https://…` origin |
| `APP_TIMEZONE` | `Asia/Jakarta` |
| `APP_LOCALE` / `APP_FALLBACK_LOCALE` | operator choice; Indonesian public site should use `id` |
| `PUBLIC_DELIVERY_CANONICAL_ORIGIN` | same public origin, or empty to follow `APP_URL` |
| `TRUSTED_PROXIES` | `*` or hop list if TLS terminates on a reverse proxy |
| `SESSION_DRIVER` | `database` (or `redis` if operated) |
| `SESSION_SECURE_COOKIE` | `true` on HTTPS |
| `QUEUE_CONNECTION` | `sync` |
| `CACHE_STORE` | `database` or `redis` |
| `FILESYSTEM_DISK` | `local` |
| `MAIL_MAILER` | real mailer if password-reset mail must leave the server |
| `LOG_LEVEL` | `error` or `warning` |
| `DB_*` | production MySQL/MariaDB |

---

## 7. Filesystem / storage checklist

| Path | Role |
|---|---|
| `storage/app/public` | Public media (CMS, publications, knowledge, Lapak images) |
| `public/storage` | Symlink from `php artisan storage:link` → `storage/app/public` **only** |
| `storage/app/private` | Laravel private disk root |
| `storage/app/private/membership-documents` | **Private** membership uploads; must **not** be under the public symlink |
| `storage/logs` | Laravel logs |
| `bootstrap/cache` | Config/route/view/event caches |

```bash
mkdir -p storage/app/public \
         storage/app/private/membership-documents \
         storage/framework/{cache,sessions,views} \
         storage/logs \
         bootstrap/cache
php artisan storage:link
```

PHP/web user needs write on `storage/` and `bootstrap/cache/`. Do **not** `chmod -R 777` the application.

Confirm URLs such as `/storage/membership-documents/...` are **not** web-reachable.

Rehearsal: `public/storage` already linked; `storage/app/private/membership-documents` created.

---

## 8. Exact deployment commands

Run from the release root. **Do not** run `composer update` or `npm update`.

```bash
# 0. Backup (see §5). If backup missing: STOP.

# 1. Optional if the current site already serves traffic
php artisan down

# 2. Place RC files (git archive, rsync, or panel upload).
#    Exclude: .env, node_modules, vendor (unless using a prebuilt artifact),
#    hip_application_transitions_table'), storage/media-library

composer install --no-dev --prefer-dist --optimize-autoloader --no-interaction

# If building on the host:
npm ci
npm run build
# If building elsewhere: copy public/build from the same RC revision.

# 3. Confirm production .env (do not key:generate if APP_KEY already exists)
php artisan optimize:clear

# 4. Inspect, then migrate — never migrate:fresh
php artisan migrate:status
php artisan migrate --force

# 5. ACL + roles (see §10–11)
php artisan acl:sync
php artisan db:seed --class=RolesAndPermissionsSeeder --force
# ab2ti:provision-operator ONLY if no operator exists (see §11)

mkdir -p storage/app/private/membership-documents
php artisan storage:link

# 6. Caches (see §12)
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache

php artisan up
```

Do **not** run `DatabaseSeeder` or `BlockTemplateSeeder` on production.

`composer install --no-dev` was **dry-run only** in this workspace (it would remove ~60 dev packages including PHPUnit).

`acl:sync` already calls `permission:cache-reset`.

---

## 9. Migration procedure

```bash
php artisan migrate:status
php artisan migrate --force
```

Canonical chain (24 files). All ran on isolated SQLite in Phase 5B:

1. `0001_01_01_000000_create_users_table`
2. `0001_01_01_000001_create_cache_table`
3. `0001_01_01_000002_create_jobs_table`
4. `2026_02_01_120359_add_two_factor_columns_to_users_table`
5. `2026_02_01_120408_create_personal_access_tokens_table`
6. `2026_02_02_025010_create_permission_tables`
7. `2026_02_13_015510_create_files_table`
8. `2026_02_13_020000_create_media_table`
9. `2026_02_13_233125_create_heroes_table`
10. `2026_02_13_234325_add_heroes_permissions` (legacy permission insert; ACL source of truth remains `config/acl.php`)
11. `2026_02_27_050000_add_soft_deletes_to_files_table`
12. `2026_03_13_000001_create_pages_table`
13. `2026_03_13_000002_create_block_templates_table`
14. `2026_04_17_140000_create_welcome_sections_table`
15. `2026_08_20_140000_create_membership_applications_table`
16. `2026_08_20_140001_create_membership_application_transitions_table`
17. `2026_08_20_140002_create_membership_documents_table`
18. `2026_08_20_140003_create_members_table`
19. `2026_08_20_150000_create_publications_table`
20. `2026_08_20_160000_create_knowledge_articles_table`
21. `2026_08_21_130000_create_secure_capability_grants_table`
22. `2026_08_22_160000_create_lapak_tables`
23. `2026_08_22_220000_add_user_id_to_members_table`
24. `2026_08_23_060000_add_payload_to_secure_capability_grants_table`

**`membership_application_transitions`:** one migration file. If production already has the **table** but `migrate:status` shows the migration **Pending**, STOP. That is metadata drift, not a duplicate file. Compare live columns to the migration. Only after the operator confirms the schema matches may they record the migration row. Do **not** blindly insert into `migrations`. Do **not** `migrate:fresh`.

Production MySQL state was **not** inspectable in Phase 5B.

Migrations are **not** a safe production rollback mechanism. Restore the pre-deploy dump if schema must go back.

---

## 10. ACL procedure

Source of truth: `config/acl.php`. Names: `<domain>.<action>` plus the documented exception `access settings`.

```bash
php artisan acl:sync
```

Additive only. Expected fresh count: **40**. Isolated rehearsal: 40 added, no stale rows.

Do **not** run `php artisan acl:sync --clean` on routine cutover.

`acl:sync` refreshes the Spatie permission cache (`permission:cache-reset`).

Staff roles created by `RolesAndPermissionsSeeder`: `super-admin` (`Gate::before` bypass), `membership-admin`, `editor`, and kernel `user`. Frontend permission hiding is UX only.

---

## 11. Operator bootstrap procedure

With `APP_ENV=production`, `RolesAndPermissionsSeeder` creates roles/permissions and **returns before** demo users (`admin@admin.com` / `user@user.com`).

```bash
php artisan db:seed --class=RolesAndPermissionsSeeder --force
```

Confirm `APP_ENV=production` first. Then, **only if no operator exists**:

```bash
php artisan ab2ti:provision-operator operator@example.test --name="Operator" --password='<unique-strong-secret>'
```

The command refuses empty passwords and `password` / `admin` / `admin123` / `secret`. Existing email fails closed. Do not provision a second operator merely because it is in the script. Do not commit the password.

Phase 5B rehearsal (isolated SQLite, `APP_ENV=production`): seeder created no demo users; provision created a single `super-admin`. That database was deleted after rehearsal.

---

## 12. Cache procedure

After `.env` and migrations are final:

```bash
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache
```

Phase 5B rehearsal: all four succeeded, then `optimize:clear` restored this workspace.

If a cache command fails: treat as a deploy defect; `php artisan optimize:clear`; fix; retry.

**Operational caution:** leaving `APP_ENV=production` (or a rehearsal `DB_DATABASE`) exported in a developer shell will make subsequent `composer test` / `artisan` commands ignore `phpunit.xml`. Unset those variables after rehearsal. This is an operator-shell issue, not a product defect.

---

## 13. Content launch checklist

Code RC ≠ public launch content.

| Item | Status in this environment |
|---|---|
| CMS page `about` (`/about`) | **NOT READY** — 404 until an active CMS page exists |
| CMS page `contact` (`/contact`) | **NOT READY** — 404 until an active CMS page exists |
| Homepage Hero / Welcome | Operator review required (unknown on production) |
| `APP_URL` / canonical origin | Operator must set production HTTPS origin |
| Locale | Operator must set intentionally (`id` recommended for public ID site) |
| Mailer | Operator; see §18 in the Phase 5B report / mail section below |
| Brand OG JPEG | Deferred / optional (placeholder remains) |

Do **not** hardcode about/contact into application code. Publish through CMS.

If the organization accepts launching with those URLs as branded 404 until content exists, mark them **INTENTIONALLY UNPUBLISHED**.

---

## 14. Production smoke tests

**Not executed** — no production origin.

After cutover, operator checks (real HTTPS origin, not PHPUnit):

**Public:** `/`, nav desktop/mobile, locale `id`/`en`, a CMS page if published, `/publikasi` (+ one detail if content exists), `/teknologi-tani` (+ one detail), `/lapak` (+ lapak/listing if eligible), `/keanggotaan`, `/robots.txt`, `/sitemap.xml`, unknown path → branded 404, no missing Vite assets.

**Auth:** `/login`, operator login/logout, `/forgot-password` if mailer configured, `/register` closed (Fortify registration feature off).

**Membership:** `GET /keanggotaan/daftar` renders; do not create junk applications unless policy allows a labelled test record. Admin membership routes work for operator; guests redirect; non-staff 403.

**Member:** only with an existing safe test member — workspace ownership; another user cannot open `/akun/lapak` of someone else.

**Admin:** Pages, Hero, Block Templates, File Manager, Publishing, Teknologi Tani, Membership, Members, Lapak moderation.

**Health:** `GET /up`.

---

## 15. SEO / discovery checks

**Not executed** on a public origin.

After deploy, from the real host:

- HTTP→HTTPS as the platform requires
- Canonical, OG, and sitemap URLs use the production host (`PUBLIC_DELIVERY_CANONICAL_ORIGIN` or `APP_URL`)
- `robots.txt` `Sitemap:` line uses that origin
- Disallows include `/admin`, `/akun`, `/akses`, auth, intake `/keanggotaan/daftar`, `/up`, `/api`, `/sanctum`, `/language`
- No `localhost` or development origin in metadata

Do not add JSON-LD or new SEO features in this phase.

---

## 16. Security checks

**Not executed** on production. Expected application behavior (covered by RC tests):

- `/__phpunit/*` registered only when `app()->runningUnitTests()`
- Public registration not in Fortify `features`
- `/admin` requires platform staff; guests redirect; non-staff 403
- Membership documents on private disk `membership_documents`
- `APP_DEBUG=false` → branded 500, no stack traces
- Capability redeem/revision and membership intake are rate-limited

---

## 17. Log inspection

**Not executed** on production.

Immediately after smoke, read `storage/logs/laravel.log` (and web-server / PHP-FPM logs) for **new** boot, DB, permission, filesystem, mail, 500, and capability errors. Ignore historic local-development noise.

This workspace’s logger is the development stack; it is not production evidence.

---

## 18. Rollback criteria

Trigger rollback (or restore) if any of:

- Application will not boot
- `migrate --force` fails
- Required admin shell fails for the operator
- Broad public 5xx
- Authorization boundary broken (public admin, private files via `/storage`, identity bypass)
- Critical Vite assets missing
- Schema incompatible with the running code

---

## 19. Rollback / recovery procedure

| When | Recovery |
|---|---|
| A. Before migrations | Leave previous files in place or retarget symlink; `optimize:clear`; `php artisan up` if you had `down` |
| B. During migrations | **Do not `migrate:rollback` unless the operator has reviewed those `down()` methods.** Restore the pre-deploy DB backup and keep/restore matching code |
| C. After migrations, before going live | Fix forward if additive and safe; otherwise restore DB + previous code |
| D. After live | Previous release directory/symlink + DB restore if schema changed |
| E. After ACL sync | Additive sync rarely needs undo; mistaken `--clean` → restore permission rows from backup |
| F. Cache rebuild | `php artisan optimize:clear`; recache |
| G. After public traffic | Same as D; `php artisan down` during restore |

This hosting model is **not** atomic unless the operator already uses symlink releases.

---

## 20. Deployment outcome

**PRODUCTION EXECUTION NOT PERFORMED.**

| Gate | Result |
|---|---|
| Production host / DB / secrets / backup / operator deploy authorization | **Not present** in this workspace |
| Strongest rehearsal | Isolated SQLite: 24 migrations, `acl:sync` 40, production seeder (no demo users), `ab2ti:provision-operator`, `storage:link` (already existed), config/route/view/event cache, `down`/`up` |
| `composer install --no-dev` | Dry-run only (would strip PHPUnit from this tree) |
| Live smoke / SEO / production logs / mail | Not executed |

**Deployment status:** READY FOR OPERATOR CUTOVER  
**Final release decision:** READY TO RELEASE (code + runbook). Not RELEASED.
