---
type: Decision Record
title: CI Pipeline — GitHub Actions
description: Design decisions made when migrating CI from GitLab to GitHub Actions and establishing the ongoing CI strategy.
tags: [ci, github-actions, php, testing]
timestamp: 2026-06-17T00:00:00Z
---

# CI Pipeline — GitHub Actions

This document records the key decisions made when establishing the GitHub Actions CI pipeline
for this project, migrated from GitLab CI.

## Source file

[.github/workflows/ci.yml](/.github/workflows/ci.yml)

## Decisions

### What was carried forward from GitLab CI

The following jobs were translated directly:

| Job | Image | Purpose |
|---|---|---|
| `build` | ubuntu-latest | Creates a deployable tarball artifact |
| `check-php-syntax` | php:X-alpine | Lints all PHP files with `php -l` |
| `check-php-version` | ubuntu-latest | Reads X, the deploy PHP version, from the Dockerfiles (see below) |
| `check-gpdata-feed` | php:X-cli | `GPDATA_FEED_URL` repo variable is set, https, and serves a valid GP feed (#25) |
| `composer-platform` | php:X-cli | Validates Composer platform requirements |
| `phpunit` | php:X-cli + mariadb:11 | Runs PHPUnit integration tests |
| `playwright` | php:X-cli + mariadb:11 (Node and Chromium installed in the job) | Runs browser/E2E tests |

The Auto-DevOps stages (DAST, container scanning, canary, production rollout) were **not** ported —
they were never configured and relied on GitLab-specific infrastructure.

The placeholder `test` job (a bare `echo`) and the `container_scanning` manual job were dropped.

### CI runs the deploy PHP version (Nov 2026)

Every PHP job runs on the PHP `MAJOR.MINOR` the deploy images use. `check-php-version` reads it
from `FROM php:X-apache` in `api/Dockerfile` and `frontend/v1/Dockerfile`, fails if the two differ,
and passes `X` on as a job output. The other jobs use `php:X-cli` / `php:X-alpine` via
`needs.check-php-version.outputs.php`; `needs` is one of the contexts GitHub allows in
`container.image`.

**Why:** the Dockerfiles have pinned the minor version since Oct 2026 (`php:8.5-apache`), but CI
still used floating images (`php:cli`, `php:alpine`) and the Playwright job used Ubuntu's PHP 8.3.
So CI tested neither what production ran nor one consistent version. PR #65 hit this directly:
`http_get_last_response_headers()` (PHP 8.4+) passed PHPUnit on 8.5 and failed Playwright on 8.3.

**Upgrading PHP** is now only the two `FROM` lines; CI follows automatically, so a PHP bump PR is
tested on the new version before it merges.

This replaces the earlier decision (when the Dockerfile used a floating `php:apache`) to track
latest PHP in CI. That decision's rationale, that the Dockerfile is the authority on the runtime
version, still holds; CI now follows it exactly. No `PHP_VERSION` variable or repository setting is
involved: the version lives only in the Dockerfiles.

### MariaDB service health checks

Both `phpunit` and `playwright` use MariaDB 11 as a service container. The health check uses
`healthcheck.sh --connect --innodb_initialized`, which is bundled in the MariaDB 11 image.
A secondary `until mysql ... do sleep 1; done` loop in the job steps guards against the rare case
where the health check passes before the user/database is fully provisioned.

### Branch protection

`main` is protected — all changes go through a branch and PR. CI runs on all branches and all
pull requests (trigger: `push: branches: ["**"]` and `pull_request: branches: ["**"]`).

## Open questions / future work

- Consider pinning the Playwright image version once a regular update cadence is established.
- Evaluate whether `check-php-version` is worth keeping as a standalone job once the project is
  fully containerized (it becomes redundant with the Dockerfile).

## Update: CD now exists

The line above about CD not being implemented is no longer true — it shipped in
[.github/workflows/cd.yml](/.github/workflows/cd.yml) and has deployed to production (azure193)
multiple times since. The `build` job's tarball artifact predates CD and remains unconsumed by it;
CD builds and pushes its own Docker images independently rather than using that artifact.

Full CD decision history (trigger scope, path-gating, ACR push, Traefik integration, `SITE_URL`
handling, deploy-time versioning) lives in [CD_NOTES.md](/CD_NOTES.md) and
[RESTRUCTURE_NOTES.md](/RESTRUCTURE_NOTES.md) at the repo root rather than duplicated here — this
bundle covers judgment calls specific to the CI *gate* (this doc) and to deploy-time versioning
(see [deploy-versioning.md](deploy-versioning.md)); the root-level notes files are the CD system of
record.
