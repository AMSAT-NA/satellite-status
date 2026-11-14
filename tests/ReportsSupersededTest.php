<?php
declare(strict_types=1);

namespace AmsatStatus\Tests;

/**
 * GET /api/v1/reports.php: include_superseded and superseded_by (Issue #50,
 * API 1.5).
 */
final class ReportsSupersededTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->db->query("DELETE FROM satellite WHERE name = 'ISS'");
    }

    public function testDefaultOmitsSupersededAndEveryRowHasNullSupersededBy(): void
    {
        $ids = $this->insertIssReports([
            ['W5FIX', 'Heard',     '10:05'],
            ['W5FIX', 'Not Heard', '10:10'],
            ['W5OTH', 'Heard',     '12:00'],
        ]);

        $data = $this->get(['name' => 'ISS', 'hours' => 72])['data'];

        $this->assertSame([$ids[2], $ids[1]], array_column($data, 'id'));
        foreach ($data as $row) {
            $this->assertArrayHasKey('superseded_by', $row);
            $this->assertNull($row['superseded_by']);
        }
    }

    public function testIncludeSupersededPointsEachAtTheReportThatCounts(): void
    {
        // Three submissions in one period: the first two are both
        // superseded by the third, the report that counts.
        $ids = $this->insertIssReports([
            ['W5FIX', 'Heard',          '10:01'],
            ['W5FIX', 'Not Heard',      '10:05'],
            ['W5FIX', 'Telemetry Only', '10:03'],
            ['W5TWO', 'Heard',          '10:40'],
        ]);

        $payload = $this->get(['name' => 'ISS', 'hours' => 72, 'include_superseded' => 'true']);
        $this->assertTrue($payload['meta']['include_superseded']);

        $byId = array_column($payload['data'], 'superseded_by', 'id');
        $this->assertSame([
            $ids[0] => $ids[2],
            $ids[1] => $ids[2],
            $ids[2] => null,
            $ids[3] => null,
        ], array_replace(array_fill_keys($ids, 'missing'), $byId));
    }

    public function testFiltersMatchSupersededReportsOwnValues(): void
    {
        $ids = $this->insertIssReports([
            ['W5FIX', 'Heard',     '10:05'],
            ['W5FIX', 'Not Heard', '10:10'],
        ]);

        $this->assertSame([], $this->get(['name' => 'ISS', 'hours' => 72, 'status' => 'Heard'])['data']);

        $data = $this->get([
            'name' => 'ISS', 'hours' => 72, 'status' => 'Heard', 'include_superseded' => 'true',
        ])['data'];
        $this->assertCount(1, $data);
        $this->assertSame($ids[0], $data[0]['id']);
        $this->assertSame($ids[1], $data[0]['superseded_by']);
    }

    public function testReportsWithoutCallsignAreNeverSuperseded(): void
    {
        // Legacy rows with an empty callsign are never collapsed.
        $this->insertIssReports([
            ['', 'Heard',     '10:05'],
            ['', 'Not Heard', '10:10'],
        ]);

        $data = $this->get(['name' => 'ISS', 'hours' => 72, 'include_superseded' => 'true'])['data'];
        $this->assertCount(2, $data);
        $this->assertSame([null, null], array_column($data, 'superseded_by'));
    }

    public function testPagingCarriesIncludeSuperseded(): void
    {
        $this->insertIssReports([
            ['W5FIX', 'Heard',     '10:05'],
            ['W5FIX', 'Not Heard', '10:10'],
            ['W5OTH', 'Heard',     '12:00'],
        ]);

        $all = $this->get(['name' => 'ISS', 'hours' => 72, 'include_superseded' => 'true', 'limit' => 500]);
        $this->assertCount(3, $all['data']);

        $page = $this->get(['name' => 'ISS', 'hours' => 72, 'include_superseded' => 'true', 'limit' => 1]);
        $ids = array_column($page['data'], 'id');
        while ($page['links']['next'] !== null) {
            parse_str((string) parse_url($page['links']['next'], PHP_URL_QUERY), $next);
            $this->assertSame('true', $next['include_superseded']);
            $this->assertLessThan(10, count($ids), 'paging did not terminate');
            $page = $this->get($next);
            $ids = array_merge($ids, array_column($page['data'], 'id'));
        }

        $this->assertSame(array_column($all['data'], 'id'), $ids);
    }

    /** @return array<string, mixed> */
    private function get(array $query): array
    {
        $resp = $this->newGuestClient()->get('/api/v1/reports.php', ['query' => $query]);
        $this->assertSame(200, $resp->getStatusCode());

        return json_decode((string) $resp->getBody(), true);
    }

    /**
     * @param array<int, array{0: string, 1: string, 2: string}> $rows
     * @return array<int, int> the inserted ids, in order
     */
    private function insertIssReports(array $rows): array
    {
        $yesterday = gmdate('Y-m-d', time() - 86400);
        $stmt = $this->db->prepare(
            'INSERT INTO satellite (name, longname, callsign, report, grid_square, observation_time) '
            . "VALUES ('ISS', 'ISS', ?, ?, 'FN42', ?)"
        );
        $ids = [];
        foreach ($rows as [$callsign, $report, $time]) {
            $observationTime = "{$yesterday} {$time}:00";
            $stmt->bind_param('sss', $callsign, $report, $observationTime);
            $stmt->execute();
            $ids[] = (int) $this->db->insert_id;
        }
        $stmt->close();

        return $ids;
    }
}
