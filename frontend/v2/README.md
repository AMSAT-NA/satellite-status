# frontend-v2 (`frontend/v2/`)

Placeholder. Nothing here is built or deployed yet.

frontend-v2 is the replacement for the PHP frontend in `frontend/v1/`. It
talks only to api-v2 (`api/v2/`), never to the database directly. The v2
admin pages ship first, together with the #26 catalog redesign (Issue #75);
the public pages follow. See
[`docs/agents/api-v1-v2-dividing-line.md`](../../docs/agents/api-v1-v2-dividing-line.md).

The folder exists now so CI's path filters can cover it from the start
(`.github/workflows/ci.yml`, `changes` job): changes here, or to `api/v2/`,
will run frontend-v2's CI once it has a job. `db/` changes won't, since
frontend-v2 doesn't read the database.
