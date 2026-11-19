---
type: Decision Record
title: api-v2 Stack
description: What api-v2 is built with (Node 26, Fastify with TypeBox schemas, Kysely on mysql2, Vitest) and why, plus how its lockfile and database types are kept honest.
tags: [api, api-v2, typescript, architecture, agents]
timestamp: 2026-11-17T00:00:00Z
---

# api-v2 Stack

api-v2 is TypeScript (see `api-v1-v2-dividing-line.md`). Decided 2026-11-17.

| Piece | Choice | v1 equivalent |
|---|---|---|
| Runtime | Node 26 (`node:26-bookworm-slim`) | `php:8.5-apache` |
| Web framework | Fastify | Apache + one PHP file per endpoint |
| Request/response schemas and OpenAPI | TypeBox, through Fastify | `report_input.php` + `openapi_spec.php` |
| Database access | Kysely, on the `mysql2` driver | mysqli prepared statements |
| Tests | Vitest, against a real MariaDB in CI | PHPUnit |

## Why

- **Node 26** is the current LTS line, supported to April 2029. The image
  is pinned to the major version, like the PHP images are pinned to the
  minor.
- **Fastify over Hono.** We run a long-lived server in Docker on azure193,
  which is Fastify's home ground. It validates requests and responses
  with the same schemas that generate the OpenAPI document, so the docs
  can't drift from the code. It also has mature cookie, session and
  rate-limit plugins, which #75's admin sign-in needs. Hono's main edge,
  running on edge platforms like Cloudflare Workers, doesn't apply.
- **TypeBox** is Fastify's native schema format and is plain JSON Schema,
  so it feeds OpenAPI directly and can be reused by frontend-v2.
- **Kysely over hand-written SQL or Drizzle.** Kysely is a typed SQL
  builder: queries read like SQL (window functions included), and
  TypeScript rejects an unknown table or column at compile time. Drizzle
  defines the schema in TypeScript and generates migrations, which would
  compete with `db/schema.sql` + `db/migrations/` as the source of truth
  that v1 also depends on.

## How integrity is enforced

- **Database types are generated, never written by hand.**
  `src/db/types.ts` is the output of `kysely-codegen` run against a
  database loaded with `db/schema.sql`. CI regenerates it and fails if it
  differs from the committed file, the same way `openapi.json` is checked
  for v1. A schema change therefore shows up as a TypeScript error
  wherever api-v2 uses the changed column.
- **`npm run typecheck`** runs strict TypeScript (`strict`,
  `noUncheckedIndexedAccess`, `exactOptionalPropertyTypes`) in CI.
- **UTC everywhere (Issue #24):** every connection runs
  `SET time_zone = '+00:00'` (Kysely's `onCreateConnection`), and mysql2
  reads and writes Dates as UTC. `test/db.test.ts` checks the session
  time zone.
- **The lockfile is committed** and CI installs with `npm ci`. Bootstrap
  only: if `package-lock.json` is ever missing, the CI job generates the
  lockfile and the database types, prints them to its log and fails, so
  they can be committed. Agents without registry access use that path.

## Production routing

api-v2 runs as its own container, `satellite-status-api-v2`, on azure193.
Traefik routes `status.amsat.org/api/v2/*` to it from the container's
labels (`deploy/docker-compose.yml`). Nothing changes in amsat-it-infra:
its Traefik only routes containers that set `traefik.enable=true` on the
`traefik-public` network, and has no central routes to edit.

- **Priority:** v1's router is `PathPrefix(/api)` at priority 20, which
  also matches `/api/v2`. api-v2's `PathPrefix(/api/v2)` router has
  priority 30, so it wins for `/api/v2/*`. Everything else under `/api`
  still goes to v1. The frontend is priority 10.
- **First deploy:** CD builds the api-v2 image when `api/v2/**` changed
  *or* when ACR doesn't have it yet. Otherwise `docker compose pull` would
  fail on the missing image.
- **After every deploy,** CD checks `${SITE_URL}/api/v2/health` through
  the public URL, which proves the Traefik routing as well as the
  container.

## Not decided here
- Linting (ESLint): left out for now. `typescript-eslint` doesn't yet
  support the latest TypeScript, and the strict typecheck covers the most
  important checks. Revisit when the codebase grows.
