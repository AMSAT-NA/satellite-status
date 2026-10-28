---
type: Decision Record
title: CHANGELOG Convention for Agent Work
description: How AI agents working on this codebase should update the root CHANGELOG file, and why this is a documented convention rather than a CI-enforced check.
tags: [changelog, agents, process]
timestamp: 2026-10-27T00:00:00Z
---

# CHANGELOG Convention for Agent Work

## Source file

[/CHANGELOG](/CHANGELOG)

## The convention

AI agents are now the primary development path for this repository (see
[Issue #20](https://github.com/AMSAT-NA/satellite-status/issues/20)). Any
agent that completes a unit of work — a bug fix, a feature, a config
change, anything a human would want to know happened — **must** add an
entry to the root `CHANGELOG` file as part of that work, in the same PR as
the change itself. **Every PR gets an entry unless the person directing
the work explicitly says otherwise** for that PR.

An entry should include, at minimum:

- The date (`YYYY-MM-DD`)
- A one-line description of what changed and why (not just what files
  moved — the existing entries in the file are the style guide)
- **The PR number** — required, e.g. `(PR #40)` (see "Referencing the
  change" below for why this is the PR number and not a commit SHA)
- The issue number, if the work traces to one, e.g. `(Issue #38, PR #40)`

Follow the existing file's format — a flat dated list, oldest to newest.
Don't restructure it into something else (front matter, JSON, per-entry
files) as part of an unrelated change.

## Why this is a *convention*, not a CI check

This was a deliberate choice, made when this doc was written: **no CI job
enforces a CHANGELOG update.** A required-file-changed check (e.g. "fail the
PR if `CHANGELOG` wasn't touched") was considered and rejected — it's easy
to satisfy with a meaningless entry, adds a CI job to maintain, and doesn't
actually guarantee a *useful* entry, only a present one. A documented
expectation that agents are instructed to follow (via this doc, and via
CLAUDE.md / AGENTS.md-equivalent instructions pointing here) achieves the
same goal without the false confidence of a green checkmark that doesn't
verify quality.

If CHANGELOG entries turn out to be unreliable in practice despite this
convention, revisit this decision — but start here.

## Referencing the change: PR number, not commit SHA

Entries reference the change by **PR number**. A commit SHA is not
required.

The reason is that a commit SHA can't be known when the entry is written:

- A commit can't contain its own SHA, because the SHA is a hash of the
  commit's contents.
- This repo squash-merges. Squashing creates a brand-new commit on `main`
  at merge time, so any SHA written inside the PR (the branch HEAD, or a
  separate "dev work" commit) no longer appears in `main`'s history once
  it lands. Putting the CHANGELOG update in its own commit doesn't
  change this.

The PR number has none of these problems. It exists as soon as the PR is
opened, never changes, links straight to the merged commit on GitHub, and
survives squash, rebase, and merge-commit merges alike. Squash merges also
put `(#N)` in the commit title, so the SHA is still one command away:

```bash
git log --oneline --grep '(#40)'
```

**Workflow:** open the PR first. Then add, or finish, the CHANGELOG entry
with the now-known PR number and push it to the same branch before
merge. Don't guess the next PR number in advance; a concurrently opened
issue or PR takes it.

**SHAs are optional extras.** Add one only when it's already known and
final, i.e. when the entry is written *after* the change merged, as in
backfilled entries. Never record a pre-merge branch SHA. (This replaces
the earlier guidance to fall back to the branch HEAD SHA, which ends up
pointing at a commit that isn't on `main` after a squash merge.)

**Merging two open PRs that both touch the CHANGELOG:** both append at the
end of the file, so the second one to merge will conflict in `CHANGELOG`
(only there). Rebase it onto `main` after the first merges, keep both
entries (same-date entries become bullets under one date line), and make
sure the file still ends with a newline.

## Relationship to deploy-time versioning

This is a separate mechanism from the `APP_COMMIT_SHA` / `APP_DEPLOYED_AT`
values the CD pipeline stamps onto every deployed build (frontend footer,
`X-AMSAT-API-Commit` / `X-AMSAT-API-Deployed-At` response headers — see
`configure-env` in [.github/workflows/cd.yml](/.github/workflows/cd.yml)).
That mechanism answers "what commit is currently running in production,
right now" and is fully automatic. The CHANGELOG answers "what changed and
why, over time" and depends on agents actually writing it — the two aren't
substitutes for each other.
