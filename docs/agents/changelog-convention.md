---
type: Decision Record
title: CHANGELOG Convention for Agent Work
description: How AI agents working on this codebase should update the root CHANGELOG file, and why this is a documented convention rather than a CI-enforced check.
tags: [changelog, agents, process]
timestamp: 2026-08-01T00:00:00Z
---

# CHANGELOG Convention for Agent Work

## Source file

[/CHANGELOG](/CHANGELOG)

## The convention

AI agents are now the primary development path for this repository (see
[Issue #20](https://github.com/AMSAT-NA/satellite-status/issues/20)). Any
agent that completes a unit of work — a bug fix, a feature, a config
change, anything a human would want to know happened — **must** add an
entry to the root `CHANGELOG` file as part of that work, in the same commit
or PR as the change itself.

An entry should include, at minimum:

- The date (`YYYY-MM-DD`)
- A one-line description of what changed and why (not just what files
  moved — the existing entries in the file are the style guide)
- The short commit SHA of the change, once known (see below)
- The issue number, if the work traces to one

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

## The commit-SHA nuance

An agent pushing a feature branch and opening a PR does not know the final
commit SHA the change will land on `main` under — a squash-merge or
rebase-merge produces a new SHA at merge time that doesn't exist yet when
the agent writes the CHANGELOG entry. Two acceptable options, in order of
preference:

1. **If the agent is present when the PR merges** (e.g. asked to merge it,
   or checks back later), update the CHANGELOG entry's SHA to the actual
   merge commit at that point.
2. **Otherwise**, use the short SHA of the branch's own HEAD commit (the
   one CI ran against) at the time the entry is written. This is still a
   real, resolvable commit — just not necessarily the one `main` ends up
   with after merge — and is far more useful than no SHA at all. Don't
   block on getting the "true final" SHA; that information isn't knowable
   at PR-creation time and waiting for it defeats the point of the entry
   existing before the work is considered done.

## Relationship to deploy-time versioning

This is a separate mechanism from the `APP_COMMIT_SHA` / `APP_DEPLOYED_AT`
values the CD pipeline stamps onto every deployed build (frontend footer,
`X-AMSAT-API-Commit` / `X-AMSAT-API-Deployed-At` response headers — see
`configure-env` in [.github/workflows/cd.yml](/.github/workflows/cd.yml)).
That mechanism answers "what commit is currently running in production,
right now" and is fully automatic. The CHANGELOG answers "what changed and
why, over time" and depends on agents actually writing it — the two aren't
substitutes for each other.
