---
type: Decision Record
title: Deploy-Time Versioning (commit SHA, deploy timestamp)
description: Design decisions for surfacing "what commit is currently running" on the frontend and API, made in response to Issue #20.
tags: [cd, versioning, api, frontend]
timestamp: 2026-08-01T00:00:00Z
---

# Deploy-Time Versioning

Implements [Issue #20](https://github.com/AMSAT-NA/satellite-status/issues/20):
there was no way to tell, from the running site or API, what commit was
actually deployed or when. This records the decisions made closing that gap.

## Source files

- [.github/workflows/cd.yml](/.github/workflows/cd.yml) — `configure-env` job generates the values
- [frontend/v1/config.php](/frontend/v1/config.php), [api/v1/config.php](/api/v1/config.php) — read them
- [frontend/v1/index.php](/frontend/v1/index.php) — footer display
- [api/v1/lib/bootstrap.php](/api/v1/lib/bootstrap.php) — `api_send_version_headers()`

## Where the values come from: computed at CD run time, not a new repo variable

`APP_COMMIT_SHA` and `APP_DEPLOYED_AT` are **not** GitHub repo/org Secrets or
Variables. They're generated inline in the `configure-env` job's "Generate
.env from secrets and variables" step, from information the workflow run
already has automatically:

- `APP_COMMIT_SHA` = `${{ github.sha }}` — the commit that triggered this
  workflow run. This is the standard GitHub Actions commit identifier: a
  40-character SHA-1 hex string. (GitHub does not use SHA-256 for commit
  hashes today — Git's SHA-256 object format exists but isn't what
  `github.sha`, `git rev-parse`, or GitHub's UI use for a repo like this
  one. "SHA-1, GitHub's standard" is the correct target, not SHA-256.)
- `APP_DEPLOYED_AT` = `$(date -u +%Y-%m-%dT%H:%M:%SZ)`, computed by the
  runner shell at the moment `configure-env` executes.

Adding a new persistent `vars.APP_COMMIT_SHA`-style repo Variable was
considered and rejected: it would need to be written by the same workflow
run that wants to read it, adding an API round-trip (`gh variable set` or
equivalent) for a value the run already holds in `github.sha` for free. Repo
Variables are for values that persist *across* runs and change rarely
(`vars.SITE_URL`, `vars.MYSQL_HOST`); a value that's different on every
single push is the wrong shape for that mechanism.

Both values flow to the containers the same way `SITE_URL` and the MySQL
credentials already do — written into the generated `.env`, shipped via
`scp`, and picked up by both `frontend` and `api` via `env_file: .env` in
`deploy/docker-compose.yml`. No new deploy step was needed.

## Optional everywhere: local dev has no deploy, so these are just absent

`frontend/v1/config.php` and `api/v1/config.php` read both as
`getenv(...) ?: null` — there is no placeholder-string default (unlike
`$siteUrl`'s `"[SITEURL]"`) because there's no sensible fake commit SHA to
show. Local Docker dev and CI's PHPUnit/Playwright test config
(`tests/fixtures/config.test.php`) never set these, so they're `null` there,
and both the frontend footer and the API headers are written to check for
`null` and simply omit themselves rather than render `null`, an empty
string, or a placeholder. A local dev run showing no "last updated" line is
correct — nothing was deployed.

## API: headers, not response body fields

Issue #20 explicitly asked to "minimize the amount of (meta)data included in
API results." The existing `X-AMSAT-API-Version` response header (a static
`API_VERSION` constant, unrelated to this feature — see `bootstrap.php`) was
already the precedent for exposing API metadata via headers instead of a
field repeated in every JSON payload. `X-AMSAT-API-Commit` (short SHA,
7 characters — matching GitHub's own short-SHA display convention) and
`X-AMSAT-API-Deployed-At` (full ISO 8601 UTC timestamp) follow the same
pattern, added in one place (`api_send_version_headers()`) and called from
both `api_json_response()` and the legacy `api_legacy_json_response()` path,
so every API response carries them without duplicating the header-writing
logic per endpoint.

## Frontend: footer line, short SHA, UTC

`frontend/v1/index.php` renders `Last updated {mm/dd/yyyy hh:mm} UTC (commit
{short SHA})` in the page footer, matching the format requested in Issue #20.
Displayed in UTC (not server-local time) to match the rest of the app's time
handling — the database schema uses `UTC_DATE()` / `UTC_TIME()`
throughout, and the API's `health.php` timestamp is UTC as well.

## Out of scope for this change

- No change to `API_VERSION` (the existing semantic-version constant) — this
  is a separate concept (API contract version vs. "what commit is live")
  and both now coexist.
- No CHANGELOG CI enforcement — see
  [changelog-convention.md](changelog-convention.md) for why that's a
  documented convention instead.
