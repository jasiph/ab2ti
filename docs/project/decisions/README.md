# Architecture Decision Records

ADRs record **material** architecture choices for AB2TI 2.0.

Canonical policy: `docs/project/CONSTITUTION.md` §19.

## When to write an ADR

Write one when changing:

- persistence models or public URL schemes
- authentication/authorization product behavior
- layout family or design-system adoption
- File Manager vs domain-document boundaries
- whether Form Requests/Policies become the default
- a significant Composer/npm dependency

Do **not** write an ADR for trivial implementation details.

## Format

Files live here as `NNNN-short-kebab-title.md`.

```markdown
# NNNN — Title

Date: YYYY-MM-DD
Status: Accepted | Proposed | Superseded
Supersedes: (optional)

## Context
## Decision
## Consequences
```

Number from `0001` upward. Do not rewrite accepted ADRs; supersede them.

## Index

| ID | Title | Status |
|---|---|---|
| 0001 | Retain cleaned Laravel/Vue kernel; Constitution is governance | Accepted |
| 0002 | Disable public self-registration | Accepted |
| 0003 | Reserve application paths from CMS | Accepted |
| 0004 | Public delivery SEO and social sharing | Accepted |
| 0005 | Retire Presbi `/sermons` and `/events` aliases | Accepted |
