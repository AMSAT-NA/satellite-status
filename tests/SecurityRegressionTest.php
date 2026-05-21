<?php
declare(strict_types=1);

namespace AmsatStatus\Tests;

/**
 * Regression tests for the security fixes shipped in MRs !4--!8.
 *
 * Each test pins down one of the behaviors the security work introduced.
 * If any of these go red, a recent change has reintroduced a vulnerability
 * that was previously closed.
 */
final class SecurityRegressionTest extends TestCase
{
    // -----------------------------------------------------------------
    // MR !4 -- admin login: prepared statement, generic error message,
    // no username enumeration, working redirect on success.
    // -----------------------------------------------------------------

    public function testValidLoginRedirectsToDashboard(): void
    {
        $client = $this->newGuestClient();
        $resp   = $client->post('/admin/login.php', [
            'form_params' => ['username' => 'admin', 'password' => self::ADMIN_PASSWORD],
        ]);

        $this->assertSame(302, $resp->getStatusCode());
        $this->assertStringContainsString('/admin/dashboard.php', $resp->getHeaderLine('Location'));
    }

    public function testInvalidPasswordReturnsGenericMessage(): void
    {
        $resp = $this->newGuestClient()->post('/admin/login.php', [
            'form_params' => ['username' => 'admin', 'password' => 'wrong'],
        ]);

        $this->assertSame(200, $resp->getStatusCode());
        $this->assertStringContainsString('Login failed', (string) $resp->getBody());
    }

    public function testNonexistentUserReturnsIdenticalGenericMessage(): void
    {
        // The wrong-password and wrong-username paths must produce
        // the same response so attackers can't enumerate accounts.
        $wrongPw = $this->newGuestClient()->post('/admin/login.php', [
            'form_params' => ['username' => 'admin',           'password' => 'wrong'],
        ]);
        $noUser = $this->newGuestClient()->post('/admin/login.php', [
            'form_params' => ['username' => 'no-such-account', 'password' => 'anything'],
        ]);

        $this->assertSame((string) $wrongPw->getBody(), (string) $noUser->getBody());
    }

    public function testSqlInjectionInUsernameIsBoundAsLiteral(): void
    {
        // Classic auth-bypass payload. With the prepared statement
        // this is bound as a literal username; no row matches.
        $resp = $this->newGuestClient()->post('/admin/login.php', [
            'form_params' => ["username" => "admin' OR '1'='1", 'password' => 'anything'],
        ]);

        $this->assertSame(200, $resp->getStatusCode());
        $this->assertStringNotContainsString('dashboard.php', $resp->getHeaderLine('Location'));
        $this->assertStringContainsString('Login failed', (string) $resp->getBody());
    }

    // -----------------------------------------------------------------
    // MR !5 -- submit.php: prepared statements, SQLi in SatName treated
    // as literal, duplicate detection still works.
    // -----------------------------------------------------------------

    public function testSqlInjectionInSatNameIsBoundAsLiteral(): void
    {
        $client = $this->newGuestClient();
        $resp   = $client->get('/submit.php', [
            'query' => $this->buildSubmitQuery([
                'SatName' => "AO-91' OR '1'='1",
                'SatCall' => 'W5ABC',
            ]),
        ]);

        $this->assertStringContainsString(
            'Satellite Name does not match',
            (string) $resp->getBody()
        );
        $this->assertSame(0, $this->countRows('satellite', "callsign='W5ABC' AND hour=11"));
    }

    public function testSubmitInsertsRow(): void
    {
        $resp = $this->newGuestClient()->get('/submit.php', [
            'query' => $this->buildSubmitQuery([
                'SatName' => 'AO-91',
                'SatCall' => 'W5XYZ',
                'SatHour' => '09',
            ]),
        ]);

        $this->assertStringContainsString('Thank you for your submission', (string) $resp->getBody());
        $this->assertSame(1, $this->countRows('satellite', "callsign='W5XYZ'"));
    }

    public function testDuplicateSubmissionReplacesRatherThanDuplicates(): void
    {
        $params = $this->buildSubmitQuery([
            'SatName' => 'AO-91',
            'SatCall' => 'W5DUP',
            'SatHour' => '10',
        ]);
        $this->newGuestClient()->get('/submit.php', ['query' => $params]);
        $resp = $this->newGuestClient()->get('/submit.php', ['query' => $params]);

        $this->assertStringContainsString('already made a report', (string) $resp->getBody());
        $this->assertSame(1, $this->countRows('satellite', "callsign='W5DUP'"));
    }

    // -----------------------------------------------------------------
    // MR !6 -- admin CRUD: anonymous mutation is no longer possible.
    // Each endpoint must 302 the unauthenticated request AND leave the
    // database unchanged.
    // -----------------------------------------------------------------

    public function testAnonymousDeleteIs302AndNonDestructive(): void
    {
        $beforeId = (int) $this->db->query("SELECT id FROM satellite_name LIMIT 1")
                                   ->fetch_assoc()['id'];
        $beforeCount = $this->countRows('satellite_name');

        $resp = $this->newGuestClient()->get("/admin/delete_satellite.php?id={$beforeId}");

        $this->assertSame(302, $resp->getStatusCode());
        $this->assertStringContainsString('/admin/index.php', $resp->getHeaderLine('Location'));
        $this->assertSame($beforeCount, $this->countRows('satellite_name'));
    }

    public function testAnonymousCreateIs302AndNonDestructive(): void
    {
        $before = $this->countRows('satellite_name');

        $resp = $this->newGuestClient()->post('/admin/create_satellite.php', [
            'form_params' => [
                'satellite_name'      => 'PWNED',
                'html_satellite_name' => 'PWN',
                'website'             => 'evil',
            ],
        ]);

        $this->assertSame(302, $resp->getStatusCode());
        $this->assertSame($before, $this->countRows('satellite_name'));
    }

    public function testAnonymousUpdateIs302AndNonDestructive(): void
    {
        $row = $this->db->query("SELECT id, name FROM satellite_name LIMIT 1")->fetch_assoc();

        $resp = $this->newGuestClient()->post('/admin/update_satellite.php', [
            'form_params' => [
                'sat_id'              => $row['id'],
                'satellite_name'      => 'PWNED',
                'html_satellite_name' => 'PWN',
                'website'             => 'evil',
            ],
        ]);

        $this->assertSame(302, $resp->getStatusCode());
        $stillThere = $this->db->query(
            "SELECT name FROM satellite_name WHERE id={$row['id']}"
        )->fetch_assoc()['name'];
        $this->assertSame($row['name'], $stillThere);
    }

    public function testAnonymousEditFormIs302(): void
    {
        $id   = (int) $this->db->query("SELECT id FROM satellite_name LIMIT 1")->fetch_assoc()['id'];
        $resp = $this->newGuestClient()->get("/admin/edit_satellite.php?id={$id}");

        $this->assertSame(302, $resp->getStatusCode());
    }

    public function testAuthenticatedAdminCanCreateEditAndDelete(): void
    {
        $admin = $this->login();

        // Create
        $admin->post('/admin/create_satellite.php', [
            'form_params' => [
                'satellite_name'      => 'TestSat',
                'html_satellite_name' => 'TEST-1',
                'website'             => 'https://example.com',
            ],
        ]);
        $id = (int) $this->db->query(
            "SELECT id FROM satellite_name WHERE html_element_name='TEST-1'"
        )->fetch_assoc()['id'];
        $this->assertGreaterThan(0, $id, 'Create did not insert a row');

        // Update
        $admin->post('/admin/update_satellite.php', [
            'form_params' => [
                'sat_id'              => $id,
                'satellite_name'      => 'Renamed',
                'html_satellite_name' => 'TEST-1',
                'website'             => 'https://example.com',
            ],
        ]);
        $name = $this->db->query("SELECT name FROM satellite_name WHERE id={$id}")
                         ->fetch_assoc()['name'];
        $this->assertSame('Renamed', $name);

        // Delete
        $admin->get("/admin/delete_satellite.php?id={$id}");
        $this->assertSame(0, $this->countRows('satellite_name', "id={$id}"));
    }

    public function testStoredXssInSatelliteNameIsEscapedOnRender(): void
    {
        $admin = $this->login();
        $admin->post('/admin/create_satellite.php', [
            'form_params' => [
                'satellite_name'      => '<script>alert(1)</script>',
                'html_satellite_name' => 'XSS-1',
                'website'             => 'https://example.com',
            ],
        ]);
        $id = (int) $this->db->query(
            "SELECT id FROM satellite_name WHERE html_element_name='XSS-1'"
        )->fetch_assoc()['id'];

        // The edit form renders the stored name in an <h1> and an
        // <input value="...">. Both should be escaped.
        $body = (string) $admin->get("/admin/edit_satellite.php?id={$id}")->getBody();

        $this->assertStringNotContainsString('<script>alert(1)</script>', $body);
        $this->assertStringContainsString('&lt;script&gt;alert(1)&lt;/script&gt;', $body);
    }

    // -----------------------------------------------------------------
    // MR !7 -- the rest of the admin pages have the auth guard fixed,
    // and info.php is gone.
    // -----------------------------------------------------------------

    public function testAnonymousDashboardIs302(): void
    {
        $this->assertSame(302, $this->newGuestClient()->get('/admin/dashboard.php')->getStatusCode());
    }

    public function testAnonymousAddSatelliteFormIs302(): void
    {
        $this->assertSame(302, $this->newGuestClient()->get('/admin/add_satellite.php')->getStatusCode());
    }

    public function testAnonymousManageSatellitesIs302(): void
    {
        $this->assertSame(302, $this->newGuestClient()->get('/admin/manage_satellites.php')->getStatusCode());
    }

    public function testInfoPhpIsGone(): void
    {
        $this->assertSame(404, $this->newGuestClient()->get('/info.php')->getStatusCode());
    }

    // -----------------------------------------------------------------
    // MR !8 -- admin/passwordhash.php (the backdoor) is gone.
    // -----------------------------------------------------------------

    public function testPasswordhashPhpIsGone(): void
    {
        $this->assertSame(404, $this->newGuestClient()->get('/admin/passwordhash.php')->getStatusCode());
    }

    // -----------------------------------------------------------------
    // Helper: build a query-string for submit.php. submit.php's
    // confirm-flow accepts both POST and GET; GET is simpler here.
    // -----------------------------------------------------------------

    private function buildSubmitQuery(array $overrides): array
    {
        // Default to "yesterday" so submit.php's no-future-times check
        // accepts the submission regardless of the runner's clock.
        $yesterday = gmdate('Y-m-d', strtotime('yesterday UTC'));
        [$year, $month, $day] = explode('-', $yesterday);

        return array_merge([
            'SatSubmit'     => 'yes',
            'Confirm'       => 'yes',
            'SatName'       => 'AO-91',
            'SatYear'       => $year,
            'SatMonth'      => $month,
            'SatDay'        => $day,
            'SatHour'       => '12',
            'SatPeriod'     => '0',
            'SatCall'       => 'W5TEST',
            'SatReport'     => 'Heard',
            'SatGridSquare' => 'EM48',
        ], $overrides);
    }
}
