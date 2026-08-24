# AB2TI 2.0 — Release Candidate readiness

**Status:** RC APPROVED (with documented non-blocking deviations)  
**Date:** 2026-08-23  
**Phase:** 5A — RC readiness & release gate  
**Phase 5B:** runbook in `docs/release/production-release.md`. Production cutover was not executed from the Phase 5A/5B workspace.  
**Does not introduce:** Member Card, Koperasi operations, Community, Award, marketplace transactions, or new product features.

Feature freeze is in effect. This document is the operational source of truth for declaring the current tree a Release Candidate.

---

## 1. RC scope

This RC is the **AB2TI 2.0 public + staff + member-workspace platform** after Phases 2–4.

In scope:

- Public site (shell, CMS pages, Publishing, Teknologi Tani, Lapak Anggota, membership information/intake)
- Staff admin (CMS, media, heroes, publishing, knowledge, membership, members, Lapak moderation, users/roles)
- Member account linkage via Secure Capability Grant
- Member-owned Lapak workspace (`/akun/lapak`)
- Platform kernel (auth, ACL, branded errors, SEO/social metadata, i18n, robots/sitemap)

Out of scope for this RC (intentionally deferred; not defects):

- Kartu Anggota production, Koperasi workflows, Community, AB2TI Award
- Payments, checkout, cart, orders, escrow, shipping, marketplace accounting, ratings, chat

---

## 2. Supported features

| Area | Supported |
|---|---|
| Auth | Login, logout, password reset, 2FA, profile. Public `/register` closed |
| Identity | `User` ≠ `Member`; explicit 1:1 `members.user_id` |
| ACL | `config/acl.php` `<domain>.<action>` + documented `access settings`; `acl:sync` |
| Secure Capability Grants | Digest-at-rest bearer grants for revision + account link |
| CMS | Pages, block templates, heroes, welcome section, file manager |
| Media | Spatie Media Library on public disk; private membership documents on `membership_documents` |
| SEO | `PublicMetadataResolver` (canonical, OG, Twitter, robots meta) |
| Public shell | Phase 4A layout, nav, footer, homepage composition |
| i18n | `id` / `en`; JSON sources + `lang:merge` |
| Branded errors | 403 / 404 / 419 always; 500 when `APP_DEBUG=false` |
| Membership | Public apply, staff review/revision/approve/reject, Member records |
| Publishing | Draft/publish, public archive/detail |
| Teknologi Tani | Draft/publish, public archive/detail |
| Lapak Anggota | One Member ↔ one Lapak; listings; public eligible storefront; owner workspace; staff moderation |

---

## 3. Deferred features

Do not treat as missing RC functionality:

- Kartu Anggota numbering/printing (`card_issued` is a compatibility flag only)
- Koperasi membership and operations
- Community
- AB2TI Award
- Marketplace payment/checkout/cart/order/escrow/shipping/accounting
- Ratings/reviews and in-platform chat
- JSON-LD
- Brand-approved OG artwork replacement (placeholder 1200×630 remains)
- English branded error copy (Phase 2E Indonesian copy retained)
- Publication type label translation (`Berita`, `Kegiatan`, … remain product terms)
- Inertia SSR, CDN, application-level cache architecture, Horizon

Public navigation does **not** link these deferred products.

---

## 4. Runtime prerequisites

| Item | Requirement |
|---|---|
| PHP | `^8.2` (verified in this workspace: PHP 8.4.1) |
| Composer | 2.x |
| Node.js | Not pinned in `package.json`; Node 20+ LTS recommended for Vite 7 |
| npm | Repository canonical frontend installer (`package-lock.json`) |
| Laravel | 12.49.0 (`laravel/framework`) |
| Extensions | `ctype`, `filter`, `hash`, `mbstring`, `openssl`, `pdo`, `tokenizer`, `xml`, `gd` or `imagick` (Spatie conversions), `fileinfo` |
| Database | SQLite acceptable for local; **MySQL/MariaDB recommended for production** (identifier-length constraints already encoded in migrations) |

Do not upgrade dependencies merely because newer versions exist.

---

## 5. Environment requirements

Canonical timezone: **`Asia/Jakarta`** (`APP_TIMEZONE`, default in `config/app.php`).

Production must set:

| Variable | Production value |
|---|---|
| `APP_ENV` | `production` |
| `APP_DEBUG` | `false` |
| `APP_KEY` | generated, unique, never committed |
| `APP_URL` | public HTTPS origin |
| `APP_LOCALE` / `APP_FALLBACK_LOCALE` | `id` for the Indonesian public site |
| `PUBLIC_DELIVERY_CANONICAL_ORIGIN` | same public origin (or empty to use `APP_URL`) |
| `SESSION_DRIVER` | `database` (or `redis` if you operate Redis) |
| `SESSION_SECURE_COOKIE` | `true` on HTTPS |
| `QUEUE_CONNECTION` | `sync` (no `ShouldQueue` jobs in this RC) |
| `CACHE_STORE` | `database` or `redis` |
| `FILESYSTEM_DISK` | `local` |
| `MAIL_MAILER` | real mailer (`smtp` / `ses` / etc.); `log` is not production mail |
| `LOG_LEVEL` | `error` or `warning` |
| `TRUSTED_PROXIES` | `*` or explicit hop list **if** TLS terminates on a reverse proxy |

`.env.example` documents these. Never commit `.env`.

---

## 6. Production bootstrap

1. `composer install --no-dev --optimize-autoloader`
2. `npm ci && npm run build`
3. Copy `.env` from `.env.example`; set production values; `php artisan key:generate` once
4. `php artisan migrate --force`
5. `php artisan acl:sync` (additive). Use `acl:sync --clean` only with an explicit stale-permission review
6. `php artisan db:seed --class=RolesAndPermissionsSeeder` **only after confirming `APP_ENV=production`** (creates roles/permissions, **not** demo users)
7. First operator:

```bash
php artisan ab2ti:provision-operator operator@example.test --name="Operator" --password='<unique-strong-secret>'
```

8. `php artisan storage:link`
9. Laravel caches (see §12)
10. Create active CMS pages `about` and `contact` before public launch (content, not code)

`DatabaseSeeder` is **development-only** and refuses to run in `production`.  
`RolesAndPermissionsSeeder` demo accounts (`admin@admin.com` / `user@user.com`, password `password`) are created **only when `APP_ENV` is not `production`**.

---

## 7. Migration procedure

```bash
php artisan migrate --force
```

The migration chain is linear and additive: users/session/cache/jobs, Spatie permission, files/media, CMS, heroes/welcome, membership (+ shortened MySQL identifier names), publishing, knowledge, secure capability grants, lapak, `members.user_id`.

There is **one** `membership_application_transitions` migration. The historical local incident “table already exists” is an operator-state problem (table present without a `migrations` row), not a duplicate-file conflict. Recovery is **not** `migrate:fresh`:

1. Restore/inspect the database
2. If the table matches the migration schema, insert the migration filename into `migrations`
3. Re-run `php artisan migrate --force`

`migrate:fresh --seed` remains a **development reset only**.

---

## 8. ACL bootstrap / sync

Source of truth: `config/acl.php` (`<domain>.<action>`, exception `access settings`).

```bash
php artisan acl:sync              # additive
php artisan acl:sync --dry-run    # report only
php artisan acl:sync --clean      # explicit stale delete (roles/users kept)
```

Staff roles: `super-admin` (Gate::before bypass), `membership-admin`, `editor`.  
Frontend permission hiding is UX only. Policies + `permission:` + `platform-staff` remain authoritative.

---

## 9. Storage requirements

| Disk | Path | Use |
|---|---|---|
| `public` | `storage/app/public` | CMS/media/Lapak/publication/knowledge images; requires `storage:link` → `public/storage` |
| `local` | `storage/app/private` | Laravel private default |
| `membership_documents` | `storage/app/private/membership-documents` | **Private** application documents. Never expose via `/storage` |

Ensure the web user can write `storage/` and `bootstrap/cache/`. Missing conversions should fail closed (public views already omit broken media URLs).

---

## 10. Queue / scheduler requirements

| Item | RC requirement |
|---|---|
| Queue worker | **Not required.** No `ShouldQueue` jobs. Use `QUEUE_CONNECTION=sync` |
| Horizon | **Not required** |
| Scheduler / cron | **Not required** for product behavior (`routes/console.php` only has `inspire`) |
| Mail | Synchronous via configured mailer. Capability/activation currently uses on-request URLs, not a mail-outbox architecture |

Do not add Supervisor workers unless you later introduce queued jobs.

---

## 11. Build procedure

```bash
composer install --no-dev --optimize-autoloader
npm ci
npm run build
```

Canonical frontend lockfile is npm (`package-lock.json`). Do not switch to yarn/pnpm for this RC.

---

## 12. Cache commands

```bash
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache
php artisan permission:cache-reset   # after acl:sync
```

HTTP routes used in production are controller actions (no production closures). PHPUnit-only error routes are registered solely when `runningUnitTests()`.

Clear on rollback:

```bash
php artisan optimize:clear
```

---

## 13. Deployment sequence

Prefer a fresh release directory + symlink swap (Phase 5B). Sequence:

1. Build assets and install PHP deps (`--no-dev`) on the release
2. Copy/share `.env` and `storage/`
3. `php artisan migrate --force`
4. `php artisan acl:sync`
5. `php artisan storage:link` (idempotent if already linked)
6. Cache config/routes/views/events
7. Reload PHP-FPM / web server
8. Smoke-check `/`, `/login`, `/robots.txt`, `/sitemap.xml`

Maintenance mode (`php artisan down` / `up`) is recommended around migrate+cache.

---

## 14. Rollback / recovery notes

| Failure point | Recovery |
|---|---|
| Code/assets only | Point the web root/symlink at the previous release; `optimize:clear` then recache that release |
| `config:cache` / `route:cache` bad | `php artisan optimize:clear` on the active release |
| ACL sync additive mistake | Restore permission rows from backup; do not `--clean` blindly |
| ACL `--clean` | Restore DB backup. Not automatically reversible |
| Migrations | **Not safely reversible in production.** `down()` exists for some tables but dropping membership/lapak/CMS tables is data-loss. Restore a pre-migrate database backup |
| First operator command | Safe to retry with a different email; existing email fails closed |

There is **no** application-level migration undo guarantee. Take a database backup before every production migrate.

---

## 15. Content readiness checklist

Code can be RC while content is empty. Before public launch an operator must:

- [ ] Active CMS page `about`
- [ ] Active CMS page `contact`
- [ ] Homepage hero + welcome copy (or accept fallbacks)
- [ ] At least one published Publication if the publications hub should not be empty
- [ ] At least one published Teknologi Tani article if that hub should not be empty
- [ ] Lapak categories as needed (staff-managed)
- [ ] Organization contact in config/settings used by the public shell
- [ ] Production `APP_URL` / canonical origin
- [ ] Real mailer if password-reset mail must leave the server
- [ ] Replace placeholder OG JPEG when brand art is available (non-blocking)

`/about` and `/contact` **404 until those CMS pages exist**. That is content, not a code blocker.

---

## 16. Smoke test checklist

Automated coverage exists for eligibility, ACL, capability grants, public SEO, robots/sitemap, branded errors, and member/Lapak privacy. Live browser E2E against a production-like URL was **not** executed in this phase.

Operator smoke after first deploy:

**Public:** `/`, `/publikasi`, `/teknologi-tani`, `/lapak`, `/keanggotaan`, locale switch, `/robots.txt`, `/sitemap.xml`, unknown path → branded 404.

**Membership:** submit apply → staff revision grant → resubmit → approve → Member exists.

**Member account:** invite bound to intended user; wrong user 404; new-user activation; login → `/akun/lapak` if linked.

**Admin:** CMS page, hero, block template, file manager, publishing, knowledge, membership, members, Lapak moderation — each requires the matching permission.

**AuthZ:** ordinary user 403 on `/admin`; staff 403 on `/akun/lapak`; guest redirected from workspaces.

---

## 17. Known deviations

- Branded 403/404/419/500 copy is Indonesian even when `APP_LOCALE=en` (Phase 2E contract)
- `/about` and `/contact` 404 without CMS content
- Default `.env.example` `APP_LOCALE=en` (local/tests); production Indonesian site should set `id`
- `composer install --no-dev` was **not** executed against this working tree (it would strip PHPUnit). Lockfile + `composer check-platform-reqs` + `--no-dev --dry-run` are the substitutes
- `vendor/bin/pint --test` fails on 23 **historical** files (`lang/{en,id}` vendor-style translations, a few legacy migrations/controllers). `vendor/bin/pint --dirty` passes. No Phase 5A files. Not a runtime blocker; not cleaned under feature freeze
- Live browser E2E against a production-like URL was not executed in this gate; automated feature coverage plus isolated migrate/`acl:sync`/cache dry-run stand in
- Live multi-width visual QA is not part of this gate (Phase 4D documented the same)
- Jetstream API tokens remain enabled for authenticated users (self-service, not a public marketplace API)

---

## 18. Known non-blocking issues

- Placeholder OG image
- Unpublished/empty archives show empty states (by design)
- `access settings` permission exists and is unused
- `BlockTemplateSeeder` is demo/admin snippets; **do not run in production**
- No JSON-LD
- No application cache layer (not required)

---

## 19. RC blockers

**None identified** after this gate.

Classified and closed as non-blockers: content-empty CMS shortcuts, i18n of error pages, deferred domains, lack of `migrate:fresh` as a deploy strategy.

---

## 20. Final RC decision

**RC APPROVED**, with the documented deviations in §17.

Automated verification used for this decision:

```text
composer test
PHPUnit 11.5.50
OK, but some tests were skipped!
Tests: 457, Assertions: 4232, Skipped: 5.

npm run build
vite v7.3.1
✓ built in 3.73s

vendor/bin/pint --dirty
PASS

vendor/bin/pint --test
FAIL — 23 historical style issues (not Phase 5A; not a runtime blocker)

php artisan migrate --force  (isolated empty SQLite)
PASS — 24 migrations, including membership_application_transitions

php artisan acl:sync         (same isolated SQLite)
PASS — 40 canonical permissions added

php artisan db:seed --class=RolesAndPermissionsSeeder
PASS (demo users only because this workspace APP_ENV is not production)

php artisan ab2ti:provision-operator  (isolated SQLite)
PASS

php artisan config:cache && route:cache && view:cache && event:cache
PASS (CACHE_STORE=array; then optimize:clear)

composer install --no-dev --dry-run
PASS — would remove 60 dev packages; not applied to this working tree
```

The codebase may proceed to operator cutover using `docs/release/production-release.md` when operators have backups, production `.env`, and the content checklist. This Phase 5A document does not execute production deployment.
