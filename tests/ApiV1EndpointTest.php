<?php
declare(strict_types=1);

namespace AmsatStatus\Tests;

final class ApiV1EndpointTest extends TestCase
{
    public function testCatalogReturnsEnvelopeWithLinks(): void
    {
        $resp = $this->newGuestClient()->get('/api/v1/catalog.php', [
            'query' => ['include_stats' => 'true'],
        ]);

        $this->assertSame(200, $resp->getStatusCode());
        $this->assertSame('application/json; charset=utf-8', $resp->getHeaderLine('Content-Type'));

        $payload = json_decode((string) $resp->getBody(), true);
        $this->assertIsArray($payload);
        $this->assertArrayHasKey('data', $payload);
        $this->assertCount(3, $payload['data']);
        $this->assertSame('AO-91', $payload['data'][0]['name']);
        $this->assertArrayHasKey('links', $payload['data'][0]);
        $this->assertArrayHasKey('report_count', $payload['data'][0]);
    }

    public function testReportsSearchFiltersBySatellite(): void
    {
        $resp = $this->newGuestClient()->get('/api/v1/reports.php', [
            'query' => ['name' => 'AO-91', 'hours' => 72],
        ]);

        $this->assertSame(200, $resp->getStatusCode());
        $payload = json_decode((string) $resp->getBody(), true);
        $this->assertIsArray($payload);
        $this->assertArrayHasKey('data', $payload);
        $this->assertNotEmpty($payload['data']);
        $this->assertSame('AO-91', $payload['data'][0]['name']);
        $this->assertArrayHasKey('meta', $payload);
    }

    public function testReportsRejectInvalidHours(): void
    {
        $resp = $this->newGuestClient()->get('/api/v1/reports.php', [
            'query' => ['hours' => 'not-a-number'],
        ]);

        $this->assertSame(400, $resp->getStatusCode());
        $payload = json_decode((string) $resp->getBody(), true);
        $this->assertSame('invalid_parameter', $payload['error']['code']);
    }

    public function testReportsRejectInvalidGridSquare(): void
    {
        $resp = $this->newGuestClient()->get('/api/v1/reports.php', [
            'query' => ['grid_square' => 'BADGRID'],
        ]);

        $this->assertSame(400, $resp->getStatusCode());
        $payload = json_decode((string) $resp->getBody(), true);
        $this->assertSame('invalid_grid_square', $payload['error']['code']);
    }

    public function testReportsRejectUnknownSatellite(): void
    {
        $resp = $this->newGuestClient()->get('/api/v1/reports.php', [
            'query' => ['name' => 'NO-SUCH-SAT'],
        ]);

        $this->assertSame(404, $resp->getStatusCode());
        $payload = json_decode((string) $resp->getBody(), true);
        $this->assertSame('satellite_not_found', $payload['error']['code']);
    }

    /**
     * Issue #58: the name check accepts a display name (the catalog lookup
     * matches either), but reports are stored under the API name. A
     * display name must be resolved to the API name before filtering,
     * not return 200 with an empty list.
     */
    public function testReportsResolveDisplayNameToApiName(): void
    {
        $byApiName = json_decode((string) $this->newGuestClient()->get('/api/v1/reports.php', [
            'query' => ['name' => 'AO-91', 'hours' => 72],
        ])->getBody(), true);

        $resp = $this->newGuestClient()->get('/api/v1/reports.php', [
            'query' => ['name' => 'AO-91 Mode U/v FM', 'hours' => 72],
        ]);

        $this->assertSame(200, $resp->getStatusCode());
        $payload = json_decode((string) $resp->getBody(), true);
        $this->assertNotEmpty($payload['data']);
        $this->assertSame($byApiName['data'], $payload['data']);
        $this->assertSame('AO-91', $payload['data'][0]['name']);
    }

    /**
     * Issue #58, write side: a POST with a display name used to pass
     * validation and be stored under the display name, so the report
     * never appeared on the status grid or in GET /reports.php. It must
     * be stored, and returned, under the API name.
     */
    public function testPostReportWithDisplayNameStoresApiName(): void
    {
        $resp = $this->newGuestClient()->post('/api/v1/reports.php', [
            'json' => [
                'name' => 'AO-91 Mode U/v FM',
                'report' => 'Heard',
                'callsign' => 'W5DSP',
                'reported_at' => gmdate('Y-m-d\TH:i:s\Z', time() - 3600),
            ],
        ]);

        $this->assertSame(201, $resp->getStatusCode());
        $payload = json_decode((string) $resp->getBody(), true);
        $this->assertSame('AO-91', $payload['data']['name']);
        $this->assertSame(1, $this->countRows('satellite', "callsign='W5DSP' AND name='AO-91'"));
        $this->assertSame(0, $this->countRows('satellite', "name='AO-91 Mode U/v FM'"));
    }

    public function testPostReportCreatesReportAndExtractsGridSquare(): void
    {
        $resp = $this->newGuestClient()->post('/api/v1/reports.php', [
            'json' => [
                'name' => 'AO-91',
                'report' => 'Telemetry',
                'callsign' => 'W5API/EM25',
                'reported_at' => gmdate('Y-m-d\TH:i:s\Z', time() - 3600),
            ],
        ]);

        $this->assertSame(201, $resp->getStatusCode());
        $payload = json_decode((string) $resp->getBody(), true);
        $this->assertSame('Telemetry Only', $payload['data']['report']);
        $this->assertSame('W5API', $payload['data']['callsign']);
        $this->assertSame('EM25', $payload['data']['grid_square']);
        $this->assertSame(1, $this->countRows('satellite', "callsign='W5API'"));
    }

    public function testPostReportDoesNotReplaceDuplicate(): void
    {
        // Issue #24: writes are non-destructive now -- submitting twice
        // for the same satellite/callsign/time stores both reports
        // rather than deleting-and-replacing the first.
        $reportedAt = gmdate('Y-m-d\TH:20:00\Z', time() - 3600);
        $client = $this->newGuestClient();
        $first = $client->post('/api/v1/reports.php', [
            'json' => [
                'name' => 'AO-91',
                'report' => 'Heard',
                'callsign' => 'W5DUP',
                'grid_square' => 'EM25',
                'reported_at' => $reportedAt,
            ],
        ]);
        $second = $client->post('/api/v1/reports.php', [
            'json' => [
                'name' => 'AO-91',
                'report' => 'Not Heard',
                'callsign' => 'W5DUP',
                'grid_square' => 'EM25',
                'reported_at' => $reportedAt,
            ],
        ]);

        $this->assertSame(201, $first->getStatusCode());
        $this->assertSame(201, $second->getStatusCode());
        $firstPayload = json_decode((string) $first->getBody(), true);
        $secondPayload = json_decode((string) $second->getBody(), true);
        $this->assertArrayNotHasKey('replaced_count', $firstPayload['data']);
        $this->assertArrayNotHasKey('replaced_count', $secondPayload['data']);
        // PR #30: replaced_count's replacement is the boolean correction.
        $this->assertFalse($firstPayload['data']['correction']);
        $this->assertTrue($secondPayload['data']['correction']);
        $this->assertNotSame($firstPayload['data']['id'], $secondPayload['data']['id']);
        $this->assertSame(2, $this->countRows('satellite', "callsign='W5DUP'"));

        $reports = $this->db->query("SELECT report FROM satellite WHERE callsign='W5DUP' ORDER BY id ASC");
        $this->assertSame('Heard', $reports->fetch_assoc()['report']);
        $this->assertSame('Not Heard', $reports->fetch_assoc()['report']);
    }

    public function testPostReportInDifferentPeriodIsNotCorrection(): void
    {
        // :10 and :20 are different 15-minute periods, so the second
        // report is a separate observation, not a correction -- exactly the
        // mistake `correction: false` lets a client catch.
        $client = $this->newGuestClient();
        $base = gmdate('Y-m-d\TH', time() - 3600);
        foreach (['10' => false, '20' => false] as $minute => $expected) {
            $resp = $client->post('/api/v1/reports.php', [
                'json' => [
                    'name' => 'AO-91',
                    'report' => 'Heard',
                    'callsign' => 'W5PER',
                    'reported_at' => "{$base}:{$minute}:00Z",
                ],
            ]);
            $this->assertSame(201, $resp->getStatusCode());
            $this->assertSame($expected, json_decode((string) $resp->getBody(), true)['data']['correction']);
        }
    }

    public function testPostReportIgnoresSpoofedSubmissionTime(): void
    {
        $resp = $this->newGuestClient()->post('/api/v1/reports.php', [
            'json' => [
                'name' => 'AO-91',
                'report' => 'Heard',
                'callsign' => 'W5SPF',
                'reported_at' => gmdate('Y-m-d\TH:i:s\Z', time() - 3600),
                'submission_time' => '2000-01-01T00:00:00Z',
            ],
        ]);

        $this->assertSame(201, $resp->getStatusCode());
        $submissionTime = $this->db->query(
            "SELECT submission_time FROM satellite WHERE callsign='W5SPF'"
        )->fetch_assoc()['submission_time'];

        $this->assertNotNull($submissionTime);
        $this->assertGreaterThan(
            strtotime('2000-01-02T00:00:00Z'),
            strtotime($submissionTime . ' UTC'),
            'submission_time must be server-generated, not the spoofed request value'
        );
    }

    public function testGetReportsResponseHasNoPeriodField(): void
    {
        $resp = $this->newGuestClient()->get('/api/v1/reports.php', [
            'query' => ['name' => 'AO-91', 'hours' => 72],
        ]);

        $payload = json_decode((string) $resp->getBody(), true);
        $this->assertNotEmpty($payload['data']);
        $this->assertArrayNotHasKey('period', $payload['data'][0]);
    }

    public function testPostReportPreservesMinutePrecision(): void
    {
        // Core proof of the display-bug fix: previously every reported_time
        // showed :30:00 regardless of the real submitted minute. Uses
        // yesterday's date at a fixed, clearly-not-:30 time.
        $reportedAt = gmdate('Y-m-d', time() - 86400) . 'T14:07:00Z';
        $resp = $this->newGuestClient()->post('/api/v1/reports.php', [
            'json' => [
                'name' => 'AO-91',
                'report' => 'Heard',
                'callsign' => 'W5PRC',
                'reported_at' => $reportedAt,
            ],
        ]);

        $this->assertSame(201, $resp->getStatusCode());
        $payload = json_decode((string) $resp->getBody(), true);
        $this->assertSame($reportedAt, $payload['data']['reported_time']);
        $this->assertStringNotContainsString(':30:00Z', $payload['data']['reported_time']);

        $getResp = $this->newGuestClient()->get('/api/v1/reports.php', [
            'query' => ['callsign' => 'W5PRC'],
        ]);
        $getPayload = json_decode((string) $getResp->getBody(), true);
        $this->assertSame($reportedAt, $getPayload['data'][0]['reported_time']);
    }

    public function testGetReportsReportedTimeMatchesFormatContract(): void
    {
        // Distinct from testPostReportPreservesMinutePrecision above,
        // which checks the *value* is accurate -- this locks the *shape*
        // regardless of value. External consumers (confirmed: Ionaut,
        // via A65RW) parse reported_time strictly against exactly
        // YYYY-MM-DDTHH:MM:SSZ and silently drop any row they can't
        // parse, so a format regression here (wrong separator, missing
        // Z, an offset instead of Z, etc.) would be a silent data-loss
        // bug for them, not a visible break -- worth catching here
        // before it ships.
        $resp = $this->newGuestClient()->get('/api/v1/reports.php', [
            'query' => ['name' => 'AO-91', 'hours' => 72],
        ]);

        $payload = json_decode((string) $resp->getBody(), true);
        $this->assertNotEmpty($payload['data']);
        $this->assertMatchesRegularExpression(
            '/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}Z$/',
            $payload['data'][0]['reported_time']
        );
    }

    public function testPostReportReportedTimeMatchesFormatContract(): void
    {
        // See testGetReportsReportedTimeMatchesFormatContract above for
        // why this format is a real external contract, not cosmetic.
        $resp = $this->newGuestClient()->post('/api/v1/reports.php', [
            'json' => [
                'name' => 'AO-91',
                'report' => 'Heard',
                'callsign' => 'W5FMT',
                'reported_at' => gmdate('Y-m-d\TH:i:s\Z', time() - 3600),
            ],
        ]);

        $this->assertSame(201, $resp->getStatusCode());
        $payload = json_decode((string) $resp->getBody(), true);
        $this->assertMatchesRegularExpression(
            '/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}Z$/',
            $payload['data']['reported_time']
        );
    }

    public function testReportsSinceFilterIsRealTimestampComparison(): void
    {
        $client = $this->newGuestClient();
        $justBefore = gmdate('Y-m-d\TH:i:s\Z', time() - 7200);
        $justAfter = gmdate('Y-m-d\TH:i:s\Z', time() - 1800);

        $client->post('/api/v1/reports.php', [
            'json' => [
                'name' => 'AO-91', 'report' => 'Heard', 'callsign' => 'W5OLD',
                'reported_at' => $justBefore,
            ],
        ]);
        $client->post('/api/v1/reports.php', [
            'json' => [
                'name' => 'AO-91', 'report' => 'Heard', 'callsign' => 'W5NEW',
                'reported_at' => $justAfter,
            ],
        ]);

        $since = gmdate('Y-m-d\TH:i:s\Z', time() - 3600);
        $resp = $client->get('/api/v1/reports.php', ['query' => ['since' => $since]]);
        $callsigns = array_column(json_decode((string) $resp->getBody(), true)['data'], 'callsign');

        $this->assertContains('W5NEW', $callsigns);
        $this->assertNotContains('W5OLD', $callsigns);
    }

    public function testPostReportRejectsFutureTimestamp(): void
    {
        $resp = $this->newGuestClient()->post('/api/v1/reports.php', [
            'json' => [
                'name' => 'AO-91',
                'report' => 'Heard',
                'callsign' => 'W5FUT',
                'reported_at' => gmdate('Y-m-d\TH:i:s\Z', time() + 3600),
            ],
        ]);

        $this->assertSame(422, $resp->getStatusCode());
        $payload = json_decode((string) $resp->getBody(), true);
        $this->assertSame('future_reported_at', $payload['error']['code']);
        $this->assertSame(0, $this->countRows('satellite', "callsign='W5FUT'"));
    }

    public function testPostReportRejectsInvalidCallsign(): void
    {
        $resp = $this->newGuestClient()->post('/api/v1/reports.php', [
            'json' => [
                'name' => 'AO-91',
                'report' => 'Heard',
                'callsign' => 'BAD!',
                'reported_at' => gmdate('Y-m-d\TH:i:s\Z', time() - 3600),
            ],
        ]);

        $this->assertSame(422, $resp->getStatusCode());
        $payload = json_decode((string) $resp->getBody(), true);
        $this->assertSame('invalid_callsign', $payload['error']['code']);
    }

    public function testSummaryReturnsGroupedCounts(): void
    {
        $resp = $this->newGuestClient()->get('/api/v1/summary.php', [
            'query' => ['hours' => 72],
        ]);

        $this->assertSame(200, $resp->getStatusCode());
        $payload = json_decode((string) $resp->getBody(), true);
        $this->assertIsArray($payload['data']);
        $this->assertNotEmpty($payload['data']);
        $this->assertArrayHasKey('report_count', $payload['data'][0]);
    }

    public function testSummaryGroupingIsUnchangedByObservationTimeMigration(): void
    {
        // Issue #24 regression: summary()'s GROUP BY (name, display_name,
        // report) and aggregation logic are untouched by the day/hour/
        // period -> observation_time read-path rewrite -- only the precision
        // of latest_reported_time should differ. TestCase's fixture seeds
        // two AO-91/Heard reports and one FO-29/Heard report.
        $resp = $this->newGuestClient()->get('/api/v1/summary.php', [
            'query' => ['hours' => 72],
        ]);
        $payload = json_decode((string) $resp->getBody(), true);

        $rows = array_filter(
            $payload['data'],
            static fn (array $row) => $row['name'] === 'AO-91' && $row['report'] === 'Heard'
        );
        $ao91Row = array_values($rows)[0];
        $this->assertSame(2, $ao91Row['report_count']);

        $fo29Rows = array_values(array_filter(
            $payload['data'],
            static fn (array $row) => $row['name'] === 'FO-29' && $row['report'] === 'Heard'
        ));
        $this->assertSame(1, $fo29Rows[0]['report_count']);
    }

    /**
     * Inserts ISS reports for yesterday at the given HH:MM (UTC), in order,
     * so later rows get higher ids. Returns nothing; read back via the API.
     *
     * @param array<int, array{0: string, 1: string, 2: string}> $rows [callsign, report, HH:MM]
     */
    private function insertIssReports(array $rows): void
    {
        $yesterday = gmdate('Y-m-d', time() - 86400);
        $stmt = $this->db->prepare(
            'INSERT INTO satellite (name, longname, callsign, report, grid_square, observation_time) '
            . "VALUES ('ISS', 'ISS', ?, ?, 'FN42', ?)"
        );
        foreach ($rows as [$callsign, $report, $time]) {
            $observationTime = "{$yesterday} {$time}:00";
            $stmt->bind_param('sss', $callsign, $report, $observationTime);
            $stmt->execute();
        }
        $stmt->close();
    }

    /** @return array<int, array<string, mixed>> */
    private function getIssReports(array $query = []): array
    {
        $resp = $this->newGuestClient()->get('/api/v1/reports.php', [
            'query' => ['name' => 'ISS', 'hours' => 72] + $query,
        ]);
        $this->assertSame(200, $resp->getStatusCode());
        return json_decode((string) $resp->getBody(), true)['data'];
    }

    public function testSummaryCountsLatestReportPerCallsignPerPeriod(): void
    {
        // PR #30: counts match 1.0's delete+insert and the status grid.
        // W5FIX corrects Heard -> Not Heard within one 15-minute period
        // (counts once, as Not Heard); W5REP submits Heard three times in
        // one period (counts once); W5TWO reports in two different periods
        // (counts twice, as under 1.0). Expected ISS: Heard 3, Not Heard 1.
        $this->insertIssReports([
            ['W5FIX', 'Heard',     '10:05'],
            ['W5FIX', 'Not Heard', '10:10'],
            ['W5REP', 'Heard',     '14:01'],
            ['W5REP', 'Heard',     '14:02'],
            ['W5REP', 'Heard',     '14:03'],
            ['W5TWO', 'Heard',     '16:05'],
            ['W5TWO', 'Heard',     '16:40'],
        ]);

        $resp = $this->newGuestClient()->get('/api/v1/summary.php', [
            'query' => ['hours' => 72],
        ]);
        $this->assertSame(200, $resp->getStatusCode());
        $payload = json_decode((string) $resp->getBody(), true);

        $iss = [];
        foreach ($payload['data'] as $row) {
            if ($row['name'] === 'ISS') {
                $iss[$row['report']] = $row['report_count'];
            }
        }
        ksort($iss);
        $this->assertSame(['Heard' => 3, 'Not Heard' => 1], $iss);
    }

    public function testReportsOmitCorrectedReport(): void
    {
        // PR #30: a resubmission in the same 15-minute period hides the
        // earlier report, as 1.0's delete+insert did.
        $this->insertIssReports([
            ['W5FIX', 'Heard',     '10:05'],
            ['W5FIX', 'Not Heard', '10:10'],
        ]);

        $data = $this->getIssReports();

        $this->assertCount(1, $data);
        $this->assertSame('Not Heard', $data[0]['report']);
        $this->assertStringEndsWith('T10:10:00Z', $data[0]['reported_time']);
    }

    public function testReportsKeepSameCallsignInDifferentPeriods(): void
    {
        // Different 15-minute periods never replaced each other under 1.0.
        $this->insertIssReports([
            ['W5TWO', 'Heard',     '10:05'],
            ['W5TWO', 'Not Heard', '10:40'],
        ]);

        $this->assertCount(2, $this->getIssReports());
    }

    public function testReportsStatusFilterDoesNotReturnCorrectedReport(): void
    {
        // The status filter applies after corrections are resolved, so the
        // corrected Heard report must not come back for status=Heard.
        $this->insertIssReports([
            ['W5FIX', 'Heard',     '10:05'],
            ['W5FIX', 'Not Heard', '10:10'],
        ]);

        $this->assertSame([], $this->getIssReports(['status' => 'Heard']));
        $this->assertCount(1, $this->getIssReports(['status' => 'Not Heard']));
    }

    public function testReportsSinceInsidePeriodStillHonoursCorrection(): void
    {
        // The correction is submitted later (higher id) but entered with an
        // earlier minute (10:01) in the same period as the original (10:05). The
        // original is inside the since window, the correction is not; the
        // original must still be treated as corrected, not resurface.
        $this->insertIssReports([
            ['W5FIX', 'Heard',     '10:05'],
            ['W5FIX', 'Not Heard', '10:01'],
        ]);
        $since = gmdate('Y-m-d', time() - 86400) . 'T10:03:00Z';

        $this->assertSame([], $this->getIssReports(['since' => $since]));
    }

    public function testLegacySatInfoOmitsCorrectedReport(): void
    {
        $this->insertIssReports([
            ['W5FIX', 'Heard',     '10:05'],
            ['W5FIX', 'Not Heard', '10:10'],
        ]);

        $resp = $this->newGuestClient()->get('/api/v1/sat_info.php', [
            'query' => ['name' => 'ISS', 'hours' => 72],
        ]);
        $rows = json_decode((string) $resp->getBody(), true);

        $this->assertCount(1, $rows);
        $this->assertSame('Not Heard', $rows[0]['report']);
    }

    public function testStatusesReturnsCanonicalValues(): void
    {
        $resp = $this->newGuestClient()->get('/api/v1/statuses.php');

        $this->assertSame(200, $resp->getStatusCode());
        $payload = json_decode((string) $resp->getBody(), true);
        $this->assertSame(
            ['Heard', 'Telemetry Only', 'Not Heard', 'Crew Active'],
            array_column($payload['data'], 'value')
        );
    }

    public function testHealthReturnsOk(): void
    {
        $resp = $this->newGuestClient()->get('/api/v1/health.php');

        $this->assertSame(200, $resp->getStatusCode());
        $payload = json_decode((string) $resp->getBody(), true);
        $this->assertSame('ok', $payload['data']['status']);
        $this->assertArrayNotHasKey('reason', $payload['data']);

        $freshness = $payload['data']['data_freshness'];
        $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}Z$/', $freshness['latest_report_time']);
        $this->assertIsInt($freshness['age_seconds']);
        $this->assertGreaterThanOrEqual(0, $freshness['age_seconds']);
        $this->assertSame(86400, $freshness['stale_after_seconds']);
    }

    /**
     * Issue #47: an instance that is up but has no reports must not look
     * healthy. Still HTTP 200 (reachable), but "degraded".
     */
    public function testHealthIsDegradedWithNoReports(): void
    {
        $this->db->query('DELETE FROM satellite');

        $resp = $this->newGuestClient()->get('/api/v1/health.php');

        $this->assertSame(200, $resp->getStatusCode());
        $data = json_decode((string) $resp->getBody(), true)['data'];
        $this->assertSame('degraded', $data['status']);
        $this->assertSame('no_reports', $data['reason']);
        $this->assertNull($data['data_freshness']['latest_report_time']);
        $this->assertNull($data['data_freshness']['age_seconds']);
    }

    /**
     * Issue #47: reports exist, but the newest is older than the threshold
     * (e.g. submissions silently stopped).
     */
    public function testHealthIsDegradedWhenNewestReportIsStale(): void
    {
        $this->db->query(
            'UPDATE satellite SET submission_time = NOW() - INTERVAL 2 DAY,'
            . ' observation_time = NOW() - INTERVAL 2 DAY'
        );

        $data = json_decode((string) $this->newGuestClient()->get('/api/v1/health.php')->getBody(), true)['data'];

        $this->assertSame('degraded', $data['status']);
        $this->assertSame('stale_data', $data['reason']);
        $this->assertGreaterThan(86400, $data['data_freshness']['age_seconds']);
    }

    /**
     * Imported pre-#24 rows have no submission_time; freshness falls back to
     * observation_time, so a freshly loaded production dump reads as fresh
     * (not "no data") right after the cutover.
     */
    public function testHealthFallsBackToObservationTimeForImportedRows(): void
    {
        $this->db->query(
            'UPDATE satellite SET submission_time = NULL, observation_time = NOW() - INTERVAL 1 HOUR'
        );

        $data = json_decode((string) $this->newGuestClient()->get('/api/v1/health.php')->getBody(), true)['data'];

        $this->assertSame('ok', $data['status']);
        $this->assertGreaterThanOrEqual(3600 - 120, $data['data_freshness']['age_seconds']);
        $this->assertLessThanOrEqual(3600 + 120, $data['data_freshness']['age_seconds']);
    }

    public function testOpenApiDocumentIsAvailable(): void
    {
        $resp = $this->newGuestClient()->get('/api/v1/openapi.php');

        $this->assertSame(200, $resp->getStatusCode());
        $payload = json_decode((string) $resp->getBody(), true);
        $this->assertSame('3.0.3', $payload['openapi']);
        $this->assertArrayHasKey('/reports.php', $payload['paths']);
        $this->assertArrayHasKey('/sat_info.php', $payload['paths']);
        $this->assertArrayHasKey('/satellites.php', $payload['paths']);
    }

    /**
     * Issue #32: API_VERSION (api/v1/lib/version.php) is the single source
     * of truth. The OpenAPI document must carry the same value as the
     * X-AMSAT-API-Version header -- this catches a stale, un-regenerated
     * openapi.json after a version bump. Format is MAJOR.MINOR, and MAJOR
     * must match the /api/v1 URL version.
     */
    public function testApiVersionIsConsistentAcrossHeaderAndOpenApi(): void
    {
        $client = $this->newGuestClient();

        $header = $client->get('/api/v1/health.php')->getHeaderLine('X-AMSAT-API-Version');
        $this->assertMatchesRegularExpression('/^1\.\d+$/', $header);

        $spec = json_decode((string) $client->get('/api/v1/openapi.php')->getBody(), true);
        $this->assertSame($header, $spec['info']['version']);
    }

    public function testSwaggerDocsPageIsPublic(): void
    {
        $resp = $this->newGuestClient()->get('/api/v1/docs.php');

        $this->assertSame(200, $resp->getStatusCode());
        $body = (string) $resp->getBody();
        $this->assertStringContainsString('AMSAT Satellite Status API Docs', $body);
        $this->assertStringContainsString('swagger-ui', $body);
        $this->assertStringContainsString('./openapi.php', $body);
    }

    /**
     * Issue #42: API consumers asked what license and terms apply. The
     * OpenAPI info block is where generated clients and Swagger UI look
     * for that, so pin the license identifier (must match LICENSE and the
     * composer.json / package.json "license" fields), a contact, and a
     * termsOfService link that points at the usage-guidelines anchor on
     * the overview page (checked separately below).
     */
    public function testOpenApiDocumentDeclaresLicenseContactAndTerms(): void
    {
        $resp = $this->newGuestClient()->get('/api/v1/openapi.php');

        $this->assertSame(200, $resp->getStatusCode());
        $info = json_decode((string) $resp->getBody(), true)['info'];

        $this->assertSame('Apache-2.0', $info['license']['name']);
        $this->assertSame('https://www.apache.org/licenses/LICENSE-2.0', $info['license']['url']);
        $this->assertSame('IT@amsat.org', $info['contact']['email']);
        $this->assertStringEndsWith('/api/v1/overview.php#usage', $info['termsOfService']);
    }

    /**
     * Issue #42: the overview page is the human-readable home of the usage
     * policy. Each guideline has its own anchor so issues, emails and the
     * OpenAPI termsOfService link can point at a specific rule; pin those
     * anchors so a page edit can't silently break inbound links.
     */
    public function testOverviewPublishesUsageGuidelines(): void
    {
        $resp = $this->newGuestClient()->get('/api/v1/overview.php');

        $this->assertSame(200, $resp->getStatusCode());
        $body = (string) $resp->getBody();

        foreach (['usage', 'identify', 'polling', 'submitting', 'data', 'license', 'changes', 'support'] as $anchor) {
            $this->assertStringContainsString('id="' . $anchor . '"', $body, "anchor #{$anchor}");
        }

        $this->assertStringContainsString('User-Agent', $body);
        $this->assertStringContainsString('CC BY 4.0', $body);
        $this->assertStringContainsString('Apache License 2.0', $body);
    }

    public function testLegacySatInfoKeepsExactArrayShape(): void
    {
        $resp = $this->newGuestClient()->get('/api/v1/sat_info.php', [
            'query' => ['name' => 'AO-91', 'hours' => 72],
        ]);

        $this->assertSame(200, $resp->getStatusCode());
        $this->assertSame('application/json', $resp->getHeaderLine('Content-Type'));
        $payload = json_decode((string) $resp->getBody(), true);
        $this->assertIsArray($payload);
        $this->assertNotEmpty($payload);
        $this->assertSame(
            ['name', 'reported_time', 'callsign', 'report', 'grid_square'],
            array_keys($payload[0])
        );
        $this->assertArrayNotHasKey('data', $payload);
        $this->assertArrayNotHasKey('meta', $payload);
    }

    public function testLegacySatInfoNonnumericHoursStillReturnsArray(): void
    {
        $resp = $this->newGuestClient()->get('/api/v1/sat_info.php', [
            'query' => ['name' => 'AO-91', 'hours' => 'bogus'],
        ]);

        $this->assertSame(200, $resp->getStatusCode());
        $this->assertIsArray(json_decode((string) $resp->getBody(), true));
    }

    public function testLegacySatInfoUnknownSatelliteReturnsEmptyArray(): void
    {
        $resp = $this->newGuestClient()->get('/api/v1/sat_info.php', [
            'query' => ['name' => 'NO-SUCH-SAT'],
        ]);

        $this->assertSame(200, $resp->getStatusCode());
        $this->assertSame('[]', trim((string) $resp->getBody()));
    }

    public function testLegacySatellitesKeepsExactArrayShape(): void
    {
        $resp = $this->newGuestClient()->get('/api/v1/satellites.php');

        $this->assertSame(200, $resp->getStatusCode());
        $this->assertSame('application/json', $resp->getHeaderLine('Content-Type'));
        $payload = json_decode((string) $resp->getBody(), true);
        $this->assertIsArray($payload);
        $this->assertNotEmpty($payload);
        $this->assertSame(['id', 'name', 'html_element_name', 'website'], array_keys($payload[0]));
        $this->assertArrayNotHasKey('data', $payload);
    }

    public function testApiRootReturnsDiscoveryDocument(): void
    {
        $resp = $this->newGuestClient()->get('/api/v1/');

        $this->assertSame(200, $resp->getStatusCode());
        $this->assertSame('application/json; charset=utf-8', $resp->getHeaderLine('Content-Type'));
        $this->assertSame('*', $resp->getHeaderLine('Access-Control-Allow-Origin'));

        $payload = json_decode((string) $resp->getBody(), true);
        $this->assertIsArray($payload);
        $data = $payload['data'];

        $this->assertSame('AMSAT Satellite Status API', $data['name']);
        $this->assertSame($resp->getHeaderLine('X-AMSAT-API-Version'), $data['version']);

        $this->assertSame(
            ['self', 'overview', 'docs', 'openapi', 'health', 'changelog'],
            array_keys($data['links'])
        );
        $this->assertStringEndsWith('/api/v1/', $data['links']['self']);

        foreach (['overview', 'docs', 'openapi', 'health'] as $rel) {
            $this->assertStringStartsWith($data['links']['self'], $data['links'][$rel], "links.{$rel}");
        }
    }

    /**
     * The discovery document's endpoint list is generated from openapi.json,
     * not hand-maintained -- this pins that: every documented operation is
     * listed, nothing undocumented is, and each entry points at this API.
     */
    public function testApiRootEndpointsMatchOpenApiDocument(): void
    {
        $client = $this->newGuestClient();

        $spec = json_decode((string) $client->get('/api/v1/openapi.php')->getBody(), true);
        $expected = [];
        foreach ($spec['paths'] as $path => $operations) {
            foreach (array_keys($operations) as $method) {
                if (in_array($method, ['get', 'post', 'put', 'patch', 'delete'], true)) {
                    $expected[] = strtoupper($method) . ' ' . $path;
                }
            }
        }

        $data = json_decode((string) $client->get('/api/v1/')->getBody(), true)['data'];
        $actual = array_map(
            static fn (array $e): string => $e['method'] . ' ' . $e['path'],
            $data['endpoints']
        );

        sort($expected);
        sort($actual);
        $this->assertNotEmpty($expected);
        $this->assertSame($expected, $actual);

        foreach ($data['endpoints'] as $endpoint) {
            $this->assertSame(
                rtrim($data['links']['self'], '/') . $endpoint['path'],
                $endpoint['url'],
                "url for {$endpoint['method']} {$endpoint['path']}"
            );
            $this->assertIsBool($endpoint['legacy']);
            $this->assertIsBool($endpoint['deprecated']);
        }

        $legacy = array_column(
            array_filter($data['endpoints'], static fn (array $e): bool => $e['legacy']),
            'path'
        );
        sort($legacy);
        $this->assertSame(['/sat_info.php', '/satellites.php'], $legacy);
    }

    public function testApiRootRejectsNonGetMethods(): void
    {
        $resp = $this->newGuestClient()->post('/api/v1/');

        $this->assertSame(405, $resp->getStatusCode());
        $this->assertSame('GET', $resp->getHeaderLine('Allow'));
    }

    public function testUnsupportedMethodsReturn405(): void
    {
        $resp = $this->newGuestClient()->delete('/api/v1/reports.php');

        $this->assertSame(405, $resp->getStatusCode());
        $this->assertSame('GET, POST', $resp->getHeaderLine('Allow'));
        $payload = json_decode((string) $resp->getBody(), true);
        $this->assertSame('method_not_allowed', $payload['error']['code']);
    }

    /**
     * Issue #42: every field the API actually returns must be described in
     * the OpenAPI document, so the spec can't silently fall behind the code
     * again (summary.php, statuses.php, the legacy arrays, the meta/links
     * envelope and the error body were all undocumented before). Walks each
     * real response against its documented schema, resolving $ref, and
     * fails on any key the schema doesn't list. One call per endpoint and
     * status, named in the failure message, rather than a generic loop.
     */
    public function testEveryReturnedFieldIsDocumentedInOpenApi(): void
    {
        $client = $this->newGuestClient();
        $spec = json_decode((string) $client->get('/api/v1/openapi.php')->getBody(), true);
        $reportedAt = gmdate('Y-m-d\TH:i:s\Z', time() - 3600);

        $cases = [
            ['GET /catalog.php?include_stats=true', '/catalog.php', 'get', 200,
                $client->get('/api/v1/catalog.php', ['query' => ['include_stats' => 'true']])],
            ['GET /catalog.php (unknown name)', '/catalog.php', 'get', 404,
                $client->get('/api/v1/catalog.php', ['query' => ['name' => 'NO-SUCH-SAT']])],
            ['GET /reports.php', '/reports.php', 'get', 200,
                $client->get('/api/v1/reports.php', ['query' => ['hours' => 72]])],
            ['GET /reports.php (bad hours)', '/reports.php', 'get', 400,
                $client->get('/api/v1/reports.php', ['query' => ['hours' => 'x']])],
            ['POST /reports.php', '/reports.php', 'post', 201,
                $client->post('/api/v1/reports.php', ['json' => [
                    'name' => 'AO-91', 'report' => 'Heard', 'callsign' => 'W5DOC',
                    'grid_square' => 'EM25', 'reported_at' => $reportedAt,
                ]])],
            ['POST /reports.php (invalid report)', '/reports.php', 'post', 422,
                $client->post('/api/v1/reports.php', ['json' => [
                    'name' => 'AO-91', 'report' => 'Loud', 'callsign' => 'W5DOC', 'reported_at' => $reportedAt,
                ]])],
            ['GET /summary.php', '/summary.php', 'get', 200,
                $client->get('/api/v1/summary.php', ['query' => ['hours' => 72]])],
            ['GET /statuses.php', '/statuses.php', 'get', 200, $client->get('/api/v1/statuses.php')],
            ['GET /health.php', '/health.php', 'get', 200, $client->get('/api/v1/health.php')],
            ['GET /satellites.php', '/satellites.php', 'get', 200, $client->get('/api/v1/satellites.php')],
            ['GET /sat_info.php', '/sat_info.php', 'get', 200,
                $client->get('/api/v1/sat_info.php', ['query' => ['name' => 'AO-91', 'hours' => 72]])],
        ];

        foreach ($cases as [$label, $path, $method, $status, $resp]) {
            $this->assertSame($status, $resp->getStatusCode(), $label);
            $documented = $spec['paths'][$path][$method]['responses'][(string) $status] ?? null;
            $this->assertNotNull($documented, "{$label}: HTTP {$status} is not documented");
            $schema = $documented['content']['application/json']['schema'] ?? null;
            $this->assertNotNull($schema, "{$label}: HTTP {$status} has no response schema");

            $body = json_decode((string) $resp->getBody(), true);
            $this->assertNotEmpty($body, "{$label}: empty body, nothing to check");
            $this->assertFieldsDocumented($spec, $schema, $body, $label);
        }
    }

    /**
     * @param array<string, mixed> $spec
     * @param array<string, mixed> $schema
     */
    private function assertFieldsDocumented(array $spec, array $schema, mixed $value, string $where): void
    {
        if (isset($schema['$ref'])) {
            $name = substr($schema['$ref'], strlen('#/components/schemas/'));
            $schema = $spec['components']['schemas'][$name];
        }

        if (!is_array($value) || ($schema['additionalProperties'] ?? false) === true) {
            return;
        }

        if (array_is_list($value)) {
            if (($schema['type'] ?? null) === 'array') {
                foreach ($value as $i => $item) {
                    $this->assertFieldsDocumented($spec, $schema['items'], $item, "{$where}[{$i}]");
                }
            }
            return;
        }

        foreach ($value as $key => $child) {
            $this->assertArrayHasKey($key, $schema['properties'] ?? [], "{$where}: field \"{$key}\" is returned but not documented");
            $this->assertFieldsDocumented($spec, $schema['properties'][$key], $child, "{$where}.{$key}");
        }
    }

    /**
     * Issue #42: out-of-range hours and limit are clamped to the nearest
     * bound, not rejected (documented in the OpenAPI parameters and the
     * overview page). meta echoes the value actually applied.
     */
    public function testOutOfRangeHoursAndLimitAreClampedNotRejected(): void
    {
        $client = $this->newGuestClient();

        $reports = $client->get('/api/v1/reports.php', ['query' => ['hours' => 9999, 'limit' => 0]]);
        $this->assertSame(200, $reports->getStatusCode());
        $meta = json_decode((string) $reports->getBody(), true)['meta'];
        $this->assertSame(720, $meta['hours']);
        $this->assertSame(1, $meta['limit']);

        $reports = $client->get('/api/v1/reports.php', ['query' => ['limit' => 100000]]);
        $this->assertSame(500, json_decode((string) $reports->getBody(), true)['meta']['limit']);

        $summary = $client->get('/api/v1/summary.php', ['query' => ['hours' => 0]]);
        $this->assertSame(200, $summary->getStatusCode());
        $this->assertSame(1, json_decode((string) $summary->getBody(), true)['meta']['hours']);
    }

    /**
     * Issue #42: POST /reports.php still accepts the legacy form fields
     * (satellite/status aliases, year/month/day/hour/minute instead of
     * reported_at). Documented, so pinned here -- including the trap that a
     * missing minute defaults to :30.
     */
    public function testPostReportAcceptsDocumentedLegacyFields(): void
    {
        $yesterday = time() - 86400;
        $legacy = [
            'satellite' => 'AO-91',
            'status' => 'heard',
            'callsign' => 'W5LEG',
            'year' => gmdate('Y', $yesterday),
            'month' => gmdate('n', $yesterday),
            'day' => gmdate('j', $yesterday),
            'hour' => '14',
        ];
        $client = $this->newGuestClient();

        $withMinute = $client->post('/api/v1/reports.php', ['form_params' => $legacy + ['minute' => '07']]);
        $this->assertSame(201, $withMinute->getStatusCode());
        $data = json_decode((string) $withMinute->getBody(), true)['data'];
        $this->assertSame('AO-91', $data['name']);
        $this->assertSame('Heard', $data['report']);
        $this->assertSame(gmdate('Y-m-d', $yesterday) . 'T14:07:00Z', $data['reported_time']);

        $legacy['callsign'] = 'W5LEH';
        $noMinute = $client->post('/api/v1/reports.php', ['form_params' => $legacy]);
        $this->assertSame(201, $noMinute->getStatusCode());
        $this->assertSame(
            gmdate('Y-m-d', $yesterday) . 'T14:30:00Z',
            json_decode((string) $noMinute->getBody(), true)['data']['reported_time']
        );
    }
}
