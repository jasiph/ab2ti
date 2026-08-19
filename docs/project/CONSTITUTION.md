# AB2TI 2.0 Project Constitution

Constitution Version: 1.0
Project Stage: Foundation
Status: Active
Adopted: 2026-08-19
Source of architecture facts: `docs/project/ARCHITECTURE_BASELINE.md`

This Constitution is the canonical engineering policy for this repository. Cursor Project Rules operationalize it. Rules MUST NOT silently override it.

---

## 1. PROJECT IDENTITY

AB2TI 2.0 is a **new business/domain application** built on the preserved Laravel + Inertia + Vue platform kernel imported from a previous system.

The preserved kernel MAY be reused: authentication, users/roles infrastructure, CMS pages, block templates, heroes, welcome section, file/media, i18n, admin/public layouts, and Base UI primitives that are actually in use.

Legacy Presbi/church business behavior is **not** part of AB2TI. That includes congregations/jemaat, church letters, song catalogs, church archives, incoming-letter administration, and jemaat import.

Cursor MUST NOT resurrect removed church-domain modules, tables, routes, permissions, or UI.

AB2TI domain modules (news, programs, membership, publications, and similar) MUST be introduced by explicit task + architecture decision, not by renaming leftover church concepts.

---

## 2. SOURCE-OF-TRUTH HIERARCHY

When instructions conflict, apply this order:

1. Explicit current task requirements (this chat / ticket)
2. This Project Constitution
3. Recorded Architecture Decision Records (`docs/project/decisions/`)
4. Cursor Project Rules (`.cursor/rules/`)
5. Existing repository architecture and patterns (`ARCHITECTURE_BASELINE.md` and code)
6. Existing implementation details

If a task requirement would violate this Constitution, Cursor MUST identify the conflict in the plan/report and MUST NOT silently violate the Constitution.

Facts about what the code currently does live in `ARCHITECTURE_BASELINE.md`. Desired changes live in ADRs and the Foundation Backlog until implemented.

---

## 3. AI / CURSOR OPERATING PROTOCOL

For non-trivial implementation work Cursor MUST follow:

**INSPECT → PLAN → IMPLEMENT → VALIDATE → REPORT**

- INSPECT: read the relevant existing code, routes, migrations, and tests before changing architecture.
- PLAN: state scope, files, risk (especially database/auth), and what will not be touched.
- IMPLEMENT: only the agreed scope.
- VALIDATE: run the quality commands that actually exist (see §14).
- REPORT: what changed, what was validated, known failures, residual risks.

Cursor MUST NOT invent modules, packages, database tables, permissions, routes, or abstractions merely because they seem convenient.

Cursor MUST prefer existing platform conventions unless an ADR explicitly changes them.

Cursor MUST NOT perform speculative refactoring or expand one task into unrelated cleanup.

Exploratory analysis MUST NOT produce unnecessary commits.

---

## 4. SCOPE CONTROL

Every development task has an explicit scope.

Out-of-scope problems discovered during implementation MUST be reported rather than silently fixed.

Cursor MAY fix an out-of-scope issue only when required for:

- correctness of the in-scope change,
- a security hole introduced or directly exercised by the in-scope change,
- build/test integrity of the in-scope change.

Any such exception MUST be reported in the same change.

---

## 5. DATABASE SAFETY

This section is mandatory.

The **canonical forward schema** is the set of migration files in `database/migrations`. Existing developer database contents are **not** canonical. They MAY contain historical church tables whose migrations were removed in Phase 0A.

Cursor MUST NOT:

- run `php artisan migrate:fresh` (or equivalent wipe) on an existing development, staging, or production database unless the user explicitly instructs it for a **disposable** environment;
- silently `DROP` legacy tables, including leftover church tables;
- delete developer data as part of ordinary feature work;
- edit an already-applied migration to rewrite history;
- run `migrate:rollback`, `migrate:reset`, `DROP DATABASE`, `TRUNCATE`, or unconstrained `DELETE FROM` unless explicitly instructed.

Cursor MUST:

- add **new** migrations for new schema changes;
- treat destructive schema (drop/rename/column removal) as requiring explicit review in the plan;
- distinguish data migrations from schema migrations when both are needed;
- consider backward compatibility of running apps;
- make migrations reversible where technically safe;
- validate migration behavior on an isolated/disposable database when the change has destructive risk (temporary SQLite file or equivalent), without modifying `.env` or the active development database.

Strategy for leftover church tables in developer DBs is a Foundation Backlog item. It MUST NOT be executed as a silent side effect of feature work.

---

## 6. BACKEND ARCHITECTURE

Current pattern: fat-ish controllers, inline validation, Eloquent models, Inertia responses. There is no Request/Policy/Service directory in use.

Cursor MUST follow that pattern unless an ADR introduces a new one.

### Controllers

Admin HTTP lives under `App\Http\Controllers\Admin`. Public CMS lives in `App\Http\Controllers\PageController`. Controllers SHOULD stay thin enough to test; they MUST NOT accumulate a second domain (do not mix File Manager with a future document archive).

### Validation

Until Form Requests are adopted by ADR, validation MUST remain server-side in the controller (or a subsequently introduced Form Request). Frontend validation is UX only.

### Models

Models are the data layer. `$fillable` (or equivalent mass-assignment protection) MUST be maintained. Business workflows that outgrow a model SHOULD move to an Action/Service **when that complexity actually appears**, not preemptively.

### Services / Actions

Do not add a repository or service layer “for architecture.” Fortify/Jetstream Actions already exist for auth. New Actions/Services MAY be added when a workflow has multiple models, transactions, or non-HTTP entry points.

### Transactions

Multi-step writes that must succeed or fail together MUST use a database transaction (existing Page/Hero media copies already do this).

### Query discipline

Avoid N+1 queries. Do not add `DB::raw` with user input. Prefer Eloquent/query builder.

### Routes

See §8. Route names for admin resources MUST stay under the `admin.` prefix.

### Dependencies

Do not add Composer packages to avoid a small amount of Laravel-native code.

---

## 7. AUTHENTICATION AND AUTHORIZATION

Authentication and authorization are separate concerns.

**Frontend menu visibility is not security.**
`usePermission` / `useNavigation` are UX. Protected operations MUST be authorized **server-side** (policy, `authorize()`, `can:` middleware, or equivalent) before they mutate or return sensitive data.

Current state: admin routes are only `auth` + `verified`. Completing server-side authorization is Foundation Hardening, not an excuse to skip it on new modules.

Future AB2TI modules MUST declare their authorization model (which permissions/roles gate which operations) in the implementation plan.

Cursor MUST NOT silently:

- enable or disable public registration,
- add or remove `MustVerifyEmail`,
- change Fortify/Jetstream feature flags,
- weaken `Gate::before` super-admin behavior without an explicit security decision.

Those items are in the Foundation Backlog until resolved.

`config/acl.php` remains the permission catalog. New permissions MUST be added there and synced with `acl:sync`. Cursor MUST NOT invent permission strings only in Vue.

---

## 8. ROUTING

`routes/web.php` is the application route file. Fortify/Jetstream register auth routes. `routes/api.php` is unused beyond Sanctum `/user`.

**Catch-all rule:** `GET /{slug}` (`pages.show`) MUST remain last among web GET routes. Explicit application routes MUST be registered before it.

The catch-all currently uses `where('slug', '.*')`. Tightening that constraint is Foundation Hardening. Until then, new explicit routes MUST be declared above the catch-all so they are not shadowed.

Legacy aliases `/sermons` and `/events` MUST NOT be renamed or removed in Phase 0B. They are unresolved information-architecture debt. No public URL changes without an explicit routing/IA decision.

Admin URLs stay under `/admin` with names `admin.*`. Dashboard exists at both `/dashboard` and `/admin/dashboard`; do not invent a third dashboard URL without an ADR.

---

## 9. FRONTEND ARCHITECTURE

Stack: Vue 3 (Options-free `<script setup>`), Inertia 2, Tailwind 3 utility-first, Ziggy `route()`, `trans()` / `$t()` from `laravel-vue-i18n`.

### Layouts

- Public pages MUST use `FrontendLayout` unless the task explicitly says otherwise.
- Admin pages MUST use `AppLayout`.
- Auth pages SHOULD keep the existing card-based Fortify views.

Do not introduce a new layout family without an ADR.

### Components

Prefer existing `Components/Base/*` that are **in use**. Do not promote `BaseSearchSelect` or `DashboardStatCard` to the design system until a real AB2TI use case reuses them.

Do not add a third-party UI kit. Do not duplicate a Base primitive (do not add another button family).

Pages own feature composition. Reusable pieces belong in `Components/`. Composables belong in `Composables/` when logic is shared.

### Forms

Use Inertia `useForm` and display server `errors`. Client-side checks MAY exist; they MUST NOT replace server validation.

### Permissions

Hide admin nav items with `usePermission` / `useNavigation` for UX. Still require server-side authorization for the corresponding endpoints.

### UX expectations for new UI

- Loading and error states for mutations that can fail.
- Responsive behavior consistent with existing `sm`/`md`/`lg` Tailwind usage.
- Accessible labels, buttons, and focus states; do not remove existing `sr-only` / `aria-*` patterns.
- Avoid page-specific one-off color systems; use semantic tokens (`primary`, `surface`, `text-main`, `border`) from `tailwind.config.js` / `app.css`.

---

## 10. DESIGN SYSTEM EVOLUTION

The existing AB2TI token set (forest green, harvest gold, terracotta, dark mode class strategy) is the initial visual foundation.

Future visual change MUST evolve those tokens or shared components, not by inventing a new palette on a single page.

A component becomes canonical only after actual reuse (two or more call sites, or an explicit design-system task). Avoid premature abstraction.

Do not “fix” Jetstream vs Base duplication unless the task is explicitly UI consolidation.

---

## 11. INTERNATIONALIZATION

Canonical system:

1. Add/edit strings in `lang/{locale}/sources/*.json`
2. Run `php artisan lang:merge {locale}` (id and en as needed)
3. Consume via `trans()` / `$t()`

Cursor MUST NOT introduce ad-hoc page-local translation maps, hardcoded user-facing copy for new features (except trivial brand names already used), or a second i18n library.

`lang/id.json` and `lang/en.json` are generated outputs of the merge workflow. Prefer updating sources then merging rather than hand-editing compiled files, except when a task explicitly requires a compiled-only fix and reports it.

Locales supported today: `id` and `en` only (enforced by `/language/{language}`).

---

## 12. MEDIA AND FILES

File Manager (`files` + Spatie Media collection `default`) is **platform infrastructure**. Future AB2TI domain documents MUST stay conceptually separate unless an ADR unifies them.

Spatie collections MUST be named intentionally (`page-cover`, `hero-backgrounds`, `default`). Do not dump unrelated files into an existing collection.

The Hero media collection registration bug is Foundation Hardening. Do not “while we’re here” rewrite media architecture during unrelated CMS work.

Uploads MUST be validated server-side (MIME/size). Frontend compression is optional UX.

---

## 13. SECURITY

Protected writes and sensitive reads MUST be authorized server-side.

Requests that create/update data MUST be validated. Mass assignment MUST go through `$fillable` / validated arrays, not `$request->all()` onto models.

Public endpoints (CMS pages, language switch, catch-all) MUST NOT leak admin data. Rate limiting already exists for login/2FA; additional public abuse controls belong to hardening, not silent feature work.

Upload endpoints MUST restrict type and size. Do not widen `store()` to accept arbitrary MIME types further.

Secrets (`APP_KEY`, DB passwords, AWS keys) MUST live in `.env`. `.env` MUST NOT be committed. Cursor MUST NOT print secret values in reports.

Do not leave debug scripts, `dd()`, `dump()`, or `console.log` debugging in completed work.

Do not set `APP_DEBUG` via committed files. Production error exposure is an operations concern; code MUST NOT add verbose exception pages for public users.

HTML from CMS (`v-html`) is an accepted current risk; sanitization is Foundation Hardening / a future ADR, not a drive-by rewrite.

---

## 14. TESTING AND QUALITY

Future features MUST include tests appropriate to the risk (at least a Feature test for authorization + validation of new mutating endpoints).

Bug fixes SHOULD include a regression test.

Existing failing tests MUST NOT be deleted or emptied to make the suite green. Known failures (FileManager restore/forceDelete, ExampleTest GET `/`) MUST be reported honestly until fixed in a dedicated task.

Tests SHOULD cover behavior, authorization, validation, and important edge cases—not merely that Inertia returned 200.

**Baseline commands that exist:**

- `php artisan test` or `composer test`
- `php artisan route:list` after route changes
- `npx vite build` after Vue/page/component changes (do **not** rely on `npm run build`)
- Isolated `DB_CONNECTION=sqlite DB_DATABASE=/tmp/...sqlite php artisan migrate --force` for migration-risk work

Do not invent npm/composer scripts. Laravel Pint MAY be used if already installed (`vendor/bin/pint`); there is no project `pint.json`. There is no ESLint/Prettier pipeline.

---

## 15. GIT WORKFLOW

This repository already exists. Cursor MUST NOT run `git init`.

Cursor MUST NOT:

- rewrite shared history (`rebase -i`, `reset --hard` of published commits, `commit --amend` of pushed commits) without explicit instruction;
- push unless explicitly instructed;
- force-push unless explicitly instructed;
- skip hooks unless explicitly instructed;
- commit `.env`, keys, or secrets.

Work MUST happen on purpose-specific branches. Recommended prefixes: `feat/`, `fix/`, `chore/`, `docs/`, `refactor/`, `test/`.

Commits MUST be logically scoped. Inspect `git status` and `git diff` before commit. Do not mix unrelated work into a feature commit.

Destructive Git operations require explicit user instruction.

---

## 16. DEPENDENCY MANAGEMENT

Do not add Composer or npm dependencies to avoid implementing a small capability already supported by Laravel/Vue/Tailwind/Spatie.

Before adding a dependency, the plan MUST:

- state the need,
- confirm no existing package already covers it,
- note maintenance/security impact,
- document it if it becomes an architectural dependency.

Do not remove `bacon/bacon-qr-code` in this governance phase. Its leftover status is Foundation Hardening.

Do not upgrade Laravel, Vue, Tailwind, or Vite as a side effect of feature work.

---

## 17. OBSERVABILITY AND ERROR HANDLING

Use Laravel logging (`Log` / reportable exceptions) unless a future ADR specifies otherwise.

Completed work MUST NOT leave `dd()`, `dump()`, `ray()`, leftover `console.log`, or root debug PHP scripts.

User-facing failures SHOULD use existing flash keys (`success`, `error`, `warning`, `info`) and Inertia error bags.

---

## 18. DEFINITION OF DONE

A feature is not done because a Vue page renders.

Before calling work complete, the change MUST:

1. Satisfy the stated requirements and stay in scope.
2. Authorize protected operations server-side (for new mutating/admin endpoints).
3. Validate all writes.
4. Add migrations only when the schema actually changes, following §5.
5. Include or update tests as required by §14.
6. Pass `npx vite build` if frontend files changed.
7. Leave `route:list` free of dangling controllers after route edits.
8. Use the canonical i18n workflow when user-facing strings were added.
9. Keep existing layouts/tokens; remain responsive at the breakpoints already used.
10. Preserve accessible names/labels where UI was added.
11. Contain no debug artifacts or secrets.
12. Have a reviewed `git diff` limited to the task.
13. Update `ARCHITECTURE_BASELINE.md` or add an ADR when architecture changed.

Known pre-existing test failures MAY remain if the task did not claim to fix them; they MUST be listed in the report.

---

## 19. ARCHITECTURE DECISIONS

Material architecture changes MUST be recorded as ADRs under `docs/project/decisions/`.

Material means: new persistence model, new authz model, new public URL scheme, new layout family, unifying File Manager with domain documents, introducing Form Requests/Policies as a standard, enabling/disabling registration or email verification, adding a significant Composer/npm dependency.

Trivial implementation choices (local variable names, extracting a 10-line helper, copy edits) MUST NOT generate ADRs.

Format: see `docs/project/decisions/README.md`.

---

## 20. CONSTITUTION AMENDMENT

Amendments MUST be:

1. Explicitly requested (or required by a recorded ADR that the user accepted).
2. Edited in this file with a version bump (1.0 → 1.1, etc.) and date.
3. Reflected in Cursor Rules only where operational behavior changes.
4. Committed as a `docs:` change, not mixed into a feature commit.

Cursor Rules MUST reference this Constitution. If a rule and this Constitution disagree, this Constitution wins until the rule is updated in the same governance change.

Cursor MUST NOT amend this Constitution as a side effect of feature implementation.
