# AB2TI 2.0 RC — operator cutover checklist

Use during a real production cutover. Full detail: `docs/release/production-release.md`.

**Never:** `migrate:fresh` / `migrate:reset` / `db:wipe` / `composer update` / `npm update` / `acl:sync --clean` / `key:generate` on an existing install / `DatabaseSeeder` / `BlockTemplateSeeder`.

---

## PRE-DEPLOY

1. [ ] RC tree snapshotted (commit/tag/archive). HEAD `38c952a` alone is **not** the RC.
2. [ ] Exclude `.env` and `storage/media-library`. Deploy the tagged commit `ab2ti-2.0-rc` only.
3. [ ] Production backup confirmed (method, time, location, who can restore). **No backup → STOP.**
4. [ ] `php artisan migrate:status` on production. If `membership_application_transitions` exists without a migrations row → STOP (controlled recovery, not `migrate:fresh`).
5. [ ] Production `.env`: `APP_ENV=production`, `APP_DEBUG=false`, HTTPS `APP_URL`, `APP_TIMEZONE=Asia/Jakarta`, locale intentional, `SESSION_SECURE_COOKIE=true`, `QUEUE_CONNECTION=sync`, real `DB_*`, `TRUSTED_PROXIES` if behind TLS proxy.
6. [ ] `APP_KEY` already set — do not regenerate.
7. [ ] PHP `^8.2` + `pdo_mysql`/`gd` or `imagick`; writable `storage/` and `bootstrap/cache/`; vhost → `public/`; TLS.
8. [ ] About/contact: READY / NOT READY / INTENTIONALLY UNPUBLISHED.

## DEPLOY

9. [ ] `php artisan down` if the site already has traffic.
10. [ ] Place RC files. `composer install --no-dev --prefer-dist --optimize-autoloader --no-interaction`.
11. [ ] `npm ci && npm run build` **or** copy `public/build` from the same RC revision.
12. [ ] `php artisan optimize:clear`
13. [ ] `php artisan migrate --force`
14. [ ] `php artisan acl:sync` (additive; expect 40 on a fresh ACL table)
15. [ ] `php artisan db:seed --class=RolesAndPermissionsSeeder --force` only with `APP_ENV=production`
16. [ ] If **no** operator: `php artisan ab2ti:provision-operator {email} --password=...` (do not record the password here)
17. [ ] `mkdir -p storage/app/private/membership-documents && php artisan storage:link`
18. [ ] `php artisan config:cache && php artisan route:cache && php artisan view:cache && php artisan event:cache`
19. [ ] `php artisan up` — confirm not left in maintenance.

## VERIFY

20. [ ] `/` HTTPS, assets load, locale switch, `/robots.txt`, `/sitemap.xml`, branded 404
21. [ ] Canonical/OG/sitemap host = production (no localhost)
22. [ ] `/login` works; `/register` closed; `/__phpunit/*` 404
23. [ ] Operator reaches admin CMS / Hero / templates / files / publishing / knowledge / membership / members / Lapak
24. [ ] Guest/non-staff denied `/admin`; private membership files not under `/storage`
25. [ ] Intake `GET /keanggotaan/daftar` renders (do not junk-submit unless labelled)
26. [ ] Mail: password reset sends **or** documented as not operational
27. [ ] New lines in `storage/logs/laravel.log` (and web/FPM logs) show no boot/migrate/5xx storm

## ROLLBACK IF NEEDED

Trigger: no boot, migrate failure, broad 5xx, authz break, private files public, missing assets.

28. [ ] `php artisan down` if still live
29. [ ] Point to previous release/symlink **or** restore previous files
30. [ ] If schema changed: restore pre-deploy DB dump (do not assume `migrate:rollback` is safe)
31. [ ] `php artisan optimize:clear` then recache on the restored revision
32. [ ] `php artisan up` and re-smoke
