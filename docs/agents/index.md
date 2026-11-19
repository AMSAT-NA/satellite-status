# Agents — Design Decisions & Guidance

Concept documents in this directory record decisions made about how this project is built, tested,
and deployed. They give future agents (and humans) the *why* behind choices that aren't obvious
from reading the code or git history alone.

# Concepts

* [ci-pipeline.md](ci-pipeline.md) - GitHub Actions CI pipeline design decisions
* [deploy-versioning.md](deploy-versioning.md) - Deploy-time commit SHA / timestamp surfaced on the frontend and API
* [changelog-convention.md](changelog-convention.md) - How agents should update the root CHANGELOG, and why it isn't CI-enforced
* [api-v1-v2-dividing-line.md](api-v1-v2-dividing-line.md) - api-v2 in TypeScript; what still goes into v1; paging for the grid's full window; v2 admin first
* [issue-convention.md](issue-convention.md) - Issue title prefixes (api-v1, api-v2, frontend-v1, frontend-v2, db, docs) and their matching labels
* [api-v2-stack.md](api-v2-stack.md) - What api-v2 is built with (Node 26, Fastify, TypeBox, Kysely, Vitest) and why; generated database types
