# AB2TI 2.0 — Phase 2B Publishing Editorial Vertical Slice

**Status:** Implemented  
**Date:** 2026-08-20  
**Follow-up (Phase 2B.1):** CMS Pages and Publications now share `EditorialContent` (see `docs/implementation/phase-2b1-editorial-content-safety-template-blocks.md`). The Phase 2B deviation that left CMS Page save unsanitized is closed.

This is the second AB2TI business-domain vertical slice: staff create/edit a Publication (Tiptap + Media) → save draft → publish → public URL with central metadata/OG.

Publication is **not** a CMS Page, Knowledge Article, Award item, Community contribution, or Membership record.

---

## 1. Files / schema introduced

### Schema

Table `publications`:

| Column | Role |
|---|---|
| `type` | Controlled enum: `berita`, `kegiatan`, `siaran_pers`, `liputan_media` |
| `title` | Required |
| `slug` | Unique on this table |
| `excerpt` | Optional summary |
| `content` | Tiptap HTML (nullable) |
| `status` | `draft` \| `published` |
| `published_at` | Set on first publish; kept on unpublish |
| `meta_title`, `meta_description` | Optional SEO overrides |
| `created_by`, `updated_by` | Staff User FKs (`restrictOnDelete`) |
| timestamps | Standard |

Index: `(status, type, published_at)`.

Migration: `database/migrations/2026_08_20_150000_create_publications_table.php`

Spatie collections on the Publication model: `publication-cover` (hero), `social-og` (optional dedicated share image). Conversions: `thumb`, `social-og` (1200×630).

### Principal application files

- Model: `Publication`
- Enums: `PublicationType`, `PublicationStatus`
- Actions: `PublishPublication`, `UnpublishPublication`
- FormRequests: `StorePublicationRequest`, `UpdatePublicationRequest`
- Policy: `PublicationPolicy`
- Controllers: `Admin\PublicationController`, `Publishing\PublicPublicationController`
- Support: `PublicationSlug`, `PublicationViews`, `PublicationMedia`, `Html\EditorialHtml`
- Exception: `InvalidPublicationTransition`
- Vue: `Pages/Admin/Publishing/Publications/{Index,Form}.vue`, `Pages/Frontend/Publishing/{Index,Show}.vue`, `Components/Publishing/*`, homepage `LatestPublications.vue`
- ACL group in `config/acl.php`; reserved CMS segments; `editor` role in `RolesAndPermissionsSeeder`

---

## 2. Publication types actually implemented

Exactly the Phase 1F / Phase 1B set:

| Stored value | Public path | Label |
|---|---|---|
| `berita` | `/berita/{slug}` | Berita |
| `kegiatan` | `/kegiatan/{slug}` | Kegiatan |
| `siaran_pers` | `/siaran-pers/{slug}` | Siaran pers |
| `liputan_media` | `/liputan-media/{slug}` | Liputan media |

No extra taxonomy, tags, or categories.

---

## 3. Editorial lifecycle

```
(create) → draft
draft → published     (PublishPublication)
published → draft     (UnpublishPublication; published_at kept)
```

- Client cannot mass-assign `status`, `published_at`, `created_by`, or `updated_by`.
- Publish is idempotent if already published.
- Unpublish is idempotent if already draft.
- Delete is allowed only while `draft`. Published items must be unpublished first.
- No scheduling, approval queue, or revision table.

Audit facts for state changes: actor (`updated_by`), previous/resulting status (row), `published_at`, timestamps. Full body is not copied into a second audit store (Phase 1F: no publication transitions table).

---

## 4. Routes

### Public

| Method | Path | Name |
|---|---|---|
| GET | `/publikasi` | `publications.index` |
| GET | `/{type}` | `publications.type` (`berita`\|`kegiatan`\|`siaran-pers`\|`liputan-media`) |
| GET | `/{type}/{slug}` | `publications.show` |

Public queries use `scopePublished`. Drafts and unpublished rows 404 (not 403) by slug or id. Type prefix must match the row type.

### Admin (`/admin`, name prefix `admin.`)

| Method | Path | Name | Permission |
|---|---|---|---|
| GET | `publishing/publications` | `admin.publishing.publications.index` | `publishing-publications.view` |
| GET | `publishing/publications/create` | `…create` | `publishing-publications.create` |
| POST | `publishing/publications` | `…store` | `publishing-publications.create` |
| GET | `publishing/publications/{publication}/edit` | `…edit` | `publishing-publications.view` |
| PUT/POST | `publishing/publications/{publication}` | `…update` | `publishing-publications.update` |
| POST | `…/publish` | `…publish` | `publishing-publications.publish` |
| POST | `…/unpublish` | `…unpublish` | `publishing-publications.publish` |
| DELETE | `publishing/publications/{publication}` | `…destroy` | `publishing-publications.delete` |

Create is registered before `{publication}/edit`.

---

## 5. Admin editorial workspace

1. List (`BaseList`): title search, status filter, type filter; stacked mobile rows; readable status/type badges.
2. Create/Edit form sections: Core content → Presentation (hero) → Publishing actions → SEO/sharing.
3. Body: existing `RichEditor` (Tiptap platform).
4. Hero / sharing image: upload or `MediaPickerModal` (File Manager). Optional `remove_*` flags.
5. Publish / unpublish / delete use `useConfirm` (presentation only; server Actions/policy remain the boundary).
6. “View public page” only when published.

---

## 6. Tiptap reuse

Reused `resources/js/Components/RichEditor.vue` and its extensions (StarterKit, Image, DynamicBlock, GlobalDiv, GlobalAttributes, MediaPickerModal). No Publishing-only editor.

Storage remains HTML (same as CMS Pages).

**Generic platform addition:** `App\Support\Html\EditorialHtml` allow-list sanitizer (strips `script`, `on*`, `javascript:` / non-http(s)|mailto URLs). Wired on Publication create/update. CMS Page save was **not** rewritten in this phase.

---

## 7. Media reuse

- Library selection: `exists:files,id` then Spatie `copy()` onto Publication collections.
- Direct upload: creates a `File` row (File Manager ownership) then copies onto the Publication.
- Membership private documents live on `membership_documents` / `membership_documents` table and cannot satisfy `exists:files,id`.
- Inertia props expose public URLs only, not disk/`object_key`/raw media collections.

---

## 8. Public Delivery / SEO

Every public Publication URL uses:

```
PublicMetadataInput
        ↓
PublicMetadataResolver::forPublication()
```

No Publishing-specific SEO resolver.

`forPublication()` sets `ogType: article`, canonical `/berita/{slug}` (etc.), title from `meta_title` then title, description from `meta_description` then excerpt then HTML plaintext, indexable only when published.

OG image chain is the **central** resolver chain:

1. dedicated `social-og` media
2. publication cover (`publication-cover`)
3. site default `/images/og/ab2ti-og-default-v1.jpg`

Admin surfaces use `forTransactionalPage` (noindex). Hub/type indexes use `forPublicInformationalPage`.

---

## 9. Authorization

Canonical two-segment names (exactly one dot):

| Permission | Use |
|---|---|
| `publishing-publications.view` | Admin list/edit screen (including drafts) |
| `publishing-publications.create` | Create draft |
| `publishing-publications.update` | Edit fields (not publish) |
| `publishing-publications.publish` | Publish **and** unpublish |
| `publishing-publications.delete` | Delete drafts |

Public read of published items is not a Spatie permission.

**Role `editor`** is now seeded with CMS + heroes + media + the five publishing capabilities. It does **not** receive Membership or Knowledge capabilities. Authorization is permission/policy based, not `hasRole('editor')`.

`platform-admin` is still not seeded. `super-admin` retains `Gate::before`. `membership-admin` does not receive publishing.

Rejected names (not registered): `publishing.publications.publish`, `publishing-publications.*`.

---

## 10. CMS / route ownership

Reserved first segments added: `publikasi`, `berita`, `kegiatan`, `siaran-pers`, `liputan-media`.

Publishing type routes are registered **before** CMS shortcuts and the catch-all. `/about`, `/sermons`, `/events`, `/contact`, and `/keanggotaan*` remain their existing owners.

---

## 11. Public UI

- Hub `/publikasi` and type indexes with filter chips and `PublicationCard`.
- Detail page: type, date, title, excerpt, hero, sanitized body (`prose`), back link. No admin chrome. No public byline (`created_by` is internal).
- Homepage: optional latest-3 published cards (`LatestPublications`). `ServiceEvents` leftover is unchanged.
- Public nav: one **Publikasi** item → `/publikasi` (not separate Berita/Kegiatan header items).

---

## 12. Tests

- `tests/Feature/Publishing/PublishingWorkflowTest.php`
- `tests/Feature/Publishing/PublishingAuthorizationTest.php`
- `tests/Feature/Publishing/PublishingPublicDeliveryTest.php`
- `tests/Feature/Publishing/PublishingAclNamingTest.php`
- `tests/Feature/Publishing/PublishingTiptapTest.php`
- `tests/Feature/Publishing/PublishingMediaTest.php`
- `tests/Unit/Publishing/EditorialHtmlTest.php`
- `tests/Unit/Publishing/PublicationStatusTest.php`
- CMS reserved-path extensions in `CmsPathTest` and `CmsRoutingTest`

---

## 13. Deviations

1. **`EditorialHtml` is generic but only wired to Publication save.** **Closed in Phase 2B.1** — CMS Page save/render and Publication save/render share `EditorialContent`.
2. **Phase 1G editor shorthand includes `knowledge-articles.*`.** Phase 2B does not seed Knowledge permissions because that domain is out of scope.
3. **Empty body may be published.** Phase 1F requires title/slug, not content.
4. **Hard delete only for drafts.** Published rows must be unpublished first (1G “prefer unpublish”).
5. **No public author/byline.** `created_by` is staff attribution only.
6. **No `source_url` / clipping fields** for `liputan_media` (not in 1F schema).
7. **Slug uniqueness is table-wide**, so the same slug cannot exist under two types.
8. **`BaseModal` still has no complete focus trap** (Pre-Phase 2A-B). Publishing uses it via `ConfirmDialog`.
9. **Homepage `ServiceEvents` leftover** was not removed (not a homepage redesign).

---

## 14. Open business questions

These were not invented:

1. Extra fields for liputan media / siaran pers (external URL, source name, clipping).
2. Public byline / author display vs internal `created_by`.
3. Whether published items may ever be hard-deleted without unpublishing.
4. Whether empty body should be blocked on publish.
5. Whether `editor` should later receive `knowledge-articles.*`.
6. `/events` redirect target (`/publikasi` vs `/berita`) — deferred IA.
7. Categories/tags for Publication.
8. Scheduled publishing.
9. Whether CMS Page save should use the same `EditorialHtml` sanitizer.

No genuine Phase 1 contradiction blocked implementation.
