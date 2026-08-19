# Local database recovery (historical leftovers)

This note is for **local/developer databases only**. It is not a production runbook and it does not authorize destructive commands.

## Canonical schema vs local history

- **Canonical forward schema** is the set of files in `database/migrations`.
- A developer database that existed **before Phase 0A** is **historical local state**. It is not schema authority.
- Phase 0A removed Presbi/church **migration files**. It did **not** `DROP` leftover tables on existing developer databases.
- Application `migrate` succeeding on an old DB does **not** mean that database is clean of leftover church tables.

Do **not** run `migrate:fresh`, `db:wipe`, unconstrained `DROP TABLE`, or any automatic leftover-table cleaner unless you are explicitly operating on a **disposable** database you created for that purpose.

`php artisan acl:sync` is likewise a **conscious maintenance** operation. After ACL groups are removed from `config/acl.php`, previously synced developer databases MAY still contain stale permission rows until an operator runs sync with `--clean` on a database they intend to maintain.

## Preferred path if you do not need old local data

1. Leave the old database file/schema in place, or copy it somewhere as a backup if you might want it later.
2. Create a **new** empty local database (new SQLite file, or a new MySQL/Postgres schema).
3. Point `.env` at that new database **manually**.
4. Run the current canonical migrations (`php artisan migrate`) against the new database.
5. Recreate only the development users/roles/content you still need (seeders or manual inserts). Do not import leftover church tables.
6. Keep or delete the old database only by **your** explicit decision.

`composer setup` copies `.env.example` when `.env` is missing, generates an app key, runs `php artisan migrate` (without `--force`), then installs and builds frontend assets. Use it for a **new** local checkout, not as a way to rebuild an existing populated developer database.

## If you must keep existing local data

1. Backup the database first.
2. Inventory tables: compare what `migrate` created on a disposable DB against what the old DB contains.
3. Treat leftover church tables (`congregations`, `letters`, `songs`, and related names) as **non-canonical**. Do not issue a broad `DROP`.
4. Plan any table-by-table change explicitly. Prefer leaving unused leftover tables in place until a dedicated, consented cleanup is designed.
5. Do not claim the old database is “clean” merely because the application boots or tests pass (tests use in-memory SQLite, not your developer DB).

## What this repository will not do

- No migration in this project silently drops leftover church tables.
- No artisan command in this project bulk-drops historical tables.
- Agents must not modify the active development database as a side effect of ordinary work.
