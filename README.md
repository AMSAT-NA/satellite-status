## Name
Satellite Status App and API

## Description
This is a project space for development of the Satellite Status App and API, hosted at https://www.amsat.org/status.

## Repository structure

```
satellite-status/
├── frontend/v1/          # PHP frontend (status page, submit form, admin)
│   └── Dockerfile        # builds the frontend container
├── api/
│   └── v1/               # PHP REST API
│       ├── Dockerfile    # builds the API container
│       ├── overview.php  # HTML API documentation
│       ├── docs.php      # Swagger UI
│       └── acknowledgements.php
├── db/schema.sql         # database schema
├── db/seed.sql           # local dev seed data
├── tests/                # PHPUnit + Playwright test suites
└── docker-compose.yml    # local dev stack
```

## Installation

**Requires:** Docker

The app is configured entirely via environment variables — there is no config file to edit.

| Variable | Description |
|---|---|
| `SITE_URL` | Fully-qualified public URL of the service (e.g. `https://www.amsat.org/status` for the frontend, or the API base URL for the API container) |
| `MYSQL_HOST` | Database hostname |
| `MYSQL_USER` | Database username |
| `MYSQL_PASSWORD` | Database password |
| `MYSQL_DATABASE` | Database name |

Pass these to the container at runtime (e.g. via `docker run -e`, `docker compose`, or your orchestrator's secret/env injection).

The database schema is in `db/schema.sql`. Apply it to a MariaDB/MySQL instance before first run.

For local development, see the [Local Docker](#local-docker) section below — `docker compose up` wires everything together automatically.

## Local Docker

The local stack runs three containers: `frontend`, `api`, and `db`.  Frontend
and API are on **separate ports** for simplicity — no local routing proxy is
needed.

```sh
docker compose up -d --build
```

Then visit:

| Service | URL |
|---|---|
| Frontend (status page) | http://localhost:8080 |
| API documentation | http://localhost:8081/api/v1/overview.php |
| API Swagger UI | http://localhost:8081/api/v1/docs.php |
| API example | http://localhost:8081/api/v1/satellites.php |
| MariaDB | `localhost:3307`, database `satellite_status`, user `satellite`, password `satellite` |

The admin login is `admin` / `password` for local development only.

Stop the stack with:

```sh
docker compose down
```

## Testing

Run the PHPUnit integration tests (starts a local PHP dev server; no Docker required):

```sh
# install deps first (once)
curl -sS https://getcomposer.org/installer | php -- --quiet
php composer.phar install

cp tests/fixtures/config.test.php frontend/v1/config.php
cp tests/fixtures/config.test.php api/v1/config.php
php -S 127.0.0.1:8000 -t . tests/fixtures/router.php &
vendor/bin/phpunit --configuration tests/phpunit.xml --colors=auto
```

Run the browser compatibility tests:

```sh
npm install
npx playwright install chromium
npx playwright test --config tests/playwright.config.js
```

## Usage
- Visit `$SITE_URL` (set via environment variable) to see the status page.
- Utilize the API at `$SITE_URL/api/v1`.

## API
The public API lives under `/api/v1` and is documented at `/api/v1/overview.php`.
Public Swagger documentation is available at `/api/v1/docs.php`.

Available API surfaces:
- `GET /api/v1/catalog.php` lists satellites with links and optional report statistics.
- `GET /api/v1/reports.php` searches recent reports by satellite, time window, callsign, grid square, and status.
- `POST /api/v1/reports.php` submits a status report using JSON or form data.
- `GET /api/v1/summary.php` returns report counts by satellite and status.
- `GET /api/v1/statuses.php` lists the canonical report values.
- `GET /api/v1/health.php` checks API and database availability.
- `GET /api/v1/openapi.php` returns the OpenAPI 3.0 document.
- `GET /api/v1/satellites.php` remains available as a legacy-compatible satellite catalog array.
- `GET /api/v1/sat_info.php` remains available as a legacy-compatible report array.

Example:

```sh
curl "$SITE_URL/api/v1/reports.php?name=AO-91&hours=24&limit=25"
```

## Support (in order of preference)
1. Create an Issue on the [Project's GitHub page](https://github.com/AMSAT-NA/satellite-status/issues)
2. Post in AMSAT Discord Server #open-source-dev channel.
3. E-mail IT@amsat.org

## Contributing
- All ideas/contributions are open to discussion. Join us in the AMSAT Discord channel #open-source-dev.
- PRs for break/fix also welcome!

## License
- TBD
