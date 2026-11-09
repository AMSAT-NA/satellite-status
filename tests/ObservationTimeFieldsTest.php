<?php
declare(strict_types=1);

namespace AmsatStatus\Tests;

use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Issue #45 (API 1.3): timestamp field names aligned with the schema, plus
 * meta.api_version from #32.
 *
 *  - POST accepts observation_time: strict ISO 8601 with an explicit offset.
 *    reported_at and year/month/day/hour/minute stay as deprecated aliases
 *    with their old lenient parsing.
 *  - observation_time and reported_at both sent: must agree, else 422.
 *  - Responses add observation_time / submission_time (reports) and
 *    latest_observation_time (catalog, summary); the old names stay as
 *    aliases with identical values.
 *  - submission_time is never taken from the client.
 *  - Every envelope response carries meta.api_version == the header.
 *
 * Existing tests already pin the deprecated paths (reported_at, legacy
 * fields) and the sat_info.php legacy shape; they're unchanged on purpose.
 */
final class ObservationTimeFieldsTest extends TestCase
{
    private const ISO_UTC = '/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}Z$/';

    public function testPostWithObservationTimeEchoesNewAndAliasFields(): void
    {
        $observed = gmdate('Y-m-d\TH:i:00\Z', time() - 3600);

        $payload = $this->postReport(['callsign' => 'W5OBS', 'observation_time' => $observed], 201);

        $this->assertSame($observed, $payload['data']['observation_time']);
        // Deprecated alias, same value.
        $this->assertSame($observed, $payload['data']['reported_time']);
        // Read back from the DB default, so it's the stored receive time.
        $this->assertMatchesRegularExpression(self::ISO_UTC, $payload['data']['submission_time']);
        $this->assertEqualsWithDelta(time(), strtotime($payload['data']['submission_time']), 120);
        $this->assertSame(1, $this->countRows('satellite', "callsign='W5OBS' AND observation_time='" . gmdate('Y-m-d H:i:00', strtotime($observed)) . "'"));
    }

    public function testObservationTimeOffsetIsConvertedToUtc(): void
    {
        // 10:30 at +02:00 is 08:30Z (yesterday, so never in the future).
        $day = gmdate('Y-m-d', time() - 86400);

        $payload = $this->postReport(['callsign' => 'W5OFS', 'observation_time' => "{$day}T10:30:00+02:00"], 201);

        $this->assertSame("{$day}T08:30:00Z", $payload['data']['observation_time']);

        // Negative offsets too: 03:30 at -05:00 is 08:30Z.
        $west = $this->postReport(['callsign' => 'W5WST', 'observation_time' => "{$day}T03:30:00-05:00"], 201);
        $this->assertSame("{$day}T08:30:00Z", $west['data']['observation_time']);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function invalidObservationTimes(): array
    {
        $day = gmdate('Y-m-d', time() - 86400);

        return [
            'no offset (ambiguous)' => ["{$day}T08:30:00"],
            'date only' => [$day],
            'offset with one digit' => ["{$day}T08:30:00+2:00"],
            'impossible date' => ['2026-02-30T08:30:00Z'],
            'hour 24' => ["{$day}T24:00:00Z"],
            'not a timestamp' => ['yesterday'],
        ];
    }

    #[DataProvider('invalidObservationTimes')]
    public function testObservationTimeRequiresStrictIso8601WithOffset(string $value): void
    {
        $payload = $this->postReport(['callsign' => 'W5BAD', 'observation_time' => $value], 422);

        $this->assertSame('invalid_observation_time', $payload['error']['code']);
        $this->assertSame(0, $this->countRows('satellite', "callsign='W5BAD'"));
    }

    /**
     * Not too strict: the forms common libraries emit are all accepted, as
     * long as the offset is explicit. Each is the same instant, 08:30Z.
     *
     * @return array<string, array{string}>
     */
    public static function acceptedObservationTimeForms(): array
    {
        $day = gmdate('Y-m-d', time() - 86400);

        return [
            'Z' => ["{$day}T08:30:00Z"],
            '+HH:MM' => ["{$day}T10:30:00+02:00"],
            '-HH:MM' => ["{$day}T03:30:00-05:00"],
            '+HHMM (PHP DATE_ISO8601, Python %z)' => ["{$day}T08:30:00+0000"],
            '-HHMM' => ["{$day}T03:30:00-0500"],
            '+HH' => ["{$day}T10:30:00+02"],
            'space instead of T (RFC 3339)' => ["{$day} 08:30:00Z"],
            'lowercase t and z' => ["{$day}t08:30:00z"],
            '+05:45 (Nepal)' => ["{$day}T14:15:00+05:45"],
        ];
    }

    #[DataProvider('acceptedObservationTimeForms')]
    public function testObservationTimeAcceptsCommonForms(string $value): void
    {
        $day = gmdate('Y-m-d', time() - 86400);

        $payload = $this->postReport(['callsign' => 'W5FRM', 'observation_time' => $value], 201);

        $this->assertSame("{$day}T08:30:00Z", $payload['data']['observation_time']);
    }

    public function testObservationTimeAcceptsOmittedSecondsAndFractions(): void
    {
        $day = gmdate('Y-m-d', time() - 86400);

        $noSeconds = $this->postReport(['callsign' => 'W5SEC', 'observation_time' => "{$day}T08:30Z"], 201);
        $fraction = $this->postReport(['callsign' => 'W5FRC', 'observation_time' => "{$day}T08:31:15.250Z"], 201);

        $this->assertSame("{$day}T08:30:00Z", $noSeconds['data']['observation_time']);
        $this->assertSame("{$day}T08:31:15Z", $fraction['data']['observation_time']);
    }

    public function testFutureObservationTimeIsRejected(): void
    {
        $payload = $this->postReport([
            'callsign' => 'W5FUT',
            'observation_time' => gmdate('Y-m-d\TH:i:s\Z', time() + 3600),
        ], 422);

        $this->assertSame('future_observation_time', $payload['error']['code']);
    }

    public function testConflictingObservationTimeAndReportedAtIsRejected(): void
    {
        $payload = $this->postReport([
            'callsign' => 'W5CON',
            'observation_time' => gmdate('Y-m-d\TH:i:00\Z', time() - 3600),
            'reported_at' => gmdate('Y-m-d\TH:i:00\Z', time() - 7200),
        ], 422);

        $this->assertSame('conflicting_observation_time', $payload['error']['code']);
        $this->assertSame(0, $this->countRows('satellite', "callsign='W5CON'"));
    }

    public function testAgreeingObservationTimeAndReportedAtIsAccepted(): void
    {
        // Same instant written two ways: Z and +01:00.
        $ts = intdiv(time() - 3600, 60) * 60;

        $payload = $this->postReport([
            'callsign' => 'W5AGR',
            'observation_time' => gmdate('Y-m-d\TH:i:s\Z', $ts),
            'reported_at' => gmdate('Y-m-d\TH:i:s', $ts + 3600) . '+01:00',
        ], 201);

        $this->assertSame(gmdate('Y-m-d\TH:i:s\Z', $ts), $payload['data']['observation_time']);
    }

    public function testSubmissionTimeFromClientIsIgnored(): void
    {
        $payload = $this->postReport([
            'callsign' => 'W5SPF',
            'observation_time' => gmdate('Y-m-d\TH:i:00\Z', time() - 3600),
            'submission_time' => '2001-01-01T00:00:00Z',
        ], 201);

        $this->assertNotSame('2001-01-01T00:00:00Z', $payload['data']['submission_time']);
        $this->assertSame(0, $this->countRows('satellite', "callsign='W5SPF' AND YEAR(submission_time) = 2001"));
    }

    public function testGetReportsReturnsObservationAndSubmissionTime(): void
    {
        $observed = gmdate('Y-m-d\TH:i:00\Z', time() - 3600);
        $this->postReport(['callsign' => 'W5GET', 'observation_time' => $observed], 201);

        $resp = $this->newGuestClient()->get('/api/v1/reports.php', ['query' => ['callsign' => 'W5GET']]);
        $this->assertSame(200, $resp->getStatusCode());
        $report = json_decode((string) $resp->getBody(), true)['data'][0];

        $this->assertSame($observed, $report['observation_time']);
        $this->assertSame($report['observation_time'], $report['reported_time']);
        $this->assertMatchesRegularExpression(self::ISO_UTC, $report['submission_time']);
    }

    public function testGetReportsSubmissionTimeIsNullForHistoricalRows(): void
    {
        // Rows imported before Issue #24 have no receive time.
        $this->db->query(
            "INSERT INTO satellite (name, longname, callsign, report, grid_square, observation_time, submission_time) "
            . "VALUES ('AO-91', 'AO-91', 'W5OLD', 'Heard', '', UTC_TIMESTAMP() - INTERVAL 2 HOUR, NULL)"
        );

        $resp = $this->newGuestClient()->get('/api/v1/reports.php', ['query' => ['callsign' => 'W5OLD']]);
        $report = json_decode((string) $resp->getBody(), true)['data'][0];

        $this->assertArrayHasKey('submission_time', $report);
        $this->assertNull($report['submission_time']);
        $this->assertMatchesRegularExpression(self::ISO_UTC, $report['observation_time']);
    }

    public function testCatalogAndSummaryReturnLatestObservationTimeAlias(): void
    {
        $catalog = json_decode((string) $this->newGuestClient()->get('/api/v1/catalog.php', [
            'query' => ['include_stats' => 'true'],
        ])->getBody(), true);
        foreach ($catalog['data'] as $satellite) {
            $this->assertArrayHasKey('latest_observation_time', $satellite, $satellite['name']);
            $this->assertSame($satellite['latest_observation_time'], $satellite['latest_reported_time'], $satellite['name']);
        }

        $summary = json_decode((string) $this->newGuestClient()->get('/api/v1/summary.php', [
            'query' => ['hours' => 72],
        ])->getBody(), true);
        $this->assertNotEmpty($summary['data']);
        foreach ($summary['data'] as $row) {
            $this->assertMatchesRegularExpression(self::ISO_UTC, $row['latest_observation_time']);
            $this->assertSame($row['latest_observation_time'], $row['latest_reported_time']);
        }
    }

    /**
     * #32: meta.api_version on every envelope response, equal to the
     * X-AMSAT-API-Version header. One request per endpoint, named.
     */
    public function testEveryEnvelopeResponseCarriesMetaApiVersion(): void
    {
        $client = $this->newGuestClient();
        $responses = [
            'GET /catalog.php' => $client->get('/api/v1/catalog.php'),
            'GET /reports.php' => $client->get('/api/v1/reports.php'),
            'GET /summary.php' => $client->get('/api/v1/summary.php'),
            'GET /statuses.php' => $client->get('/api/v1/statuses.php'),
            'GET /health.php' => $client->get('/api/v1/health.php'),
            'POST /reports.php' => $client->post('/api/v1/reports.php', ['json' => [
                'name' => 'AO-91', 'report' => 'Heard', 'callsign' => 'W5VER',
                'observation_time' => gmdate('Y-m-d\TH:i:00\Z', time() - 3600),
            ]]),
        ];

        foreach ($responses as $label => $resp) {
            $header = $resp->getHeaderLine('X-AMSAT-API-Version');
            $this->assertMatchesRegularExpression('/^1\.\d+$/', $header, $label);
            $body = json_decode((string) $resp->getBody(), true);
            $this->assertSame($header, $body['meta']['api_version'] ?? null, $label);
        }
    }

    /**
     * @param array<string, mixed> $fields
     * @return array<string, mixed>
     */
    private function postReport(array $fields, int $expectedStatus): array
    {
        $resp = $this->newGuestClient()->post('/api/v1/reports.php', [
            'json' => $fields + ['name' => 'AO-91', 'report' => 'Heard'],
        ]);

        $this->assertSame($expectedStatus, $resp->getStatusCode(), (string) $resp->getBody());

        return json_decode((string) $resp->getBody(), true);
    }
}
