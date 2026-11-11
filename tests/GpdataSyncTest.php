<?php
declare(strict_types=1);

namespace AmsatStatus\Tests;

use PHPUnit\Framework\Attributes\DataProvider;

require_once __DIR__ . '/../api/v1/lib/gpdata_sync.php';

/**
 * Issue #25: NORAD catalog numbers from the AMSAT GP feed (gpdata-sync).
 *
 * Rules under test, one test each (see api/v1/lib/gpdata_sync.php):
 *  - names match despite leading zeros, dashes, case and mode suffixes, and
 *    either side of a parenthesised alternate name
 *  - all mode rows of one spacecraft get the same ID
 *  - IDs are set, corrected, and left alone when already right
 *  - not in the feed / in the feed without an ID: existing ID kept
 *  - ambiguous name: skipped, never guessed
 *  - unusable feed: run aborted, nothing written
 *  - one run at a time
 *  - every run recorded
 * Plus the three entry points: the token-protected endpoint, the scheduled
 * CLI job, and the admin "Sync now" button; and norad_cat_id in
 * catalog.php / reports.php.
 *
 * The library tests call gpdata_sync() directly against the test DB with
 * fixture feeds; the HTTP tests use the server's GPDATA_FEED_URL, which CI
 * points at tests/fixtures/gpdata/daily-bulletin.json.
 */
final class GpdataSyncTest extends TestCase
{
    private const FEED = __DIR__ . '/fixtures/gpdata/daily-bulletin.json';
    private const TOKEN_ENV = 'GPDATA_SYNC_TOKEN';

    /** @var list<string> */
    private array $tempFiles = [];

    protected function tearDown(): void
    {
        foreach ($this->tempFiles as $file) {
            @unlink($file);
        }
        parent::tearDown();
    }

    // ── Matching ───────────────────────────────────────────────────────

    /**
     * @return array<string, array{string, string, bool}>
     */
    public static function namePairs(): array
    {
        return [
            'leading zero' => ['AO-07', 'AO-7', true],
            'dash' => ['RS-38S', 'RS38S', true],
            'case' => ['GRBBeta', 'GRBBETA', true],
            'underscores (catalog) vs spaces' => ['Out of the Box', 'Out_of_the_Box', true],
            'token boundaries kept' => ['TEVEL2-1', 'TEVEL-21', false],
            'different number' => ['AO-7', 'AO-73', false],
        ];
    }

    #[DataProvider('namePairs')]
    public function testNameKey(string $a, string $b, bool $same): void
    {
        $this->assertSame($same, gpdata_name_key($a) === gpdata_name_key($b));
    }

    public function testCatalogModeSuffixIsIgnored(): void
    {
        $this->assertSame('AO-123', gpdata_catalog_spacecraft_name('AO-123_[FM]'));
        $this->assertSame('AO-129', gpdata_catalog_spacecraft_name('AO-129_[U/u_Digi]'));
        $this->assertSame('Out of the Box', gpdata_catalog_spacecraft_name('Out_of_the_Box_[Music]'));
        $this->assertSame('ISS', gpdata_catalog_spacecraft_name('ISS'));
    }

    public function testFeedAlternateNamesMatchOnEitherSide(): void
    {
        $this->assertSame(['HYPERVIEW-1G', 'RS66S'], gpdata_feed_names('HYPERVIEW-1G (RS66S)'));
        $this->assertSame(['RS95S', 'QMR-KWT-2'], gpdata_feed_names('RS95S (QMR-KWT-2)'));
        $this->assertSame(['ISS'], gpdata_feed_names('ISS'));
    }

    // ── Sync behaviour ─────────────────────────────────────────────────

    public function testSyncSetsIdsAndRecordsTheRun(): void
    {
        $result = gpdata_sync($this->db, 'manual', self::FEED);

        $this->assertSame('ok', $result['status']);
        $this->assertSame('manual', $result['trigger']);
        $this->assertSame(30, $result['feed_records']);
        $this->assertSame(3, $result['matched']); // seed: AO-91, FO-29, ISS
        $this->assertSame([43017, 24278, 25544], [$this->noradOf('AO-91'), $this->noradOf('FO-29'), $this->noradOf('ISS')]);
        $this->assertContains(['spacecraft' => 'AO-91', 'from' => null, 'to' => 43017], $result['changed']);
        $this->assertSame(1, $this->countRows('gpdata_sync_run', "status = 'ok' AND matched = 3 AND changed = 3 AND finished_at IS NOT NULL"));
    }

    public function testNamePatternsFromTheLiveCatalogMatch(): void
    {
        $this->addCatalog('AO-7_[U/v]', 'RS38S_[SSTV]', 'RS40S_[SSTV]', 'RS66S_[SSDV]', 'RS95S_[FM]', 'GRBBeta_[UHF_Digi]');

        gpdata_sync($this->db, 'manual', self::FEED);

        $this->assertSame(7530, $this->noradOf('AO-7_[U/v]'), 'leading zero: AO-07');
        $this->assertSame(57189, $this->noradOf('RS38S_[SSTV]'), 'dash: RS-38S');
        $this->assertSame(57172, $this->noradOf('RS40S_[SSTV]'), 'inside parentheses: UmKA 1 (RS40S)');
        $this->assertSame(61772, $this->noradOf('RS66S_[SSDV]'), 'inside parentheses: HYPERVIEW-1G (RS66S)');
        $this->assertSame(67291, $this->noradOf('RS95S_[FM]'), 'before parentheses: RS95S (QMR-KWT-2)');
        $this->assertSame(60237, $this->noradOf('GRBBeta_[UHF_Digi]'), 'case: GRBBETA');
    }

    public function testAllModesOfOneSpacecraftGetTheSameId(): void
    {
        $this->addCatalog('QO-100_[NB]', 'QO-100_[WB]');

        $result = gpdata_sync($this->db, 'manual', self::FEED);

        $this->assertSame(43700, $this->noradOf('QO-100_[NB]'));
        $this->assertSame(43700, $this->noradOf('QO-100_[WB]'));
        // Reported once per spacecraft, not per mode row.
        $qo = array_values(array_filter($result['changed'], static fn ($c) => $c['spacecraft'] === 'QO-100'));
        $this->assertCount(1, $qo);
    }

    public function testWrongIdIsCorrectedAndRightIdLeftAlone(): void
    {
        $this->db->query("UPDATE satellite_name SET norad_cat_id = 1 WHERE html_element_name = 'AO-91'");
        $this->db->query("UPDATE satellite_name SET norad_cat_id = 24278 WHERE html_element_name = 'FO-29'");

        $result = gpdata_sync($this->db, 'manual', self::FEED);

        $this->assertSame(43017, $this->noradOf('AO-91'));
        $this->assertContains(['spacecraft' => 'AO-91', 'from' => 1, 'to' => 43017], $result['changed']);
        $this->assertNotContains('FO-29', array_column($result['changed'], 'spacecraft'));
    }

    public function testSecondRunChangesNothing(): void
    {
        gpdata_sync($this->db, 'manual', self::FEED);
        $second = gpdata_sync($this->db, 'scheduled', self::FEED);

        $this->assertSame('ok', $second['status']);
        $this->assertSame([], $second['changed']);
        $this->assertSame(2, $this->countRows('gpdata_sync_run', '1=1'));
    }

    public function testSpacecraftNotInFeedKeepsItsId(): void
    {
        $this->addCatalog('CO-128_[TLM]');
        $this->db->query("UPDATE satellite_name SET norad_cat_id = 99999 WHERE html_element_name = 'CO-128_[TLM]'");

        $result = gpdata_sync($this->db, 'manual', self::FEED);

        $this->assertSame(99999, $this->noradOf('CO-128_[TLM]'));
        $this->assertContains('CO-128', $result['not_in_feed']);
    }

    public function testSpacecraftInFeedWithoutIdKeepsItsId(): void
    {
        $this->addCatalog('LILIUM-4_[VHF_Digi]');
        $this->db->query("UPDATE satellite_name SET norad_cat_id = 12345 WHERE html_element_name = 'LILIUM-4_[VHF_Digi]'");

        $result = gpdata_sync($this->db, 'manual', self::FEED);

        $this->assertSame(12345, $this->noradOf('LILIUM-4_[VHF_Digi]'));
        $this->assertContains('LILIUM-4', $result['in_feed_without_id']);
    }

    public function testAmbiguousNameIsSkipped(): void
    {
        // Two feed records answer to "AO 91" with different IDs.
        $feed = $this->feedWith([
            ['AMSAT_NAME' => 'AO-91', 'NORAD_CAT_ID' => 43017],
            ['AMSAT_NAME' => 'FOX-1B (AO-91)', 'NORAD_CAT_ID' => 99001],
        ]);

        $result = gpdata_sync($this->db, 'manual', $feed);

        $this->assertSame('ok', $result['status']);
        $this->assertNull($this->noradOf('AO-91'));
        $this->assertSame([['spacecraft' => 'AO-91', 'feed' => ['AO-91', 'FOX-1B (AO-91)']]], $result['ambiguous']);
        $this->assertSame(25544, $this->noradOf('ISS'), 'other spacecraft still sync');
    }

    /**
     * @return array<string, array{callable(self): string, string}>
     */
    public static function unusableFeeds(): array
    {
        return [
            'missing file' => [static fn (self $t) => '/nonexistent/daily-bulletin.json', 'could not be read'],
            'not JSON' => [static fn (self $t) => $t->tempFile('<html>Bad Gateway</html>'), 'not valid JSON'],
            'not a list' => [static fn (self $t) => $t->tempFile('{"AMSAT_NAME": "ISS"}'), 'not a JSON list'],
            'too few records' => [static fn (self $t) => $t->tempFile('[{"AMSAT_NAME": "ISS", "NORAD_CAT_ID": 25544}]'), 'at least 20'],
            'malformed ID' => [static fn (self $t) => $t->feedWith([['AMSAT_NAME' => 'ISS', 'NORAD_CAT_ID' => '25544']]), 'invalid NORAD_CAT_ID'],
            'record without name' => [static fn (self $t) => $t->feedWith([['NORAD_CAT_ID' => 25544]]), 'no AMSAT_NAME'],
            'plain http' => [static fn (self $t) => 'http://example.com/daily-bulletin.json', 'https://'],
        ];
    }

    /**
     * @param callable(self): string $feed
     */
    #[DataProvider('unusableFeeds')]
    public function testUnusableFeedAbortsWithNoWrites(callable $feed, string $reason): void
    {
        $this->db->query("UPDATE satellite_name SET norad_cat_id = 1 WHERE html_element_name = 'AO-91'");

        $result = gpdata_sync($this->db, 'scheduled', $feed($this));

        $this->assertSame('aborted', $result['status']);
        $this->assertStringContainsString($reason, (string) $result['reason']);
        $this->assertNull($result['matched']);
        $this->assertSame(1, $this->noradOf('AO-91'), 'nothing written');
        $this->assertNull($this->noradOf('ISS'), 'nothing written');
        $this->assertSame(1, $this->countRows('gpdata_sync_run', "status = 'aborted'"));
    }

    public function testOnlyOneRunAtATime(): void
    {
        $other = new \mysqli(
            getenv('TEST_DB_HOST') ?: '127.0.0.1',
            getenv('TEST_DB_USER') ?: 'satstatus',
            getenv('TEST_DB_PASS') ?: 'satstatus',
            getenv('TEST_DB_NAME') ?: 'satstatus',
            (int) (getenv('TEST_DB_PORT') ?: 3307)
        );
        $other->query("SELECT GET_LOCK('" . GPDATA_LOCK_NAME . "', 0)");

        try {
            $result = gpdata_sync($this->db, 'manual', self::FEED);
        } finally {
            $other->query("SELECT RELEASE_LOCK('" . GPDATA_LOCK_NAME . "')");
            $other->close();
        }

        $this->assertSame('busy', $result['status']);
        $this->assertSame(0, $this->countRows('gpdata_sync_run', '1=1'));
        $this->assertNull($this->noradOf('ISS'));
    }

    // ── Endpoint ───────────────────────────────────────────────────────

    public function testEndpointRequiresToken(): void
    {
        $client = $this->newGuestClient();

        $none = $client->post('/api/v1/gpdata-sync.php');
        $wrong = $client->post('/api/v1/gpdata-sync.php', ['headers' => ['Authorization' => 'Bearer wrong']]);
        $basic = $client->post('/api/v1/gpdata-sync.php', ['headers' => ['Authorization' => 'Basic ' . base64_encode('x:' . $this->token())]]);

        foreach (['no token' => $none, 'wrong token' => $wrong, 'not Bearer' => $basic] as $label => $resp) {
            $this->assertSame(401, $resp->getStatusCode(), $label);
            $this->assertSame('unauthorized', json_decode((string) $resp->getBody(), true)['error']['code'], $label);
        }
        $this->assertSame('Bearer realm="gpdata-sync"', $none->getHeaderLine('WWW-Authenticate'));
        $this->assertSame(0, $this->countRows('gpdata_sync_run', '1=1'));
    }

    public function testEndpointRejectsGet(): void
    {
        $resp = $this->newGuestClient()->get('/api/v1/gpdata-sync.php');

        $this->assertSame(405, $resp->getStatusCode());
    }

    public function testEndpointRunsSyncWithToken(): void
    {
        $resp = $this->newGuestClient()->post('/api/v1/gpdata-sync.php', [
            'headers' => ['Authorization' => 'Bearer ' . $this->token()],
        ]);

        $this->assertSame(200, $resp->getStatusCode(), (string) $resp->getBody());
        $payload = json_decode((string) $resp->getBody(), true);
        $this->assertSame('ok', $payload['data']['status']);
        $this->assertSame('manual', $payload['data']['trigger']);
        $this->assertSame(3, $payload['data']['matched']);
        $this->assertSame($resp->getHeaderLine('X-AMSAT-API-Version'), $payload['meta']['api_version']);
        $this->assertSame(25544, $this->noradOf('ISS'));
    }

    // ── Scheduled job ──────────────────────────────────────────────────

    public function testScheduledCliJobRunsSync(): void
    {
        $cmd = 'GPDATA_FEED_URL=' . escapeshellarg(self::FEED) . ' '
            . escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg(__DIR__ . '/../api/bin/gpdata-sync.php') . ' 2>&1';

        exec($cmd, $output, $exitCode);

        $this->assertSame(0, $exitCode, implode("\n", $output));
        $line = json_decode((string) end($output), true);
        $this->assertSame('ok', $line['gpdata_sync']['status']);
        $this->assertSame('scheduled', $line['gpdata_sync']['trigger']);
        $this->assertSame(1, $this->countRows('gpdata_sync_run', "trigger_source = 'scheduled'"));
    }

    // ── API fields ─────────────────────────────────────────────────────

    public function testCatalogAndReportsExposeNoradCatId(): void
    {
        $before = $this->catalogEntry('ISS');
        $this->assertArrayHasKey('norad_cat_id', $before);
        $this->assertNull($before['norad_cat_id']);

        gpdata_sync($this->db, 'manual', self::FEED);

        $this->assertSame(25544, $this->catalogEntry('ISS')['norad_cat_id']);

        $reports = json_decode((string) $this->newGuestClient()->get('/api/v1/reports.php', [
            'query' => ['name' => 'AO-91', 'hours' => 72],
        ])->getBody(), true)['data'];
        $this->assertNotEmpty($reports);
        foreach ($reports as $report) {
            $this->assertSame(43017, $report['norad_cat_id']);
        }
    }

    // ── Admin "Sync now" ───────────────────────────────────────────────

    public function testAdminSyncNowRunsSyncAndShowsResult(): void
    {
        $client = $this->login();
        $dashboard = (string) $client->get('/admin/dashboard.php')->getBody();
        $this->assertStringContainsString('Sync now', $dashboard);
        $this->assertStringContainsString('Last run:', $dashboard);
        $this->assertMatchesRegularExpression('/name="csrf_token" value="([0-9a-f]{64})"/', $dashboard);
        preg_match('/name="csrf_token" value="([0-9a-f]{64})"/', $dashboard, $m);

        $resp = $client->post('/admin/gpdata_sync.php', [
            'form_params' => ['csrf_token' => $m[1]],
            'allow_redirects' => false,
        ]);

        $this->assertSame(302, $resp->getStatusCode());
        $this->assertSame(25544, $this->noradOf('ISS'));
        $this->assertSame(1, $this->countRows('gpdata_sync_run', "trigger_source = 'manual' AND status = 'ok'"));

        $after = (string) $client->get('/admin/dashboard.php')->getBody();
        $this->assertStringContainsString('Sync ok', $after);
        $this->assertStringContainsString('3 spacecraft matched', $after);

        $manage = (string) $client->get('/admin/manage_satellites.php')->getBody();
        $this->assertStringContainsString('<td>25544</td>', $manage);
    }

    public function testAdminSyncNowRequiresCsrfToken(): void
    {
        $client = $this->login();
        $client->get('/admin/dashboard.php');

        $resp = $client->post('/admin/gpdata_sync.php', [
            'form_params' => ['csrf_token' => str_repeat('0', 64)],
            'allow_redirects' => false,
        ]);

        $this->assertSame(302, $resp->getStatusCode());
        $this->assertSame(0, $this->countRows('gpdata_sync_run', '1=1'));
    }

    public function testAdminSyncNowRequiresLogin(): void
    {
        $resp = $this->newGuestClient()->post('/admin/gpdata_sync.php', ['allow_redirects' => false]);

        $this->assertSame(302, $resp->getStatusCode());
        $this->assertStringContainsString('/admin/index.php', $resp->getHeaderLine('Location'));
        $this->assertSame(0, $this->countRows('gpdata_sync_run', '1=1'));
    }

    // ── Helpers ────────────────────────────────────────────────────────

    private function token(): string
    {
        $token = (string) getenv(self::TOKEN_ENV);
        if ($token === '') {
            $this->markTestSkipped(self::TOKEN_ENV . ' is not set for this test run.');
        }

        return $token;
    }

    private function addCatalog(string ...$apiNames): void
    {
        $stmt = $this->db->prepare("INSERT INTO satellite_name (name, html_element_name, website) VALUES (?, ?, '')");
        foreach ($apiNames as $apiName) {
            $display = str_replace('_', ' ', $apiName);
            $stmt->bind_param('ss', $display, $apiName);
            $stmt->execute();
        }
        $stmt->close();
    }

    private function noradOf(string $apiName): ?int
    {
        $stmt = $this->db->prepare('SELECT norad_cat_id FROM satellite_name WHERE html_element_name = ?');
        $stmt->bind_param('s', $apiName);
        $stmt->execute();
        $value = $stmt->get_result()->fetch_row()[0] ?? null;
        $stmt->close();

        return $value === null ? null : (int) $value;
    }

    /**
     * @return array<string, mixed>
     */
    private function catalogEntry(string $name): array
    {
        $payload = json_decode((string) $this->newGuestClient()->get('/api/v1/catalog.php', [
            'query' => ['name' => $name],
        ])->getBody(), true);

        return $payload['data'][0];
    }

    /**
     * The fixture feed with extra records appended (keeps it over the
     * minimum record count, so only the extra records decide the outcome).
     *
     * @param list<array<string, mixed>> $extra
     */
    public function feedWith(array $extra): string
    {
        $records = json_decode((string) file_get_contents(self::FEED), true);
        $records = array_values(array_filter($records, static fn ($r) => !in_array($r['AMSAT_NAME'], ['AO-91'], true)));

        return $this->tempFile((string) json_encode(array_merge($records, $extra)));
    }

    public function tempFile(string $content): string
    {
        $file = tempnam(sys_get_temp_dir(), 'gpfeed');
        file_put_contents($file, $content);
        $this->tempFiles[] = $file;

        return $file;
    }
}
