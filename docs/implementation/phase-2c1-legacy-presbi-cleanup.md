# AB2TI 2.0 — Phase 2C.1 Legacy Presbi Domain Cleanup

**Status:** Implemented  
**Date:** 2026-08-21  
**Does not start:** Koperasi, Community, AB2TI Award, or the next business feature.

Governing rule: Presbi/GPII domain-specific behavior is removed. Generic platform capabilities already in use stay. Church semantics are not renamed into AB2TI.

Constitution conflict: §8 (v1.1) and ADR 0001 forbade removing `/sermons` and `/events` without an IA ADR. This phase records **ADR 0005** and amends Constitution to v1.2, then performs the retirement.

---

## 1. Legacy artifacts discovered

| Artifact | Classification | Action |
|---|---|---|
| `GET /sermons` CMS alias (`sermons`) | REMOVE | Removed; first segment reserved |
| `GET /events` CMS alias (`events`) | REMOVE | Removed; first segment reserved |
| Public nav “News” → `events` | REMOVE | Dropped (Publikasi already exists) |
| Footer `/sermons`, `/events`, `#program` | REMOVE | Retargeted to AB2TI routes |
| `ServiceEvents.vue` | REMOVE | Deleted (church name + leftover placeholder cards; not a generic primitive) |
| Homepage `ServiceEvents` mount | REMOVE | Unwired |
| i18n `Sermons` / `Khotbah`, `Events` / `Acara` | REMOVE | Removed from sources + remarged |
| Sermon models/controllers/policies/ACL | — | None present at runtime |
| Sermon migrations/tables in repo | — | None; Phase 0A already removed church migrations |
| `culture.*` / `artikel.*` in `config/acl.php` | HISTORICAL | Already removed in 0C-B; stale DB rows remain operator `--clean` |
| `/about`, `/contact` | KEEP | AB2TI informational CMS aliases |
| Publishing `kegiatan` | KEEP | AB2TI Publishing type; not church ServiceEvent |
| Hero/Welcome leftover `#program` fallbacks | GENERALIZE (retarget only) | Point at `/teknologi-tani` instead of a missing `#program` section |
| Discovery/implementation docs mentioning sermons | HISTORICAL/DOCUMENTATION ONLY | Left as audit history |
| `LOCAL_DATABASE_RECOVERY.md` church tables | HISTORICAL | Operator recovery; no DROP |

No Rakernas, rayon, Hamba Tuhan, jemaat, preacher, or worship **runtime** modules were found.

---

## 2. What was removed

- Explicit routes `/sermons` and `/events`
- `ServiceEvents.vue` and its homepage mount
- Public “News” item bound to `events`
- Footer church leftover URLs and unimplemented `#program` program list
- Localization keys `Sermons`, `Events`, `News` (nav leftover), and ServiceEvents-only landing strings
- Tests that treated `/sermons` and `/events` as live CMS aliases

---

## 3. What was retained

- CMS kernel (`Page`, catch-all, `/about`, `/contact`)
- Publishing, Knowledge, Membership, Media, Public Delivery
- Hero + Welcome landing sections (AB2TI copy)
- Latest Publications and Latest Teknologi Tani homepage rows
- Fortify/Jetstream platform routes
- Historical documentation of the leftover aliases

---

## 4. What was generalized

None as a new shared component. `ServiceEvents` was **not** worth salvaging: hardcoded cards, fake `date`/`time` fields, dead `#` links, Unsplash placeholders. Homepage already has real Publication and Knowledge surfaces.

Hero secondary CTA and Welcome default CTA were retargeted from `#program` to `/teknologi-tani` so removing the leftover section does not leave dead in-page anchors. That is not a homepage redesign.

---

## 5. Migration / schema strategy

**C — no schema change.**

There were no Sermon tables or migrations in this repository (removed in Phase 0A). No forward DROP migration was added. Leftover church tables on old developer databases remain non-canonical (`LOCAL_DATABASE_RECOVERY.md`). `pages` rows with slug `sermons` or `events`, if present locally, are unreachable and cannot be recreated via CMS validation.

---

## 6. Route changes

Removed:

- `GET /sermons` name `sermons`
- `GET /events` name `events`

Remaining relevant public routes: `/`, `/about`, `/contact`, `/publikasi`, `/berita` (etc.), `/teknologi-tani`, `/keanggotaan`, CMS catch-all.

---

## 7. ACL changes

None. No Sermon/church permissions existed in `config/acl.php`. `acl:sync --clean` was **not** run. Stale `culture.*` / `artikel.*` DB rows remain an operator step.

---

## 8. Navigation / localization

- Public nav: Home, About AB2TI, Teknologi Tani (`knowledge.index`), Publications, Contact, Membership. No Sermons/News/`events`.
- Admin nav: unchanged (already AB2TI domains).
- Footer Navigasi/Program use `knowledge.index` and `publications.index`.
- `lang:merge` for `id` and `en` after source key removal.

---

## 9. Homepage cleanup

Composition is now: Hero → Welcome → Latest Publications → Latest Knowledge. No church-named section. No fourth unmanaged program strip.

---

## 10. Tests

- `CmsRoutingTest`: retired aliases 404 even if leftover page rows exist; slugs cannot be created
- `CmsPathTest`: `sermons` and `events` reserved
- `LegacyPresbiCleanupTest`: no route/permission names; homepage has no `/sermons`; AB2TI families still work

---

## 11. Remaining legacy references

Intentional:

- ADR 0005, Constitution v1.2, this document, ARCHITECTURE_BASELINE debt item 5 (resolved)
- Phase 0–1 discovery docs (historical)
- `LOCAL_DATABASE_RECOVERY.md` leftover church tables
- Tests asserting `/sermons` is **not** a content route
- CSS `pointer-events` / npm `fsevents` (unrelated)

---

## 12. Deviations

1. `/events` was retired in the same ADR as `/sermons` (same leftover class; task required inspecting other Presbi aliases). No redirect to `/publikasi`.
2. Public “News” nav item was removed rather than pointed at `/publikasi`, to avoid duplicating Publications.
3. Footer unimplemented program hash-links (Koperasi, Pelatihan, Advokasi, Bank Benih) were removed instead of exposing future features.
4. Constitution amended to v1.2 so the old “do not remove without ADR” sentence cannot resurrect the aliases.
