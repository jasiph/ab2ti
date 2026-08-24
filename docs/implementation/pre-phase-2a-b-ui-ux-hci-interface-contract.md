# AB2TI 2.0 — Pre-Phase 2A-B UI/UX, HCI & Interface Contract

**Status:** Canonical interface contract for later business domains. Not a visual redesign. Not Membership/Publication/Award implementation.  
**Date:** 2026-08-20  
**Inputs:** Repository UI audit, Constitution, Architecture Baseline, Phase 1 IA (public labels), Pre-Phase 2A public delivery.

When a later feature would invent a new color, dialog, form pattern, or badge style, **this document wins** unless an ADR supersedes it.

Priority when goals conflict:

1. Understandability  
2. Accessibility  
3. Task completion  
4. Error prevention  
5. Readability  
6. Consistency  
7. Performance  
8. Visual refinement  

---

## 1. User context

AB2TI is used by farmers, seed farmers, association members, regional representatives, cooperative participants, administrators, content editors, and public visitors.

Digital literacy, device quality, network, age, and visual comfort vary. **Ease of use is a functional requirement.**

Implications:

- Prefer everyday Indonesian on public and member-facing surfaces.
- Prefer recognition over recall; visible labels; obvious next action.
- Design important public workflows for a small Android phone first.
- Do not assume hover, multi-window desktop, or fast broadband.
- One primary action per screen. Recoverable mistakes.

---

## 2. HCI principles adopted (applied, not cited)

| Principle | How AB2TI applies it |
|---|---|
| Visibility of status | Toasts for lightweight success; inline/page alerts for failures that need action; confirm dialogs only for irreversible work |
| Real-world language | Public/nav copy in user terms (`Tentang AB2TI`, `Berita`, `Teknologi Tani`). No “submit entity / invalid payload” |
| User control | Cancel, back, close (ESC on dialogs), preserve form input on validation failure |
| Consistency | Base components + tokens. Same filter/save/status patterns across modules |
| Error prevention | Confirm only when consequence is real. Required fields labeled. System infers what it already knows |
| Recognition not recall | Visible nav, page titles, status labels (not raw enums), searchable selects for long lists |
| Flexibility without complexity | Common actions first; advanced filters disclosed later |
| Minimalist design | Public: spacious. Admin: compact but not cramped. No decorative dashboards |
| Error recovery | Field-level messages; empty states with next step; retry |
| Contextual help | Helper text on non-obvious fields (especially personal data) |

Workflow questions (required before drawing a business screen):

What does the user want? What information is necessary? What must they decide? What can the system infer? Where can they err? How do we prevent that? How do they know it worked? How do they go back?

---

## 3. Current UI audit

| Area | Finding |
|---|---|
| Tokens | CSS variables in `resources/css/app.css`; Tailwind semantic colors. Brand green/gold/terracotta is coherent. Status colors were previously ad-hoc (`red-600`, `indigo-400`, `green-500`). |
| Fonts | Inter loaded from Google; Figtree also loaded and **unused**. Tailwind `font-sans` was the default system stack, so Inter was not actually applied. |
| Layouts | `FrontendLayout` public; `AppLayout` admin; Fortify auth cards. `WebLayout` unused. `GuestLayout` unused by auth pages. |
| Public nav | Sticky header, logo+wordmark, desktop links, hamburger &lt; `sm`. Labels already i18n (`Teknologi Tani`, `Berita`). Routes still `/sermons`, `/events` (IA debt). |
| Landing | Hero + welcome + leftover `ServiceEvents`. Hero can fall back to Unsplash. Default CTA `/login` is not a membership entry. |
| Admin | Sidebar + topbar; list pages use `BaseList` (cards) more than data tables. Users/Roles open **modals** for create/edit. |
| Feedback | `SystemToast` for flash; `useConfirm` + `ConfirmDialog` + `BaseModal`. |
| A11y gaps | Dual modal families; `BaseModal` had unlabeled close; `NavLink` used Jetstream indigo; error pages not branded; no focus trap library; `animate-pulse` on field errors. |
| Logo | `ApplicationLogo` (full mark, hardcoded SVG colors). `ApplicationMark` (triangle+wheat, `currentColor`). Wordmark had typo “Asosiasis”. Admin mobile sidebar leaked a stray `>`. |

---

## 4. Existing component inventory

Layer rule: **Base / Primitive → Shared / Application → Feature → Page / Layout**.

| Need | Canonical now | Status |
|---|---|---|
| Button | `BaseButton` | **KEEP** — variants `primary` `secondary` `danger` `outline` `ghost` |
| Jetstream `PrimaryButton` / `SecondaryButton` / `DangerButton` | Fortify/profile only | **DEPRECATE** — do not use on new AB2TI screens |
| Input | `BaseInput` (label + error) | **KEEP / STANDARDIZE** |
| File input | `BaseFileInput` | **KEEP** — added when public Membership intake needed a labeled file control |
| Select | `BaseSelect` | **KEEP** — wrap with a visible label at call site until `FormField` exists |
| Checkbox | Jetstream `Checkbox` | **KEEP** for now; restyle with tokens when next touched |
| Card | `BaseCard` | **KEEP** |
| List (admin, mobile-friendly) | `BaseList` | **KEEP** — preferred over dense tables for many admin queues |
| Pagination | `BasePagination` | **KEEP** |
| Dialog | `BaseModal` + `ConfirmDialog` | **KEEP** as canonical |
| Jetstream `Modal` / `DialogModal` / `ConfirmationModal` | Profile/API/Block picker | **DEPRECATE** for new work; migrate `BlockTemplatePickerModal` later |
| Toast | `SystemToast` | **KEEP** |
| Nav (public) | `NavLink` | **STANDARDIZE** (brand tokens; `aria-current`) |
| Nav (admin) | `ResponsiveNavLink` + sidebar | **KEEP** |
| Logo | `ApplicationLogo`, `ApplicationLogoWithText`, `ApplicationMark` | **KEEP** — do not redesign |
| Search select | `BaseSearchSelect` | Present, **no callers** — use when a long list needs search |
| `DashboardStatCard` | Unused | **DEPRECATE** until a real actionable dashboard |
| Status badge / FormField / EmptyState / Skeleton / Drawer / Breadcrumb / PageHeader | Not built | **ADD when first domain needs them** — do not invent unused primitives now |
| Tiptap `RichEditor` | CMS only | **KEEP** — not a public chrome component |

Feature components (e.g. future `MembershipApplicationStatusBadge`) map domain status onto a shared badge. They must not invent colors.

---

## 5. Design-language findings

**One brand, two densities.**

Public: open, editorial, branded, content-first, calm. Ivory background, generous padding (`max-w-7xl`, `px-4 sm:px-6 lg:px-8`), hero as identity not a widget wall.

Admin: task-oriented, predictable, denser, less decorative. Page header in `AppLayout` `#header`. `BaseList` + search + one primary button.

Do not force the same information density.

Avoid: visual clutter, clever hidden nav on desktop, tiny type, icon-only unfamiliar actions, modal-heavy long forms, jargon.

---

## 6. Logo usage

Canonical mark: existing SVG (`ApplicationLogo` / `ApplicationMark`). **Do not redesign, stretch, recolor, crop, or add shadows/effects.**

| Context | Component | Rule |
|---|---|---|
| Public header | `ApplicationLogoWithText` | Full logo **plus** wordmark. Logo height ~40px (`h-10`). Clear space ≈ 0.25× logo height. |
| Public footer / dark band | `ApplicationMark` on dark (`text-white`) | Mark may follow `currentColor`. Do not recolor wheat gold inside `ApplicationLogo`. |
| Admin sidebar | `ApplicationMark` `h-8` + “AB2TI 2.0” | Compact; still readable. |
| Auth card | `AuthenticationCardLogo` | Keep. |
| Favicon / app icon | Existing public favicon | Do not invent a new mark this phase. |

Minimum logo height: **32px** (`h-8`). Prefer **40px** in public header.

`ApplicationLogo` contains fixed brand fills (`#052`, gold). Do **not** apply `text-primary` / `fill-current` in a way that replaces those fills. `ApplicationMark` is the tintable variant.

---

## 7. Color-system decision

**Retain** the AB2TI agricultural palette (green / harvest gold / terracotta / ivory). Do not introduce a second palette.

| Token (Tailwind) | Role |
|---|---|
| `primary` / `primary-dark` | Brand / primary actions |
| `secondary` | Gold accent (identity, not body text) |
| `accent` | Terracotta accent (identity) |
| `background` | Page |
| `surface` / `surface-variant` (`surface-muted` alias) | Cards, chrome |
| `text-main` / `text-muted` | Content text |
| `border` | Dividers |
| `success` `warning` `danger` `info` | **Status only** |
| `focus` | Focus ring |

**Do not use `text-primary` for body copy.** In this Tailwind theme `text-primary` is brand green.

Status mapping (label + color; never color alone):

| Domain idea | Tone | Example label |
|---|---|---|
| Approved / published | `success` | Disetujui, Diterbitkan |
| Pending / review | `warning` | Menunggu, Sedang Ditinjau |
| Rejected / error | `danger` | Ditolak, Gagal |
| Draft / informational | `info` or `text-muted` | Draf, Informasi |

Features must not invent `bg-indigo-*` / one-off reds for status.

Gold `secondary` as **text on ivory** may fail AA; use it as accent/background, not long text.

---

## 8. Typography decision

**Retain Inter.** It has a large x-height, Indonesian-friendly Latin, many weights, and is already in the stack.

**Change made:** Inter is now `theme.fontFamily.sans`. Figtree was unused and was removed from the Blade font load (payload). Weight 300 was dropped (thin text is hard to read).

Hierarchy (Tailwind):

| Role | Classes |
|---|---|
| Display / hero | `text-3xl sm:text-4xl lg:text-5xl font-bold leading-tight` |
| H1 page title | `text-2xl sm:text-3xl font-bold text-text-main` |
| H2 | `text-xl font-semibold` |
| H3 | `text-lg font-semibold` |
| H4 | `text-base font-semibold` |
| Body | `text-base leading-relaxed` (16px root) |
| Small / helper | `text-sm text-text-muted` |
| Caption | `text-caption` or `text-sm` — **not** `text-[10px]` for essential copy |
| Label | `text-sm font-medium text-text-main` |
| Button | `text-sm font-medium` (not uppercase tracking-widest on Base) |
| Nav | `text-sm font-medium` |
| Table header | `text-sm font-semibold` |
| Validation | `text-sm text-danger` |

Avoid: 12px body, weight 300 for UI, all-caps paragraphs, line length much beyond ~65–75 characters for long-form (`max-w-3xl` / `max-w-prose` on articles). CMS `prose` class remains for HTML content.

---

## 9. Layout conventions

**Public page:** identity bar (optional) → sticky nav → main → footer. Content width `max-w-7xl`; long-form `max-w-3xl` or `max-w-5xl`.

**Admin page:**

```
Page header (title + optional description)
Primary action (one)
Toolbar (search / filters)
Main (list / form / detail)
Feedback (empty / loading / error)
```

Use breadcrumbs only when hierarchy is more than two levels (e.g. Program → Edition → Entry). Do not auto-breadcrumb every admin index.

---

## 10. Public navigation principles

Desktop: full primary nav visible. Do not hide it behind hover mega-menus.

Mobile (`< sm`): hamburger → sheet/sidebar. Targets ≥ 44px. No hover-only actions.

Current items (i18n already): Beranda, Tentang AB2TI, Teknologi Tani, Berita, Kontak. Login is secondary.

Later, when domains exist, prefer goal labels: **Keanggotaan**, **Berita**, **Teknologi Tani**, **Tentang AB2TI**, **Koperasi**. Do not add a top-level item per database table. Avoid more than ~6–7 primary links.

Footer: secondary groups (AB2TI, Keanggotaan, Publikasi, Teknologi Tani, Koperasi, Kontak, Legal) — only links that exist. Do not dump every URL. Placeholder `#program` links in the current footer are **legacy**; replace when IA pages exist.

Orientation: page `<title>` / H1, `aria-current` on nav, obvious back/home.

---

## 11. Admin screen & workflow rules

Start from the task, not the table.

Typical list: title, one primary (“User Baru”), search, `BaseList` rows with title + description + row actions.

Typical detail: title, status badge, description, primary action, secondary actions visually quieter.

Dashboard (when built): “what needs attention / what changed / what can I do now?” — not decorative charts. Do not pin every domain on one screen.

Users/Roles currently edit in **modals**. That is acceptable for short identity forms. **Do not** put membership applications, award entries, or long CMS-like forms in the same modal pattern — those are **full pages**.

---

## 12. Form conventions

Each field: **visible label**, control, optional helper, validation message. Placeholder is never the only label.

Required: `*` plus understandable copy. Collect only what the workflow needs. Group related fields. Split only when it reduces load.

Language (examples):

| Avoid | Prefer |
|---|---|
| Submit entity | Kirim Pendaftaran |
| Update resource | Simpan Perubahan |
| Invalid payload | Data belum lengkap |
| Are you sure? | Named consequence |

Validation: next to the field, keep input, no pulse animation, `role="alert"` on the message. Multiple errors: also a short summary at the top of long forms.

Significant public submissions (membership, award): review step; distinguish **Simpan / Kembali / Kirim**.

Multi-step only when it reduces load. Show current / done / remaining, whether data is saved, and that back is safe.

Data loss: warn or draft-save only for long/high-stakes forms — not globally.

---

## 13. Modality decision matrix

| Pattern | Use when |
|---|---|
| Inline | Simple, context must stay visible |
| Popover / dropdown | Small contextual choices |
| Dialog (`BaseModal` / `ConfirmDialog`) | Focused decision, short form, real confirmation |
| Drawer / sheet | Medium secondary context; list still useful (not built yet) |
| Full page | Long form, deep focus, shareable URL |

Destructive confirm only for delete / reject / revoke / irreversible submit. Explain consequence. No confirm for trivial reversible clicks.

Canonical dialog: `BaseModal`. ESC and backdrop close when `closeable`. Mobile: full-height sheet (`h-full` / `sm:rounded-xl`). Close control must have an accessible name.

---

## 14. Responsive / mobile rules

Breakpoints: Tailwind `sm` 640 / `md` 768 / `lg` 1024 / `xl` 1280.

| Element | Small | Desktop |
|---|---|---|
| Public nav | Hamburger | Inline links |
| Admin list | `BaseList` cards | Same list; extra columns via `#right` |
| True comparison tables | Cards or drill-down; horizontal scroll last | Table with few columns |
| Forms | Single column | Two columns only for short paired fields |
| Toolbar | Stack | Row |

Touch: `min-h-control` (44px) on `BaseButton` / `BaseInput` / `BaseSelect`. Do not pack icon-only actions.

Hero/slider motion must respect `prefers-reduced-motion` (global CSS now short-circuits transitions).

---

## 15. Table / list rules

Use a table only when comparing columns is the task. Prefer `BaseList` for queues (applications, users, files).

Desktop table: clear headers, numeric right-aligned, few columns, explicit row actions.

Mobile: do not default to sideways scroll. Prefer list/cards.

Empty `BaseList`: explain emptiness and next step (not a blank grid).

---

## 16. Feedback / state conventions

| Mechanism | Use |
|---|---|
| Toast (`SystemToast`) | Lightweight success (“Perubahan disimpan”) |
| Inline field error | Validation |
| Page alert | Persistent warning / failed save that must be read |
| Dialog | Decision / destructive confirm |
| Disabled submit + label | In-flight save (“Menyimpan…”) via `BaseButton` `loading` |
| Skeleton / local spinner | Partial load — do not block the whole admin chrome |

No silent success. No infinite spinner without copy.

Status: human labels (`Menunggu`, `Disetujui`, …), semantic token, optional icon.

Error pages (404 / 403 / 419 / 500): **not implemented as branded views yet** — deferred. Must be plain language, recovery link, AB2TI chrome, no stack traces.

---

## 17. Accessibility baseline

- Semantic HTML first; ARIA only when needed.
- Labels associated (`for` / `id` on `BaseInput`).
- Heading order: one H1 per page view.
- Keyboard: logical tab order; ESC closes dialogs; no mystery shortcuts.
- Visible `:focus-visible` (global + control rings).
- Dialog: `role="dialog"` `aria-modal="true"`; labeled close. **Focus trap / restore not fully implemented** — required before complex modal forms in Membership.
- Color is never the only meaning.
- Target WCAG AA for body text and controls.

---

## 18. Reusable-component governance

Before adding a component: search → reuse Base → extend Base if generic → feature component only for domain meaning → page last.

Do not copy a second button/modal/badge in a domain folder.

Jetstream primitives stay for Fortify until an explicit auth-UI pass. New AB2TI screens use Base.

---

## 19. Design tokens

Source: `resources/css/app.css` + `tailwind.config.js`. No separate token package.

| Category | Canonical |
|---|---|
| Color | CSS RGB channels + Tailwind `withOpacity` |
| Spacing | Tailwind scale; page padding `px-4 sm:px-6 lg:px-8` |
| Radius | `rounded-md` controls; `rounded-xl` cards/dialogs |
| Shadow | `shadow-sm` cards; `shadow-xl` dialogs |
| Type | Inter; sizes in §8 |
| Control height | `min-h-control` (2.75rem) |
| Breakpoints | Tailwind defaults |
| Focus | `--color-focus` / `ring-focus` |

Avoid magic hex in feature Vue. If a value repeats, add a token.

---

## 20. Legacy UI issues (known, not all fixed)

- Public routes `/sermons`, `/events` vs IA names.
- Footer `#program` placeholders; `bg-slate-900` vs token `primary-dark`.
- Hero Unsplash fallback and default CTA `/login`.
- `ServiceEvents` leftover on landing.
- Admin “CMS / Hero Manager / File Manager” jargon for editors.
- Dual modal/button families (Jetstream vs Base).
- Users/Roles in modals — do not copy for long domain forms.
- No branded 404/403/419/500.
- `BaseModal` focus trap incomplete.
- `WebLayout` unused.
- Logo `fill-current` on `ApplicationLogo` in header may fight hardcoded SVG fills — prefer not forcing fill on the full logo.
- i18n `general.json` maps `Home` → “Rumah” (wrong); navigation sources correctly use “Beranda”. Keep nav keys on the navigation source.

---

## 21. Changes made in this phase

- Semantic tokens: `success` `warning` `danger` `info` `focus`; `surface-muted` alias.
- Inter as `font-sans`; removed unused Figtree; dropped Inter 300.
- Base body type, `:focus-visible`, `prefers-reduced-motion`.
- `BaseButton` touch height; `danger` token; `BaseInput`/`BaseSelect` control height; field errors without pulse.
- `BaseModal` dialog semantics + named close.
- `NavLink` brand tokens + `aria-current`.
- Confirm defaults in Indonesian; confirm colors use tokens.
- `BaseList` empty copy.
- Logo wordmark typo “Asosiasis” → “Asosiasi”.
- Stray `>` in admin mobile sidebar.

No new UI framework. Logo not redesigned. No business-domain screens.

---

## 22. Deferred design work

- Branded error pages.
- `FormField`, `StatusBadge`, `EmptyState`, `Skeleton`, `Drawer`, `Breadcrumb`, `PageHeader` primitives when first needed.
- Migrate Fortify/Jetstream buttons and `BlockTemplatePickerModal` to Base.
- Full dialog focus trap.
- Public IA route rename (`/sermons`, `/events`).
- Landing hero CTA and Unsplash fallback.
- Footer token cleanup and real IA links.
- Auth UI pass (still Jetstream).
- Contrast lab measurements for gold-on-ivory in every state.
- Autosave (only for long public applications later).

---

## 23. Phase 2A readiness

Phase 2A may implement business UI **using this contract**. It must not invent a second design system.

First membership/public form: full page, labeled fields, Indonesian copy, review-before-submit if irreversible, `BaseButton` primary, tokens for status.

---

## Optional screen patterns (not implementations)

**Public landing:** identity, purpose, primary nav, one current highlight, a few entry points (keanggotaan, pengetahuan, berita, program). Whitespace. Not a dashboard.

**Admin list:** header, one primary, search, `BaseList`, pagination, empty explanation.

**Admin detail:** title, status, actions, content sections.

**Standard form:** grouped fields, Simpan secondary visual vs one primary submit, errors on fields.

**Multi-step:** numbered steps in user language; back without wiping.

**Confirmation:** title = action, body = consequence, confirm named (`Hapus`, `Tolak`), cancel `Batal`.

**Empty:** what/why/next.

**Error:** what happened, what to do, link home/nav.
