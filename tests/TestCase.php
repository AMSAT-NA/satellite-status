<?php
declare(strict_types=1);

namespace AmsatStatus\Tests;

use GuzzleHttp\Client;
use GuzzleHttp\Cookie\CookieJar;
use mysqli;
use PHPUnit\Framework\TestCase as PHPUnitTestCase;

/**
 * Base class for AMSAT Satellite Status HTTP integration tests.
 *
 * Each test method starts from a known database state: the satellite,
 * satellite_name, and users tables are TRUNCATEd and reseeded in setUp().
 * The test harness expects a separately-running web server (locally:
 * `docker compose -f .dev/docker-compose.yml up -d`; in CI: a `php -S`
 * spawned before the test job runs).
 *
 * Configuration is via environment variables, with defaults targeting the
 * local docker-compose stack. See phpunit.xml for the defaults.
 */
abstract class TestCase extends PHPUnitTestCase
{
    protected Client $http;
    protected mysqli $db;

    /**
     * Bcrypt hash of the literal string "password". Used by login tests.
     */
    protected const ADMIN_PASSWORD = 'password';
    protected const ADMIN_PASSWORD_HASH =
        '$2y$10$HAEfSuuDdryK.SjjOmBiv.iJzhtDQUtvJmjYqCeRQz05RW5h0mG9e';

    protected function setUp(): void
    {
        $this->db   = $this->openDatabaseConnection();
        $this->http = $this->newGuestClient();
        $this->resetDatabase();
    }

    protected function tearDown(): void
    {
        $this->db->close();
    }

    /**
     * Returns a new Guzzle client with no session cookie. Use for tests
     * that exercise unauthenticated behavior, or as the starting point
     * before calling login().
     */
    protected function newGuestClient(?CookieJar $jar = null): Client
    {
        return new Client([
            'base_uri'        => getenv('TEST_BASE_URL') ?: 'http://localhost:8080',
            'http_errors'     => false, // don't throw on 4xx/5xx
            'allow_redirects' => false, // tests assert on 302s directly
            'cookies'         => $jar ?? new CookieJar(),
            'timeout'         => 10,
        ]);
    }

    /**
     * POSTs valid admin credentials to /admin/login.php and returns a
     * Guzzle client whose cookie jar holds the resulting PHPSESSID.
     */
    protected function login(string $username = 'admin', string $password = self::ADMIN_PASSWORD): Client
    {
        $jar    = new CookieJar();
        $client = $this->newGuestClient($jar);
        $resp   = $client->post('/admin/login.php', [
            'form_params' => ['username' => $username, 'password' => $password],
        ]);

        if ($resp->getStatusCode() !== 302) {
            throw new \RuntimeException(sprintf(
                'Login as %s failed: expected 302, got %d. Body: %s',
                $username,
                $resp->getStatusCode(),
                (string) $resp->getBody()
            ));
        }

        return $client;
    }

    /**
     * Returns a fresh mysqli connection to the test database, using env
     * vars (defaults match the .dev docker-compose stack).
     */
    private function openDatabaseConnection(): mysqli
    {
        $host = getenv('TEST_DB_HOST') ?: '127.0.0.1';
        $port = (int) (getenv('TEST_DB_PORT') ?: 3307);
        $user = getenv('TEST_DB_USER') ?: 'satstatus';
        $pass = getenv('TEST_DB_PASS') ?: 'satstatus';
        $name = getenv('TEST_DB_NAME') ?: 'satstatus';

        $db = @new mysqli($host, $user, $pass, $name, $port);
        if ($db->connect_error) {
            throw new \RuntimeException(
                "Could not connect to test database at {$host}:{$port}: {$db->connect_error}"
            );
        }
        return $db;
    }

    /**
     * Wipes the application's data tables and reseeds with a known
     * fixture set. Called before every test method.
     */
    protected function resetDatabase(): void
    {
        // Order doesn't matter -- no foreign keys -- but TRUNCATE rather
        // than DELETE so AUTO_INCREMENT counters reset and ids are stable
        // across runs.
        foreach (['satellite', 'satellite_name', 'users'] as $table) {
            if (!$this->db->query("TRUNCATE TABLE `{$table}`")) {
                throw new \RuntimeException("TRUNCATE {$table} failed: {$this->db->error}");
            }
        }

        // Admin user
        $stmt = $this->db->prepare(
            'INSERT INTO users (username, password, email) VALUES (?, ?, ?)'
        );
        $stmt->bind_param('sss', ...[
            'admin', self::ADMIN_PASSWORD_HASH, 'admin@example.com',
        ]);
        $stmt->execute();
        $stmt->close();

        // Satellite catalog
        $catalog = [
            ['AO-91 Mode U/v FM', 'AO-91', 'https://www.amsat.org/two-fox-in-a-box-amsat-fox-1b/'],
            ['FO-29',             'FO-29', 'https://www.amsat.org/satellite/fo-29/'],
            ['ISS Voice Repeater','ISS',   'https://www.amsat.org/iss-info/'],
        ];
        $stmt = $this->db->prepare(
            'INSERT INTO satellite_name (name, html_element_name, website) VALUES (?, ?, ?)'
        );
        foreach ($catalog as [$name, $html, $site]) {
            $stmt->bind_param('sss', $name, $html, $site);
            $stmt->execute();
        }
        $stmt->close();

        // A handful of recent reports. Dates are relative to "today" in UTC
        // since submit.php enforces a UTC clock.
        $today = gmdate('Y-m-d');
        $stmt  = $this->db->prepare(
            'INSERT INTO satellite (name, longname, day, hour, period, callsign, report, grid_square) '
            . 'VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
        );
        foreach ([
            ['AO-91', 'AO-91', $today, 14, 1, 'W5ABC',  'Heard',          'EM25LX'],
            ['AO-91', 'AO-91', $today, 15, 0, 'KB1XYZ', 'Heard',          'FN42'],
            ['FO-29', 'FO-29', $today, 18, 3, 'JA1ABC', 'Heard',          'PM95'],
        ] as [$name, $longname, $day, $hour, $period, $callsign, $report, $grid]) {
            $stmt->bind_param('sssiisss', $name, $longname, $day, $hour, $period, $callsign, $report, $grid);
            $stmt->execute();
        }
        $stmt->close();
    }

    /**
     * Convenience: count rows in a table matching a WHERE expression.
     */
    protected function countRows(string $table, string $whereExpr = '1=1'): int
    {
        // $table/$whereExpr are test-controlled, not user input.
        $result = $this->db->query("SELECT COUNT(*) AS n FROM `{$table}` WHERE {$whereExpr}");
        return (int) $result->fetch_assoc()['n'];
    }
}
