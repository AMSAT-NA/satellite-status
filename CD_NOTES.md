# CD Notes — build, push to ACR, deploy to azure193

This document covers the `cd/azure193-deploy` branch: a CD pipeline that
builds `frontend/v1` and `api/v1` images, pushes them to ACR, and deploys the
stack to azure193, mirroring the working `amsat-discord-bot` CI/CD workflow.
It flags every decision that diverged from the task brief or needs repo-owner
confirmation before the first real deploy.

---

## Trigger: broader than the brief's literal snippet

The task brief suggested:

```yaml
on:
  push:
    branches: [main]
  pull_request:
    branches: [main]
```

`cd.yml` instead uses `branches: ["**"]` for both `push` and `pull_request`,
matching the existing `ci.yml` convention already in this repo. Reasoning:
with the brief's literal trigger, pushing to a feature branch without opening
a PR would never fire the workflow at all — but this task explicitly calls
for pushing commits to `cd/azure193-deploy` with no PR and confirming the CI
gate and Docker build test jobs run on that branch. Restricting the trigger
to `main` only would make that unverifiable. Deploy-related jobs (push,
configure-env, deploy) are still gated to `main` via per-job `if:` conditions
exactly as in the discord bot workflow — only the *trigger* is broader, not
the deploy blast radius.

## CI gate: made `ci.yml` a reusable workflow instead of duplicating it

`ci.yml`'s own `push`/`pull_request` triggers were removed and replaced with
`workflow_call:` only. `cd.yml` now calls it as job `ci` via
`uses: ./.github/workflows/ci.yml`. This avoids running the full CI suite
twice per push (once standalone, once nested inside CD) while still
satisfying "CD never runs if CI is broken" — every other job in `cd.yml`
transitively depends on `ci` succeeding. `cd.yml` is now the sole entry point
for both CI and CD; the underlying CI job names still show up individually in
the Actions UI, nested under the CD run.

## Path-gating implementation

A dedicated `changes` job runs `dorny/paths-filter@v3` (after the CI gate)
and exposes `frontend`/`api` boolean outputs, shared by both the Docker build
test job and the push-to-ACR job:

- **Job 2 (docker-build-test)**: job-level `if` skips entirely unless
  `frontend/v1/**` or `api/v1/**` changed. When it runs, it builds **both**
  images unconditionally (matching the brief).
- **Job 3 (push)**: gates per-image at the *step* level — each
  `docker/build-push-action` step has its own `if: needs.changes.outputs.X
  == 'true'`. If only `frontend/v1/**` changed, only the frontend image is
  built and pushed; the `api` step is skipped. The job itself still runs (it
  doesn't skip wholesale) so its per-step gating stays fine-grained.
- **Jobs 4–5 (configure-env, deploy)** are *not* path-gated at all — they
  always run on a `main` push (subject to upstream success), because the
  `.env` or `deploy/docker-compose.yml` may need to ship even when no image
  changed. `docker compose pull && docker compose up -d` is a safe no-op for
  any service whose image digest didn't change.

One subtlety worth documenting: a job-level `if` skip and an upstream-failure
skip both report `result == 'skipped'` — they're indistinguishable by that
field alone. So jobs 3–5 don't just check "was the previous job skipped or
successful"; each also independently checks `needs.ci.result == 'success' &&
needs.changes.result == 'success'` (neither of which are ever legitimately
skipped on a triggered `main` push) before proceeding. This is what prevents
a CI failure from silently letting a stale deploy through just because the
path filter also happened to report "skipped".

## `deploy/docker-compose.yml` vs. root `docker-compose.yml`

Two separate files, deliberately:

- **Root `docker-compose.yml`** — local dev and the CI smoke-test job. Uses
  `build:` contexts, bind-mounts source code, exposes host ports
  (`8080`/`8081`/`3307`), and hardcodes dev credentials via `environment:`.
- **`deploy/docker-compose.yml`** — what actually runs on azure193. Uses
  `image:` references to ACR (`amsatorg.azurecr.io/satellite-status-*:latest`),
  no bind mounts (production images are immutable), no host port exposure
  (Traefik is the only ingress), and `env_file: .env` sourced from GitHub
  Secrets/Variables at deploy time instead of hardcoded values.

Collapsing these into one parameterized file was considered and rejected —
the local/CI file needs `build:` + bind mounts + exposed ports for iteration
and smoke testing, none of which belong anywhere near production. Two small,
readable files beat one file trying to serve both purposes via profiles or
overrides.

## `--force-recreate`: omitted

The discord bot's deploy step uses `docker compose up -d --force-recreate`,
which restarts every container on every deploy regardless of whether its
image digest changed. That's fine for a single-container bot with no
persistent user-facing state to disrupt mid-request. This stack has three
containers — `frontend`, `api`, and `db` — where `db` unconditionally
restarting on every deploy (even ones that only change the frontend image)
would briefly drop DB connectivity for both other services and is pure
downside with no corresponding benefit. `docker compose up -d` (no flag)
already recreates any container whose image or config changed and leaves
the rest running, which is the correct behavior here.

## Database init on first deploy — resolved: schema mounted, init-once by design

**Decision (confirmed by repo owner): the DB should only be initialized if
it doesn't already exist.** That's exactly MariaDB's built-in behavior for
`/docker-entrypoint-initdb.d/` — those scripts only run when the data
directory is empty (i.e. `db-data` has never been initialized). So mounting
`db/schema.sql` there is safe on every deploy, not just the first one: on a
fresh volume it seeds the schema; on every subsequent deploy the volume is
already initialized and MariaDB skips the directory entirely, so the mount
is inert.

`deploy/docker-compose.yml` now mounts:
```yaml
- ./db/schema.sql:/docker-entrypoint-initdb.d/001-schema.sql:ro
```
matching the root compose file's pattern. `db/schema.sql` isn't part of the
repo checkout on the server, so the `configure-env` job now also ships it
via `scp` to `/opt/services/satellite-status/db/schema.sql` alongside the
compose file and `.env` (the service directory now includes a `db/`
subdirectory, created in the "Ensure service directory exists on server"
step).

`db/seed.sql` is still deliberately **not** shipped or mounted — it's
test/demo fixture data, not appropriate for production regardless of the
init-timing question above.

## Secrets and vars — what's new vs. already exists

Per the discord bot convention, already exist at the org or repo level and
are reused as-is (no action needed):

- `secrets.HOST_DEPLOY_KEY`
- `secrets.AZURE_ACR_CLIENT_SECRET`
- `vars.AZURE_ACR_CLIENT_ID`, `vars.AZURE_TENANT_ID`,
  `vars.AZURE_SUBSCRIPTION_ID`, `vars.AZURE_ACR_NAME`,
  `vars.HOST_ADDRESS`, `vars.HOST_USER`

New — need to be added to this repo's Actions settings before the first
deploy:

- `secrets.MYSQL_PASSWORD` — app DB user password
- `vars.SITE_URL` — `https://status.amsat.org`
- `vars.MYSQL_HOST` — `db`
- `vars.MYSQL_USER` — e.g. `satellite`
- `vars.MYSQL_DATABASE` — `satellite_status`

There is intentionally **no root password secret**. The `db` service uses
`MARIADB_RANDOM_ROOT_PASSWORD=yes` (hardcoded in the workflow, not a var —
it should always be true for this stack). This is a single-purpose compose
stack with no shared DB container and no port exposed on `db`, so a
privileged root account with a known password isn't needed. The only
credential in play anywhere is the app user (`MYSQL_USER`/`MYSQL_PASSWORD`).

---

## Verification status

Pushed to `cd/azure193-deploy` (no PR opened) and confirmed on GitHub Actions
across two runs:

**First push** (workflow files + notes only, nothing under `frontend/v1/**`
or `api/v1/**`): the CI Gate passed and the path filter correctly determined
neither image needed rebuilding, so `Docker Build Test` (and everything after
it) reported `skipped` — proof the path-gating works as intended rather than
building unconditionally.

**Second push** (trivial comment added to both `frontend/v1/Dockerfile` and
`api/v1/Dockerfile`, specifically to exercise the gated path): 
- **CI Gate** (`ci.yml` called via `workflow_call`) passed — PHPUnit,
  Playwright, and the docker-compose smoke test all green. (One transient
  `phpunit` failure from a Docker Hub pull timeout on the runner, unrelated
  to any code here — a re-run of just that job passed cleanly, and it's
  worth noting the failure correctly cascaded to skip every job after it,
  confirming the "CD never runs if CI is broken" gate works both ways.)
- **Detect changed paths** correctly flagged both `frontend` and `api` as
  changed.
- **Docker Build Test** built both `frontend/v1` and `api/v1` images
  successfully with `push: false` — no images were pushed anywhere.
- **Push to ACR**, **Configure Server Environment**, and **Deploy** all
  correctly reported `skipped`, since `github.ref` isn't `refs/heads/main`.

No deploy to azure193 has been attempted from this branch. The repo owner
will add the new secrets/vars above, resolve the DB-init question, and
trigger the first real deploy by merging to `main`.

## MariaDB bumped 11.4 → 12.3 LTS

`docker-compose.yml` and `deploy/docker-compose.yml` now pin `mariadb:12.3`
instead of `mariadb:11.4`. 11.4 was the current LTS when these files were
first written; 12.3 LTS was released May 29, 2026 and is now the current
LTS (maintained for three years), so 12.3 is the correct pin going forward.

This also happens to address a production issue: on 11.4, healthcheck user
creation was failing with `MARIADB_RANDOM_ROOT_PASSWORD=yes` set, despite the
official MariaDB docs showing that combination as supported. Root cause on
11.4 wasn't tracked down — the version bump resolves it either way, so it
wasn't worth further investigation.

The azure193 server was fully wiped (`/opt/services/satellite-status/`
deleted, `db-data` volume destroyed) before this change, so the next deploy
is a genuinely clean first-boot init on 12.3 — no migration path from an
11.4 data directory needs to be considered.

## `db` healthcheck: TCP loopback on azure193, `healthcheck.sh` everywhere else

The first production deploy to azure193 surfaced a healthcheck failure that
turned out to be specific to that host, not to MariaDB or to this repo's
config. Ruled out during investigation:

- MariaDB version (reproduced identically on both 11.4 and 12.3)
- Volume state (reproduced on a completely fresh `db-data` volume)
- The root password approach (`MARIADB_RANDOM_ROOT_PASSWORD=yes` is
  documented by upstream as compatible with `healthcheck.sh`)
- The `--su-mysql` flag to `healthcheck.sh` (tried, same access-denied error)

**Root cause**: on azure193, `docker exec` routes MariaDB client connections
through the container's Docker bridge network IP (observed as `172.20.0.2`)
rather than through the unix socket, regardless of how the socket is
specified on the command line. `healthcheck.sh` connects as the
`healthcheck` user, which MariaDB's entrypoint grants only as
`healthcheck@localhost`, `healthcheck@127.0.0.1`, and `healthcheck@::1` —
none of which match a connection arriving from the bridge IP, so every
`docker exec healthcheck.sh` call fails with access denied. This is a
behavior of this specific Azure VM's Docker/network setup, not a MariaDB bug
or a misconfiguration in this repo. It isn't isolated to this stack either —
every other service on azure193 that needs a healthcheck (e.g. uptime-kuma)
also uses an HTTP/TCP check rather than anything socket-based, because no
socket-based healthcheck works there via `docker exec`.

**Fix**: `deploy/docker-compose.yml`'s `db` healthcheck now connects over TCP
to `127.0.0.1` using the app database credentials (`MYSQL_USER`/
`MYSQL_PASSWORD`, already present via `env_file: .env`) instead of invoking
`healthcheck.sh`:

```yaml
healthcheck:
  test: ["CMD", "mariadb", "-h", "127.0.0.1", "-u", "${MYSQL_USER}", "-p${MYSQL_PASSWORD}", "--silent", "-e", "SELECT 1;"]
```

This has been running healthily in production on azure193 since being
applied manually; this change syncs the repo to match.

The root `docker-compose.yml` (local dev and the CI smoke-test job)
deliberately keeps `healthcheck.sh` unchanged — socket connections behave
normally on GitHub Actions runners and on local dev machines, so there's no
reason to carry azure193's workaround into environments that don't need it.
The two compose files using different healthcheck mechanisms for the same
service is intentional, not drift.

As part of the same fix, `MARIADB_AUTO_UPGRADE=1` is now set for `db` in both
compose files (via `.env` for `deploy/docker-compose.yml`, generated in
`cd.yml`'s `configure-env` job; via the `environment:` block for the root
`docker-compose.yml`). It was tried during the azure193 investigation as a
possible healthcheck-recovery mechanism and didn't fix the connection-routing
issue above, but it's being kept permanently regardless: it makes MariaDB run
`mysql_upgrade` automatically on startup whenever the system tables are
behind the running server version, which is the correct standing behavior
for a Docker-managed MariaDB instance that gets version-bumped over time
(like the 11.4 → 12.3 move above) rather than something to only enable
reactively. It's a no-op with negligible startup overhead on fresh installs
and on every startup where no upgrade is needed.
