---
type: Decision Record
title: api-v2 Language and the v1 / v2 Dividing Line
description: Why api-v2 will be TypeScript, what work still goes into the PHP v1 API and frontend, and how v1 serves the status grid's full window (paging).
tags: [api, architecture, v2, versioning, agents]
timestamp: 2026-11-12T00:00:00Z
---

# api-v2 Language and the v1 / v2 Dividing Line

## 1. api-v2 will be written in TypeScript

- **Why:** frontend-v2 will be TypeScript, so one language covers the
  whole v2 stack. Pass-prediction (SGP4) code can be shared between the
  API and the browser. satellite.js v7 reads OMM records from the GP feed
  directly (`json2satrec`). OpenAPI can be generated from typed schemas,
  and contributors are easy to find.
- **Considered:** PHP (v1's language), Python and Rust. Rust was
  attractive, but its contributor pool is small for a volunteer project.
  Python was the runner-up.
- **Candidate stack (not final):** Fastify or Hono; Zod or TypeBox for
  schemas and OpenAPI; Kysely or Drizzle on mysql2; Vitest; a Node LTS
  release.

## 2. Dividing line between v1 and v2

**The v1 PHP API and frontend-v1 get only:**

- what frontend-v1's **public pages** need to stop querying MariaDB
  directly ([Issue #67](https://github.com/AMSAT-NA/satellite-status/issues/67));
- bug and security fixes.

**Everything new goes to api-v2.** That includes:

- aggregate and activity endpoints
  ([Issue #69](https://github.com/AMSAT-NA/satellite-status/issues/69));
- **the admin pages**. These are *not* moved onto v1 API endpoints.
  They'll be rebuilt as frontend-v2 admin pages on api-v2, and the
  frontend-v1 admin pages (`frontend/v1/admin/*`) will be retired when
  that ships. Until then, they keep their direct database access;
- member sign-in through WildApricot
  ([Issue #44](https://github.com/AMSAT-NA/satellite-status/issues/44)) for
  new features.

If a proposed v1 change fits neither bullet in the first list, it belongs
in api-v2. v1 stays on MINOR versions (`API_VERSION` in
`api/v1/lib/version.php`). The numbers don't run out: 1.9 is followed by
1.10.

## 3. Full-window retrieval for the v1 grid: paging

- The status grid shows today plus the previous 5 UTC calendar days,
  starting at 00:00 UTC (Issue #46). It needs every corrected report in
  that window, about 1,000 rows and more around launches, but
  `reports.php` returns at most 500.
- **Decision:** add cursor (keyset) paging to `reports.php`. It's an
  additive change, so a MINOR version bump. Each response is a complete
  JSON page with a `next` link, and the client keeps requesting until
  there's no `next`. Streaming was rejected: it's harder for third-party
  clients, Cloudflare and caching, and paged JSON is the standard pattern
  that api-v2 can reuse.
- The cursor is a report ID, and rows are ordered newest first, so
  reports arriving between pages don't cause skipped or repeated rows.
  The correction rule (latest per callsign per 15-minute period) is
  applied to the whole window before it's paged.
- `index.php` keeps its PHP logic for the grid, the plurality vote, the
  tooltips and "Reports From", using rows fetched from the API.
- Raising the cap (e.g. to 5,000) was the smaller alternative but keeps a
  fixed ceiling, so it was rejected.
