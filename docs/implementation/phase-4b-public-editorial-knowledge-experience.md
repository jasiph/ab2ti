# AB2TI 2.0 — Phase 4B Public Editorial & Knowledge Experience

**Status:** Implemented  
**Date:** 2026-08-22  
**Does not start:** Phase 4C.2/4C.3 (member self-service and public Lapak). Phase 4C.1 domain/admin foundation: `docs/implementation/phase-4c1-lapak-domain-admin-foundation.md`. Member Portal, Kartu Anggota, Koperasi UI, Community, Award workflow, comments, likes, bookmarks, payments, newsletter, and a page builder remain out of scope.  
**Does not reopen:** Phase 3 Membership, Phase 4A public shell, Phase 2B/2C domain ownership.

---

## 1. Inherited public content state

Phase 2B/2C already shipped public index/detail, `published()` scopes, `PublicationViews` / `KnowledgeArticleViews`, `EditorialContent`, and central SEO. Phase 4A put those pages on `FrontendLayout`.

Gaps closed in 4B:

- No public search
- Publication index did not eager-load media (N+1 on cards)
- No related / further-reading on detail
- Empty state did not distinguish “none published” vs “search miss”
- Knowledge show had richer prose CSS than Publication show
- Archives did not use `PublicContainer`
- Knowledge has no taxonomy; none was invented

---

## 2. Public information architecture

Unchanged from 4A: Publikasi and Teknologi Tani remain primary nav items. Type archives stay `/berita`, `/kegiatan`, `/siaran-pers`, `/liputan-media`. CMS catch-all still cannot own those prefixes.

---

## 3. Publication archive

`PublicPublicationController@index` / `typeIndex`:

- `published()` + `with('media')`
- Optional type from the path (existing enum only)
- `q` query parameter via `PublicContentSearch` (title or excerpt, max 100 chars)
- `paginate(12)->withQueryString()`
- Cards via `PublicationViews::publicCard`
- Type chips preserve `q`
- Empty copy differs for search vs no content

---

## 4. Publication search / filter

Meaningful filters: the four Publication types as **routes**, not a new taxonomy. Search is GET `q`. No tags, dates, authors, or full-text body search (body is HTML).

---

## 5. Publication cards

Same `PublicationCard` on homepage, archive, and related. Card image prefers Spatie `thumb` when generated; alt is the title. `type_label` is projected from `PublicationType::label()`.

---

## 6. Publication detail

Still `/{type}/{slug}` and `published()` only (wrong type or draft → 404). Cover uses original media URL. Body through `EditorialContent` → `EditorialBody`. Related: same type, other published rows, newest 3, `publicCard`.

---

## 7. Teknologi Tani archive

`PublicKnowledgeController@index`: `published()`, media eager-load, `q` search, pagination 12. **No category/type filter** — Phase 2C taxonomy is none.

---

## 8. Knowledge search / filter

GET `q` only. No invented taxonomy.

---

## 9. Knowledge cards

Same `KnowledgeArticleCard`; thumb when available.

---

## 10. Knowledge detail

`/teknologi-tani/{slug}`, published only. Further reading: other published articles, newest 3.

---

## 11. Editorial rendering

Unchanged pipeline: `EditorialContent::render`. Vue wrapper `EditorialBody` is presentation only (prose + overflow). Not a second sanitizer. CMS Page show still uses its own prose block (not in 4B scope).

---

## 12. Media

Cover collections unchanged (`publication-cover`, `knowledge-cover`). Cards use `thumb`; detail/OG still original / `social-og` resolver hierarchy.

---

## 13. Related / further reading

Domain queries, `published()`, exclude self, limit 3, `publicCard`. Hidden when empty. Not a recommendation engine.

---

## 14. Empty states

Archive: “none published yet” vs “no match for this search” + clear search. Detail 404 for unpublished. Related sections omitted when empty.

---

## 15. SEO / social

Unchanged resolvers: `forPublication`, `forKnowledgeArticle`, `forPublicInformationalPage` for archives. Canonical paths do not include `q`. Drafts remain 404, not noindex pages.

---

## 16. Accessibility / responsive

`PublicContainer` rhythm; search labelled; type chips `min-h-control` + focus ring; breadcrumbs `aria-label`; pagination is a `nav` with current page; cards still whole-link with focus ring; readable `max-w-prose` on detail; images `object-cover`.

---

## 17. Performance

List/related queries eager-load `media`. Card thumbs instead of full covers. Search is indexed columns (`title`, `excerpt`) plus existing `(status, type, published_at)` / `(status, published_at)`. No new JS libraries.

---

## 18. Tests

`tests/Feature/PublicExperience/PublicArchiveExperienceTest.php` and `tests/Unit/PublicDelivery/PublicContentSearchTest.php`. Existing public delivery, sanitization, CMS catch-all, and Membership tests preserved.

---

## 19. Deferred

Lapak, comments/likes/bookmarks, newsletter, publication tags, knowledge taxonomy, full-text body search, CMS page visual redesign, page builder.

---

## 20. Phase 4C inputs

Public shell and content families are stable. Lapak should append to `frontendMenus` only when a real public route exists. Do not reuse Publication/Knowledge tables for listings.
