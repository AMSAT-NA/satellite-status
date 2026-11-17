# api-v2

Version 2 of the AMSAT satellite status API, in TypeScript. Stack and
reasoning: [`docs/agents/api-v2-stack.md`](../docs/agents/api-v2-stack.md).
In development: `/api/v1` remains the stable API.

## Run locally

```sh
docker compose up -d --build        # from the repo root
curl http://localhost:8082/api/v2/health
open http://localhost:8082/api/v2/docs   # Swagger UI
```

## Scripts (from `api-v2/`)

| Command | What it does |
|---|---|
| `npm ci` | Install exactly what `package-lock.json` pins |
| `npm run typecheck` | Strict TypeScript check |
| `npm test` | Vitest, against the database in `TEST_DB_*` |
| `npm run build` | Compile to `dist/` |
| `npm run db:types` | Regenerate `src/db/types.ts` from the database in `DATABASE_URL` |

`src/db/types.ts` is generated from `db/schema.sql`. Never edit it by
hand: after a schema change, run `npm run db:types` against a database
loaded with the new schema and commit the result. CI fails if they differ.

## Configuration

The same variables as the v1 API: `MYSQL_HOST`, `MYSQL_PORT` (default
3306), `MYSQL_USER`, `MYSQL_PASSWORD`, `MYSQL_DATABASE`, plus `PORT`
(default 3000).
