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

    public function testPostReportReplacesDuplicatePeriod(): void
    {
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
        $payload = json_decode((string) $second->getBody(), true);
        $this->assertSame(1, $payload['data']['replaced_count']);
        $this->assertSame(1, $this->countRows('satellite', "callsign='W5DUP'"));
        $this->assertSame(
            'Not Heard',
            $this->db->query("SELECT report FROM satellite WHERE callsign='W5DUP'")->fetch_assoc()['report']
        );
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
