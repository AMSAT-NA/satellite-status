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
        $this->assertNotSame($firstPayload['data']['id'], $secondPayload['data']['id']);
        $this->assertSame(2, $this->countRows('satellite', "callsign='W5DUP'"));

        $reports = $this->db->query("SELECT report FROM satellite WHERE callsign='W5DUP' ORDER BY id ASC");
        $this->assertSame('Heard', $reports->fetch_assoc()['report']);
        $this->assertSame('Not Heard', $reports->fetch_assoc()['report']);
    }

    public function testPostReportIgnoresSpoofedSubmittedAt(): void
    {
        $resp = $this->newGuestClient()->post('/api/v1/reports.php', [
            'json' => [
                'name' => 'AO-91',
                'report' => 'Heard',
                'callsign' => 'W5SPF',
                'reported_at' => gmdate('Y-m-d\TH:i:s\Z', time() - 3600),
                'submitted_at' => '2000-01-01T00:00:00Z',
            ],
        ]);

        $this->assertSame(201, $resp->getStatusCode());
        $submittedAt = $this->db->query(
            "SELECT submitted_at FROM satellite WHERE callsign='W5SPF'"
        )->fetch_assoc()['submitted_at'];

        $this->assertNotNull($submittedAt);
        $this->assertGreaterThan(
            strtotime('2000-01-02T00:00:00Z'),
            strtotime($submittedAt . ' UTC'),
            'submitted_at must be server-generated, not the spoofed request value'
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

    public function testSummaryGroupingIsUnchangedByObservedAtMigration(): void
    {
        // Issue #24 regression: summary()'s GROUP BY (name, display_name,
        // report) and aggregation logic are untouched by the day/hour/
        // period -> observed_at read-path rewrite -- only the precision
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

    public function testSwaggerDocsPageIsPublic(): void
    {
        $resp = $this->newGuestClient()->get('/api/v1/docs.php');

        $this->assertSame(200, $resp->getStatusCode());
        $body = (string) $resp->getBody();
        $this->assertStringContainsString('AMSAT Satellite Status API Docs', $body);
        $this->assertStringContainsString('swagger-ui', $body);
        $this->assertStringContainsString('./openapi.php', $body);
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

    public function testUnsupportedMethodsReturn405(): void
    {
        $resp = $this->newGuestClient()->delete('/api/v1/reports.php');

        $this->assertSame(405, $resp->getStatusCode());
        $this->assertSame('GET, POST', $resp->getHeaderLine('Allow'));
        $payload = json_decode((string) $resp->getBody(), true);
        $this->assertSame('method_not_allowed', $payload['error']['code']);
    }
}
