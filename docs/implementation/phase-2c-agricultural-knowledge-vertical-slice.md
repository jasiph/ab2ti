# AB2TI 2.0 — Phase 2C Agricultural Knowledge / Teknologi Tani Vertical Slice

**Status:** Implemented  
**Date:** 2026-08-20  
**Does not reopen:** Phase 2A Membership, Phase 2B Publishing, Phase 2B.1 Editorial Content Safety (except where this slice reuses those capabilities).

This is the first Agricultural Knowledge vertical slice: authorized editor creates/edits a Knowledge Article (shared Tiptap + Template Blocks + Editorial Content Safety + Media) → draft → publish → public `/teknologi-tani` index and `/teknologi-tani/{slug}` with central Public Delivery.

Knowledge Article is **not** a Publication, CMS Page, Community contribution, Award item, or Membership record. It reuses platform capabilities; it does not share domain ownership.

---

## 1. Domain model

**Table:** `knowledge_articles`  
**Model:** `App\Models\KnowledgeArticle`  
**Migration:** `database/migrations/2026_08_20_160000_create_knowledge_articles_table.php`

| Column | Role |
|---|---|
| `title` | Required |
| `slug` | Unique on this table |
| `excerpt` | Optional summary |
| `content` | Tiptap HTML through `EditorialContent` |
| `status` | `draft` \| `published` |
| `published_at` | Set on first publish; kept on unpublish |
| `meta_title`, `meta_description` | Optional SEO overrides |
| `created_by`, `updated_by` | Staff User FKs (`restrictOnDelete`) |
| timestamps | Standard |

Index: `(status, published_at)`. No `type` column. No taxonomy columns.

Spatie collections: `knowledge-cover` (hero), `social-og` (optional dedicated share image). Conversions: `thumb`, `social-og` (1200×630 crop).

Relationships: `creator()` / `updater()` to `User`. Media via Spatie `HasMedia`.

**Invariants**

- Fillable is title, slug, excerpt, content, meta fields only. Status, `published_at`, and actor IDs are server-set.
- Public queries use `scopePublished`.
- `KnowledgeArticle` does not extend `Publication`. There is no shared `content_items` table.

**Taxonomy actually implemented:** none. Architecture does not block a later approved category model; PublicationType is not used.

---

## 2. Lifecycle

```
(create) → draft
draft → published     (PublishKnowledgeArticle)
published → draft     (UnpublishKnowledgeArticle; published_at kept)
```

- Named Actions: `App\Actions\Knowledge\PublishKnowledgeArticle` and `UnpublishKnowledgeArticle`.
- Client cannot mass-assign `status`, `published_at`, `created_by`, or `updated_by`.
- Publish is idempotent if already published.
- Unpublish is idempotent if already draft.
- Delete is allowed only while `draft`. Published items must be unpublished first.
- Invalid transitions throw `InvalidKnowledgeArticleTransition` (redirect with `error`).
- No reviewer workflow, scheduling, or revision table.

Audit facts for state changes: actor (`updated_by`), resulting status (row), `published_at`, timestamps. Full body is not copied into a second audit store.

---

## 3. Routes

### Public

| Method | Path | Name |
|---|---|---|
| GET | `/teknologi-tani` | `knowledge.index` |
| GET | `/teknologi-tani/{slug}` | `knowledge.show` |

Public queries use `scopePublished`. Drafts and unpublished rows 404 (not 403) by slug or numeric id.

### Admin (`/admin`, name prefix `admin.`)

| Method | Path | Name | Permission |
|---|---|---|---|
| GET | `knowledge/articles` | `admin.knowledge.articles.index` | `knowledge-articles.view` |
| GET | `knowledge/articles/create` | `…create` | `knowledge-articles.create` |
| POST | `knowledge/articles` | `…store` | `knowledge-articles.create` |
| GET | `knowledge/articles/{article}/edit` | `…edit` | `knowledge-articles.view` |
| PUT/POST | `knowledge/articles/{article}` | `…update` | `knowledge-articles.update` |
| POST | `…/publish` | `…publish` | `knowledge-articles.publish` |
| POST | `…/unpublish` | `…unpublish` | `knowledge-articles.publish` |
| DELETE | `knowledge/articles/{article}` | `…destroy` | `knowledge-articles.delete` |

Create is registered before `{article}/edit`.

---

## 4. Admin workspace

1. List (`BaseList`): title search, status filter; stacked mobile rows; `KnowledgeStatusBadge`.
2. Create/Edit form sections: Core content → Presentation (hero) → Publication actions → SEO/sharing.
3. Body: existing `RichEditor` (Tiptap platform). No `KnowledgeEditor`.
4. Hero / sharing image: upload or `MediaPickerModal` (File Manager).
5. Publish / unpublish / delete use `useConfirm` (presentation only; server Actions/policy remain the boundary).
6. “View public page” only when published.

---

## 5. Public Teknologi Tani

- Index: published cards (`KnowledgeArticleCard`) with title, excerpt, visual, date, route to detail.
- Detail: title, optional summary, hero, rendered body, semantic headings, back link to `/teknologi-tani`.
- Homepage: latest-3 published cards (`LatestKnowledge`) without redesigning the landing page.
- Public nav “Teknologi Tani” / “Farming Tech” now targets `knowledge.index` (was the leftover `/sermons` CMS alias). `/sermons` itself is unchanged.

Visibility: only `status = published`. Frontend hiding is not the security boundary.

---

## 6. Tiptap reuse

Reused `resources/js/Components/RichEditor.vue` and its extensions (StarterKit, Image, DynamicBlock, GlobalDiv, GlobalAttributes). No Knowledge-only editor. No Tiptap table extension was added (none exists on the platform; Phase 2C did not require one).

Storage remains HTML (same as CMS Pages and Publications).

---

## 7. Template Block reuse

Existing registered blocks used as-is:

1. Highlight Card (Android Style)
2. Beautiful Image with Caption
3. Two Columns Feature

No Knowledge-only blocks were invented. Public detail CSS keeps images from overflowing, captions readable, long URLs wrapping, and two-column grids collapsing to one column on small screens (`grid-cols-1` already present in the seeded snippet; public prose also forces that collapse).

---

## 8. Editorial Content Safety

Knowledge Article write and public/admin load use the shared pipeline:

```
Tiptap HTML
  → EditorialContent::normalize()   // save
  → stored HTML
  → EditorialContent::render()      // public + editor reload
```

- Free-form sanitizer: `App\Support\Html\EditorialHtml`
- Allow-list: `App\Support\Editorial\TemplateBlockRegistry`

There is no `KnowledgeSanitizer`, `KnowledgeHtml`, or Knowledge-specific block renderer.

---

## 9. Media reuse

- Library selection: `exists:files,id` then Spatie `copy()` onto Knowledge collections.
- Direct upload: creates a `File` row then copies onto the article.
- Membership private documents live on `membership_documents` and cannot satisfy `exists:files,id`.
- Inertia props expose public URLs only, not disk/`object_key`/raw media collections.

---

## 10. Public Delivery / SEO

Every public Knowledge URL uses:

```
PublicMetadataInput
        ↓
PublicMetadataResolver::forKnowledgeArticle()
```

No Knowledge-specific SEO resolver. `forKnowledgeArticle()` feeds the shared `resolve()` method (`ogType: article`).

Description fallback (central):

```
SEO override (meta_description)
    ↓
excerpt
    ↓
safe plaintext from content
    ↓
site default
```

OG image fallback (central):

1. dedicated `social-og` media
2. Knowledge hero (`knowledge-cover`)
3. site default `/images/og/ab2ti-og-default-v1.jpg`

Canonical: `https://{origin}/teknologi-tani/{slug}` (index: `/teknologi-tani`).  
Admin surfaces use `forTransactionalPage` (noindex). Index uses `forPublicInformationalPage`.

Controllers must not rely on `forRequest()` for `/teknologi-tani` because reserved CMS first segments would be treated as private/noindex.

---

## 11. Authorization

Canonical two-segment names (exactly one dot):

| Permission | Use |
|---|---|
| `knowledge-articles.view` | Admin list/edit screen (including drafts) |
| `knowledge-articles.create` | Create draft |
| `knowledge-articles.update` | Edit fields (not publish) |
| `knowledge-articles.publish` | Publish **and** unpublish |
| `knowledge-articles.delete` | Delete drafts |

Public read of published items is not a Spatie permission.

**Role `editor`** is now seeded with CMS + heroes + media + publishing + the five Knowledge capabilities. It does **not** receive Membership or Koperasi capabilities. Authorization is permission/policy based, not `hasRole('editor')`.

`platform-admin` is still not seeded. `super-admin` retains `Gate::before`. `membership-admin` does not receive Knowledge.

Rejected names (not registered): `knowledge.articles.view`, `knowledge.articles.publish`, `knowledge-articles.workflow.publish`, `knowledge-articles.*`.

---

## 12. CMS / route ownership

Reserved first segment added: `teknologi-tani`.

Knowledge routes are registered **before** the CMS catch-all. Publishing, Membership, and CMS Page families remain their existing owners. `/sermons` remains a CMS alias (legacy; redirect is a later product decision).

---

## 13. HCI / accessibility

- Feature components: `KnowledgeArticleCard`, `KnowledgeStatusBadge` (domain concepts; not PublicationType reuse).
- Base/shared: `BaseList`, `BaseButton`, `BaseInput`, `BaseTextarea`, `BaseCard`, `BaseStatusBadge`, `RichEditor`, `MediaPickerModal`.
- Public reading: `h1`, optional summary, `time`, `figure`/`img` alt from title, `nav` back link, `prose` body, visible focus on cards.
- Admin: associated labels, required title/slug, confirm dialogs with action names.
- Staff `created_by` is not a public byline.

---

## 14. Tests

- `tests/Feature/Knowledge/KnowledgeWorkflowTest.php`
- `tests/Feature/Knowledge/KnowledgeAuthorizationTest.php`
- `tests/Feature/Knowledge/KnowledgePublicDeliveryTest.php`
- `tests/Feature/Knowledge/KnowledgeAclNamingTest.php`
- `tests/Feature/Knowledge/KnowledgeEditorialSafetyTest.php`
- `tests/Feature/Knowledge/KnowledgeMediaTest.php`
- CMS reserved-path extensions in `CmsPathTest` and `CmsRoutingTest`

---

## 15. Deviations

1. **Empty body may be published.** Phase 1F requires title/slug, not content (same as Publishing).
2. **Hard delete only for drafts.** Published rows must be unpublished first.
3. **No public author/byline.** `created_by` is staff attribution only.
4. **No Knowledge taxonomy.** None was approved in Phase 1.
5. **Tiptap tables were not added.** The platform editor has no table extension; Phase 2C did not introduce one.
6. **`/sermons` was not redirected.** Public nav now points at `/teknologi-tani`; the leftover CMS alias remains.
7. **Homepage `ServiceEvents` leftover** was not removed (not a homepage redesign).

---

## 16. Open business questions

1. Agricultural Knowledge taxonomy (seed technology, cultivation, soil, pest, post-harvest, etc.) — not approved as V1 fields.
2. Public byline / author display for Knowledge Articles.
3. Whether `/sermons` should redirect to `/teknologi-tani`.
4. Provibio URL (`/provibio` vs nest under Teknologi Tani).
5. Whether empty body may remain publishable.
6. Whether published articles may later be hard-deleted after unpublish-only is reconsidered.

---

## 17. Future Template Block compatibility

Phase 2C stores declarative HTML wrappers (`data-block-type` + inner snippet) through the existing registry. That does not implement, and does not prevent, the later path:

```
Static Template Block
        ↓
Structured Template Block
        ↓
Registered Service-Backed Block
        ↓
Trusted Domain Read Resolver
        ↓
Reusable Renderer
```

`TemplateBlockRegistry::allows()` already accepts an unused `$context` argument so a later phase can restrict types per domain without a second registry. Future service-backed blocks must remain explicitly registered and schema-validated. The editor must never store arbitrary model names, table names, SQL, PHP classes, method names, unrestricted Vue component names, storage paths, or executable code.

This phase did **not** build a dynamic/service-backed block framework.

---

## 18. Files of note

- Model / enum / factory: `KnowledgeArticle`, `KnowledgeArticleStatus`, `KnowledgeArticleFactory`
- Actions: `PublishKnowledgeArticle`, `UnpublishKnowledgeArticle`
- HTTP: `Admin\Knowledge\KnowledgeArticleController`, `Knowledge\PublicKnowledgeController`, FormRequests, `KnowledgeArticlePolicy`
- Support: `KnowledgeArticleSlug`, `KnowledgeArticleMedia`, `KnowledgeArticleViews`
- Public metadata: `PublicMetadataResolver::forKnowledgeArticle()`
- Vue: `Pages/Admin/Knowledge/Articles/{Index,Form}.vue`, `Pages/Frontend/Knowledge/{Index,Show}.vue`, `Components/Knowledge/*`, `LatestKnowledge.vue`
- ACL: `config/acl.php` group `knowledge-articles`; `RolesAndPermissionsSeeder` editor mapping
- Reserved CMS: `config/cms.php` `teknologi-tani`
