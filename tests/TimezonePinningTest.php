<?php
declare(strict_types=1);

namespace AmsatStatus\Tests;

use PHPUnit\Framework\TestCase as PHPUnitTestCase;

/**
 * Verifies api_db() pins the MySQL session to UTC (Issue #24 --
 * TIMESTAMP columns implicitly convert based on the session's time_zone,
 * and this app assumes UTC everywhere via gmdate()/gmmktime()).
 *
 * Deliberately NOT an HTTP integration test like the rest of this suite:
 * a session variable isn't visible across requests/connections, so
 * there's no HTTP-visible way to check what time_zone a *different*
 * request's connection ended up with. This test requires api/v1's
 * bootstrap directly and calls api_db() in-process instead.
 *
 * api_db()'s mysqli connection has no port parameter (matches
 * production/CI, where the DB is always reached on its default port),
 * so this maps TEST_DB_* onto MYSQL_* without a port -- consistent with
 * how tests/fixtures/config.test.php feeds api/v1/config.php in CI
 * (TEST_DB_HOST=mariadb, default port 3306). A local run against the
 * docker-compose stack's host-mapped port (3307) will not connect here,
 * same pre-existing constraint config.test.php has.
 */
final class TimezonePinningTest extends PHPUnitTestCase
{
    public function testApiDbConnectionIsPinnedToUtc(): void
    {
        putenv('MYSQL_HOST=' . (getenv('TEST_DB_HOST') ?: 'mariadb'));
        putenv('MYSQL_USER=' . (getenv('TEST_DB_USER') ?: 'satstatus'));
        putenv('MYSQL_PASSWORD=' . (getenv('TEST_DB_PASS') ?: 'satstatus'));
        putenv('MYSQL_DATABASE=' . (getenv('TEST_DB_NAME') ?: 'satstatus'));

        require_once __DIR__ . '/../api/v1/lib/bootstrap.php';

        // config.php's top-level `$mysqlHost = ...` assignments (pulled in
        // by the require above) land in THIS METHOD's local scope, not
        // $GLOBALS -- require executes in the scope of the require
        // statement, not the file's own top level. api_db() reads these
        // via `global $mysqlHost`, i.e. $GLOBALS, so without this bridge
        // it would see nothing and connect with a null host.
        foreach (['mysqlHost', 'mysqlUsername', 'mysqlPassword', 'mysqlDatabase'] as $name) {
            $GLOBALS[$name] = $$name;
        }

        $db = api_db();
        $result = $db->query('SELECT @@session.time_zone AS tz');
        $timeZone = $result->fetch_assoc()['tz'];
        $db->close();

        $this->assertSame('+00:00', $timeZone);
    }
}
