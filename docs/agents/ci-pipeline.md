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
| `check-php-syntax` | php:alpine | Lints all PHP files with `php -l` |
| `check-php-version` | php:alpine | Confirms the PHP version in CI |
| `composer-platform` | php:cli | Validates Composer platform requirements |
| `phpunit` | php:cli + mariadb:11 | Runs PHPUnit integration tests |
| `playwright` | mcr.microsoft.com/playwright + mariadb:11 | Runs browser/E2E tests |

The Auto-DevOps stages (DAST, container scanning, canary, production rollout) were **not** ported —
they were never configured and relied on GitLab-specific infrastructure.

The placeholder `test` job (a bare `echo`) and the `container_scanning` manual job were dropped.

### PHP version is not pinned in CI

The PHP images in CI use untagged (latest-stable) variants: `php:alpine` and `php:cli`.

**Rationale:** The app is being moved from manual static file deployment (where the host PHP version
mattered) to Docker-based deployment. In a containerized world, the runtime PHP version is
controlled by the `Dockerfile`, not the host. CI should verify that the code is valid and tests
pass against current PHP, not mirror a soon-to-be-retired host version.

The `Dockerfile` (which currently uses `php:8.2-apache`) remains the authoritative source of the
production runtime environment.

### No PHP_VERSION workflow variable

A workflow-level `env.PHP_VERSION` variable was considered and rejected. GitHub Actions does not
make the `env` context available in `container.image` fields — only `vars` (repository UI variables),
`matrix`, and a small set of other contexts are permitted there. Introducing a `vars.PHP_VERSION`
repository variable would have moved a version pin out of the code and into the GitHub UI, adding
friction without benefit given the decision above to track latest PHP anyway.

### MariaDB service health checks

Both `phpunit` and `playwright` use MariaDB 11 as a service container. The health check uses
`healthcheck.sh --connect --innodb_initialized`, which is bundled in the MariaDB 11 image.
A secondary `until mysql ... do sleep 1; done` loop in the job steps guards against the rare case
where the health check passes before the user/database is fully provisioned.

### Branch protection

`main` is protected — all changes go through a branch and PR. CI runs on all branches and all
pull requests (trigger: `push: branches: ["**"]` and `pull_request: branches: ["**"]`).

## Open questions / future work

- CD (deployment pipeline) is not yet implemented. The `build` job produces a tarball artifact
  that is not yet consumed by anything automated.
- Consider pinning the Playwright image version once a regular update cadence is established.
- Evaluate whether `check-php-version` is worth keeping as a standalone job once the project is
  fully containerized (it becomes redundant with the Dockerfile).
