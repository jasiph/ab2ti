# AB2TI 2.0 — Phase 2B.1 Editorial Content Safety & Template Blocks

**Status:** Implemented  
**Date:** 2026-08-20  
**Does not reopen:** Phase 2A Membership. Completes the Phase 2B documented gap on the shared editorial trust boundary. Does not begin Phase 2C.

---

## 1. Previous content flow

### CMS Page

- Stored as Tiptap **HTML** on `pages.content`.
- Admin save wrote the editor string with **no sanitization**.
- Public `PageController@show` passed the Eloquent model; `Frontend/Page/Show.vue` used `v-html` of `page.content`.
- The public renderer **trusted stored HTML**.

### Publication

- Stored as Tiptap **HTML** on `publications.content`.
- Admin save ran `EditorialHtml::sanitize()` (Phase 2B).
- Public detail `v-html` of shaped `publication.content` (already sanitized if saved through the UI).
- Factory/tinker/legacy rows could still hold unsanitized HTML until resave.

### Tiptap / RichEditor

- Source of truth is **HTML**, not JSON.
- Extensions: StarterKit, Image, `GlobalDiv`, `GlobalAttributes`, `DynamicBlock`.
- Images insert File Manager **URLs**, not Media IDs.
- Code view lets staff paste HTML; that is editor UX, not the security boundary.

### Template Blocks (existing architecture)

`block_templates` is a **named HTML snippet library**, not a JSON attribute schema.

Editor representation:

```html
<div class="tiptap-block-wrapper" data-block-type="{name}">
  <div class="tiptap-block-content">{snippet HTML}</div>
</div>
```

Before this phase, `RichEditor` **unwrapped** those nodes on `v-model` emit, so stored content lost block identity. Public output was flattened HTML. There was **no** allow-list registry, **no** trusted renderer, and snippet HTML was unsanitized in the library table.

Seeded snippets (the actual existing blocks):

1. Highlight Card (Android Style)
2. Beautiful Image with Caption
3. Two Columns Feature

No video/iframe/gallery-by-Media-ID blocks exist. No PHP class is resolved from `data-block-type`.

---

## 2. Identified trust boundary

The trust boundary is **server-side** `App\Support\Editorial\EditorialContent` (`normalize` on write, `render` on output).

Vue `v-html`, Tiptap, and the block picker are not the security boundary.

---

## 3. Final shared pipeline

```
Editor / Tiptap HTML
        ↓
EditorialContent::normalize  (save: Page, Publication, BlockTemplate)
        ↓
  TemplateBlockRegistry.allows(data-block-type)?
        ├── yes → lock wrapper attrs → sanitize inner HTML (EditorialHtml)
        └── no  → drop block identity → sanitize as free-form
        ↓
Stored HTML (wrappers preserved when recognized)
        ↓
EditorialContent::render     (public CMS/Publication; also admin editor load)
        ↓
v-html of pipeline output
```

`normalize` and `render` use the same process so security does not depend on resave.

---

## 4. EditorialHtml responsibility

`App\Support\Html\EditorialHtml` is the **generic free-form sanitizer** only.

It does:

- allow-list tags/attributes for standard Tiptap + existing snippet layout (`div`/`class`, headings, lists, links, images, figure, **svg/path** subset)
- strip `script`, event handlers (`on*`), `iframe`, `style`, `javascript:` / non-http(s)|mailto URLs

It does **not**:

- know Publication vs CMS
- decide which Template Blocks are legal
- invoke renderers
- resolve Media IDs

---

## 5. Template Block responsibility

Existing blocks remain **snippet blocks**: staff-editable HTML inside a platform wrapper.

The platform:

- recognizes wrappers (`div.tiptap-block-wrapper`)
- allow-lists `data-block-type` via `TemplateBlockRegistry`
- rewrites the wrapper to **only** `class="tiptap-block-wrapper"` + `data-block-type`
- sanitizes inner HTML
- never interpolates the type into a PHP/Vue class name

A future first-class block (e.g. YouTube) must be added as an **explicit** registry entry plus a trusted renderer that builds any iframe internally. That renderer does not exist yet because no such block exists.

---

## 6. Registry / resolver

`App\Support\Editorial\TemplateBlockRegistry`

- Explicit `PLATFORM_TYPES` (seeded names + short aliases + Tiptap default `Block Content`)
- Plus **active** `block_templates.name` rows (so the existing library remains the extension model)
- `normalizeType()` rejects `\`, `/`, `::`, `.`, and over-long/odd strings
- Optional `$context` argument is unused (reserved for later `editorial.cms` / `editorial.publication` profiles)

Unknown types: identity stripped; inner HTML sanitized as free-form. No class loader.

---

## 7. Block validation

For this representation, validation is:

- type allow-listed and normalized
- wrapper/content containers may not carry arbitrary attributes (`data-disk`, `data-object-key`, `onclick`, renderer names)
- inner HTML uses EditorialHtml (no raw iframe, no script, no JS URLs)
- images/links: URL policy only (http/https/mailto or site-relative `/` `#`)

Current snippets do **not** store Media IDs. URLs in `<img src>` are sanitized, not resolved through File Manager. Future blocks that need media **should** use Media IDs; that is documented as extension guidance, not invented here.

---

## 8. CMS integration

- Admin Page store/update: `normalize`
- Admin Page edit load: in-memory `normalize` (not persisted) so the editor is not a weaker preview
- Public `PageController@show`: `render` into `page.content` before Inertia
- BlockTemplate store/update + active picker JSON: `normalize`

No bulk `pages` rewrite.

---

## 9. Publication integration

- Admin store/update: `EditorialContent::normalize` (replaces direct `EditorialHtml::sanitize`)
- Admin form props: `normalize` of stored body
- Public detail props: `render`

---

## 10. Legacy content strategy

- **No** mass UPDATE of `pages` / `publications` / `block_templates`.
- Public (and admin editor load) always run `render`/`normalize`.
- Next real save persists the cleaned HTML (lazy normalization).
- Security does not wait for that save.

---

## 11. Rendering strategy

Public Vue still uses `v-html`. The HTML it receives has already gone through the pipeline. Admin “view public page” hits the same public renderer. There is no separate raw-HTML preview route.

`RichEditor` now **keeps** `tiptap-block-wrapper` in `v-model` so block identity can round-trip.

---

## 12. Security invariants

- `<script>` cannot execute (stripped)
- `on*` cannot execute (stripped)
- `javascript:` URLs cannot execute (stripped)
- Arbitrary `<iframe>` cannot appear in free-form or snippet inner HTML
- `data-block-type` cannot select a PHP/Vue class
- Wrapper attributes cannot inject disk/object paths
- Unknown block types fail closed (no trusted-wrapper claim)
- Legacy rows are safe on read

---

## 13. Tests

- `tests/Unit/Editorial/EditorialContentTest.php`
- `tests/Unit/Editorial/TemplateBlockRegistryTest.php`
- `tests/Unit/Publishing/EditorialHtmlTest.php`
- `tests/Feature/Cms/CmsEditorialContentTest.php`
- Existing Publishing Tiptap/workflow tests remain the Publication regression set

---

## 14. Deviations

1. Template Blocks remain **HTML snippets**, not JSON `{ template, data.mediaIds }` nodes. That is the repository’s established representation; a second block system was not added.
2. No Media-ID gallery/video block was invented. Image snippets still use sanitized URLs.
3. Inline `style` from Tiptap `GlobalAttributes` is stripped on the server (XSS surface). Tailwind `class` on allowed tags is kept.
4. Welcome Section is plain text interpolation, not this pipeline.
5. Jetstream Privacy/Terms `v-html` and pagination `v-html` of link labels were left unchanged (not editorial CMS/Publication content).

---

## 15. Future extension guidance

Preference order:

1. Standard Tiptap node
2. Existing approved Template Block (snippet library)
3. New reusable approved Template Block — add an explicit registry key and, if it needs HTML the sanitizer must not allow (e.g. iframe), a **trusted renderer** keyed in code (never `app("App\\Blocks\\{$input}")`)
4. Raw HTML only as an explicit later capability

Do not loosen global `EditorialHtml` to admit a new presentation. Add a structured/trusted block instead.

Capability profiles (`editorial.cms` / `editorial.publication` / `editorial.knowledge`) are not built yet; `TemplateBlockRegistry::allows()` already accepts an unused `$context` for that later split. Knowledge blocks are out of scope.
