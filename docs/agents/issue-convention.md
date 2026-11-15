---
type: Convention
title: Issue Titles and Labels
description: Prefix issue titles with the part of the project they belong to, and apply the matching label.
tags: [issues, labels, process, agents]
timestamp: 2026-11-15T00:00:00Z
---

# Issue Titles and Labels

Prefix each issue title with the part of the project it belongs to, then a
colon, and apply the label of the same name:

| Prefix / label | Covers |
|---|---|
| `api-v1` | The PHP v1 API (`api/v1`) |
| `api-v2` | The TypeScript v2 API (`api-v2`) |
| `frontend-v1` | The PHP v1 frontend (`frontend/v1`) |
| `frontend-v2` | The v2 frontend (`frontend-v2`) |

Example: `frontend-v2: Add field for comments`, labeled `frontend-v2`.

- **Work that spans two parts** gets the prefix of the part that delivers
  it, and both labels. For example, member sign-in (#44) is prefixed
  `frontend-v2` and labeled `frontend-v2` and `api-v2`.
- **Work outside these four** (the shared database schema, CI, the
  container images) doesn't have a prefix yet. Ask before adding a new
  prefix, and create its label at the same time.
- The usual GitHub labels (`bug`, `enhancement`, `documentation` and so
  on) still apply alongside these.
- Why: one glance at the issue list shows which stack a task belongs to.
  That matters more now that v1 and v2 are separate stacks with different
  rules about what work they get (`api-v1-v2-dividing-line.md`).
