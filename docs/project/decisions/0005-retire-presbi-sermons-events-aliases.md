# 0005 — Retire Presbi `/sermons` and `/events` aliases

Date: 2026-08-21
Status: Accepted
Supersedes: the `/sermons` / `/events` hold in ADR 0001 and the “aliases are not reserved” note for those two paths in ADR 0003

## Context

AB2TI 2.0 inherited CMS shortcut routes `/sermons` and `/events` from the Presbi/church kernel. They were never AB2TI product URLs. Public labels (“Farming Tech”, “News”) hid church slugs. Phase 1B classified both as **retire**, not canonical IA.

Constitution §8 and ADR 0001 required an information-architecture ADR before removing them. Phase 2C already moved Teknologi Tani to `/teknologi-tani`. Publishing already owns `/publikasi` and `/berita`. There is no semantic relationship between sermons and agricultural knowledge, and no proven AB2TI compatibility requirement to keep the old aliases.

Redirecting `/sermons` → `/teknologi-tani` or `/events` → `/publikasi` would invent a false historical mapping.

## Decision

- Remove explicit CMS alias routes `GET /sermons` (`sermons`) and `GET /events` (`events`).
- Do **not** add redirects from those paths to AB2TI content.
- Reserve first segments `sermons` and `events` in `config/cms.php` so they cannot be reclaimed as CMS pages.
- A request to `/sermons` or `/events` is a 404.
- `/about` and `/contact` remain valid AB2TI CMS aliases (informational, not church domain).
- Do not resurrect Sermon models, admin UI, ACL, or navigation.

## Consequences

- Public and footer navigation must use real AB2TI routes (`knowledge.index`, `publications.index`, membership, CMS about/contact).
- Catch-all CMS pages cannot occupy `sermons` or `events`.
- Operator databases may still contain a `pages` row with slug `sermons` or `events`; those rows are not publicly reachable after this ADR.
- Constitution §8 is amended to match this decision.
