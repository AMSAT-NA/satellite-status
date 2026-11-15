---
type: Convention
title: Issue Titles and Labels
description: Prefix issue titles with the part of the project they belong to (api-v1, api-v2, frontend-v1, frontend-v2, db, docs), and apply the matching label.
tags: [issues, labels, process, agents]
timestamp: 2026-11-15T00:00:00Z
---

# Issue Titles and Labels

Prefix each issue title with the part of the project it belongs to, then a
colon, and apply the matching label (the same name, except `docs`). Each label exists in the repo:

| Prefix / label | Covers |
|---|---|
| `api-v1` | The PHP v1 API (`api/v1`) |
| `api-v2` | The TypeScript v2 API (`api-v2`) |
| `frontend-v1` | The PHP v1 frontend (`frontend/v1`) |
| `frontend-v2` | The v2 frontend (`frontend-v2`) |
| `db` | The shared database: schema, migrations and catalog data (`db/`) |
| `docs` | Documentation only. Uses GitHub's existing `documentation` label |

Example: `frontend-v2: Add field for comments`, labeled `frontend-v2`.

- **Work that spans two parts** gets the prefix of the part that delivers
  it, and both labels. For example, member sign-in (#44) is prefixed
  `frontend-v2` and labeled `frontend-v2` and `api-v2`.
- **Work that touches several parts without belonging to one**, such as
  a PHP base-image bump (#37), gets no prefix, and is labeled with every
  part it affects (`api-v1` and `frontend-v1`).
- **Bugs** keep the prefix of where the bug is, plus GitHub's existing
  `bug` label, e.g. `api-v1: reports.php returns 500 on an empty since`,
  labeled `api-v1` and `bug`. The prefix says where; the label says what
  kind. There is no `bug:` or `fix:` prefix.
- The other usual GitHub labels (`enhancement`, `documentation` and so
  on) apply alongside these too.
- Ask before adding a new prefix, and create its label at the same time.
- Why: one glance at the issue list shows which stack a task belongs to.
  That matters more now that v1 and v2 are separate stacks with different
  rules about what work they get (`api-v1-v2-dividing-line.md`).
