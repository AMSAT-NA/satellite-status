<?php
declare(strict_types=1);

namespace AmsatStatus\Tests;

/**
 * Cursor paging for GET /api/v1/reports.php (Issue #67, API 1.5).
 */
final class ReportsPagingTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->db->query("DELETE FROM satellite WHERE name = 'ISS'");
    }

    public function testPagesReturnEveryReportExactlyOnce(): void
    {
        // Includes two reports with the same observation_time (the keyset
        // tie-break on id) and a correction (W5FIX 10:05 -> 10:10), which
        // must not appear on any page.
        $this->insertIssReports([
            ['W5AAA', 'Heard',     '09:00'],
            ['W5BBB', 'Heard',     '10:05'],
            ['W5CCC', 'Not Heard', '10:05'],
            ['W5FIX', 'Heard',     '10:05'],
            ['W5FIX', 'Not Heard', '10:10'],
            ['W5DDD', 'Heard',     '12:30'],
            ['W5EEE', 'Telemetry Only', '15:45'],
        ]);

        $all = $this->get(['name' => 'ISS', 'hours' => 72, 'limit' => 500]);
        $this->assertCount(6, $all['data']);
        $this->assertNull($all['links']['next']);
        $this->assertNull($all['meta']['next_cursor']);

        $pages = $this->collectPages(['name' => 'ISS', 'hours' => 72, 'limit' => 2]);

        $this->assertSame([2, 2, 2], array_map(static fn ($p) => count($p['data']), $pages));
        $this->assertSame(
            array_column($all['data'], 'id'),
            array_merge(...array_map(static fn ($p) => array_column($p['data'], 'id'), $pages))
        );
        $this->assertNull(end($pages)['links']['next']);
    }

    public function testNextLinkRepeatsFiltersWithFixedSince(): void
    {
        $this->insertIssReports([
            ['W5AAA', 'Heard', '09:00'],
            ['W5BBB', 'Heard', '10:00'],
        ]);

        $first = $this->get(['name' => 'ISS', 'hours' => 72, 'limit' => 1, 'status' => 'Heard']);
        $this->assertIsString($first['meta']['next_cursor']);

        parse_str((string) parse_url($first['links']['next'], PHP_URL_QUERY), $next);
        $this->assertSame('ISS', $next['name']);
        $this->assertSame('Heard', $next['status']);
        $this->assertSame('1', $next['limit']);
        $this->assertSame($first['meta']['since'], $next['since']);
        $this->assertSame($first['meta']['next_cursor'], $next['cursor']);
        $this->assertArrayNotHasKey('hours', $next);
    }

    public function testPagesAreASnapshotOfThePageOneData(): void
    {
        $this->insertIssReports([
            ['W5AAA', 'Heard', '09:00'],
            ['W5BBB', 'Heard', '10:00'],
        ]);

        $first = $this->get(['name' => 'ISS', 'hours' => 72, 'limit' => 1]);
        $this->assertSame('W5BBB', $first['data'][0]['callsign']);

        // Written after page 1: a new report, and a correction of the
        // report still to come on page 2. Neither may show up on page 2.
        $this->insertIssReports([
            ['W5NEW', 'Heard',     '20:00'],
            ['W5AAA', 'Not Heard', '09:05'],
        ]);

        $second = $this->follow($first['links']['next']);
        $this->assertCount(1, $second['data']);
        $this->assertSame('W5AAA', $second['data'][0]['callsign']);
        $this->assertSame('Heard', $second['data'][0]['report']);
        $this->assertNull($second['links']['next']);

        // A fresh request sees the new data.
        $fresh = $this->get(['name' => 'ISS', 'hours' => 72, 'limit' => 500]);
        $this->assertSame(['W5NEW', 'W5BBB', 'W5AAA'], array_column($fresh['data'], 'callsign'));
        $this->assertSame('Not Heard', $fresh['data'][2]['report']);
    }

    public function testMalformedCursorIsRejected(): void
    {
        $wrongVersion = rtrim(strtr(base64_encode('2:1:1:1'), '+/', '-_'), '=');

        foreach (['not a cursor', 'abc!', $wrongVersion] as $cursor) {
            $resp = $this->newGuestClient()->get('/api/v1/reports.php', [
                'query' => ['cursor' => $cursor],
            ]);
            $this->assertSame(400, $resp->getStatusCode(), $cursor);
            $this->assertSame('invalid_cursor', json_decode((string) $resp->getBody(), true)['error']['code']);
        }
    }

    /** @return array<string, mixed> */
    private function get(array $query): array
    {
        $resp = $this->newGuestClient()->get('/api/v1/reports.php', ['query' => $query]);
        $this->assertSame(200, $resp->getStatusCode());

        return json_decode((string) $resp->getBody(), true);
    }

    /**
     * Requests a links.next URL by path and query only, so the test doesn't
     * depend on the host name the API puts in absolute links.
     *
     * @return array<string, mixed>
     */
    private function follow(string $url): array
    {
        $resp = $this->newGuestClient()->get(
            (string) parse_url($url, PHP_URL_PATH) . '?' . (string) parse_url($url, PHP_URL_QUERY)
        );
        $this->assertSame(200, $resp->getStatusCode());

        return json_decode((string) $resp->getBody(), true);
    }

    /** @return array<int, array<string, mixed>> */
    private function collectPages(array $query): array
    {
        $pages = [$this->get($query)];
        while (($next = end($pages)['links']['next']) !== null) {
            $this->assertLessThan(20, count($pages), 'paging did not terminate');
            $pages[] = $this->follow($next);
        }

        return $pages;
    }

    /** @param array<int, array{0: string, 1: string, 2: string}> $rows */
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
}
