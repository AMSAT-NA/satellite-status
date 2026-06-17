## Name
Satellite Status App and API

## Description
This is a project space for development of the Satellite Status App and API, hosted at https://www.amsat.org/status.

## Installation

**Requires:** Docker

The app is configured entirely via environment variables — there is no config file to edit.

| Variable | Description |
|---|---|
| `SITE_URL` | Fully-qualified public URL of the site (e.g. `https://www.amsat.org/status`) |
| `MYSQL_HOST` | Database hostname |
| `MYSQL_USER` | Database username |
| `MYSQL_PASSWORD` | Database password |
| `MYSQL_DATABASE` | Database name |

Pass these to the container at runtime (e.g. via `docker run -e`, `docker compose`, or your orchestrator's secret/env injection).

The database schema is in `db/schema.sql`. Apply it to a MariaDB/MySQL instance before first run.

For local development, see the [Local Docker](#local-docker) section below — `docker compose up` wires everything together automatically.

## Local Docker
Run the app and a seeded MariaDB database locally:

```sh
docker compose up -d --build
```

Then visit:
- App: http://localhost:8080
- API docs: http://localhost:8080/api/
- API example: http://localhost:8080/api/v1/satellites.php
- MariaDB: `localhost:3307`, database `satellite_status`, user `satellite`, password `satellite`

The admin login is `admin` / `password` for local development only.
Stop the stack with:

```sh
docker compose down
```

## Testing
Run the PHP integration tests inside the Docker web container:

```sh
docker compose exec -T web sh -lc 'TEST_BASE_URL=http://localhost TEST_DB_HOST=db TEST_DB_PORT=3306 TEST_DB_USER=satellite TEST_DB_PASS=satellite TEST_DB_NAME=satellite_status vendor/bin/phpunit --colors=never'
```

Run the browser compatibility tests:

```sh
npm install
npx playwright install chromium
npm run test:frontend
```

## Usage
- Visit $SITEURL (configured in config.php) to see the data.
- Utilize the API.

## API
The public API lives under `$SITEURL/api/v1` and is documented at
`$SITEURL/api/index.php`. Public Swagger documentation is available at
`$SITEURL/api/docs.php`.

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
curl "$SITEURL/api/v1/reports.php?name=AO-91&hours=24&limit=25"
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