<?php
declare(strict_types=1);

namespace AmsatStatus\Tests;

/**
 * Status grid counting under non-destructive writes (PR #30).
 *
 * Every submission is stored, so a resubmission no longer deletes the
 * earlier report. To keep the grid behaving exactly as it did under
 * delete+insert, only each callsign's latest submission per satellite per
 * 15-minute period is counted; cells are still 2-hour columns. These
 * tests pin that down:
 *
 *  - a correction by the same operator in the same period shows the
 *    corrected status, not "conflicting" (the help text's promise);
 *  - the same operator in two *different* periods of one cell still counts
 *    twice, as under 1.0 (two separate observations);
 *  - two *different* operators who disagree still show as conflicting;
 *  - one operator submitting repeatedly in one period gets one vote.
 *
 * Reports are inserted directly on yesterday's date so they always land
 * inside the 6-day window, whatever time of day the suite runs. The ISS
 * row is used because the seed data has no ISS reports.
 */
final class StatusGridTest extends TestCase
{
    private const COLOR_HEARD       = '#648fff';
    private const COLOR_NOT_HEARD   = '#dc267f';
    private const COLOR_CONFLICTING = '#fe6100';

    public function testCorrectionBySameCallsignShowsLatestStatus(): void
    {
        $this->insertReport('W5FIX', 'Heard', '10:05');
        $this->insertReport('W5FIX', 'Not Heard', '10:10');

        $body = (string) $this->newGuestClient()->get('/')->getBody();
        $row  = $this->issRow($body);

        $this->assertStringContainsString(self::COLOR_NOT_HEARD, $row);
        $this->assertStringNotContainsString(self::COLOR_CONFLICTING, $row);
        $this->assertStringNotContainsString(self::COLOR_HEARD, $row);

        // Both reports are still listed in the tooltip; the earlier one is
        // marked as replaced.
        $this->assertStringContainsString('corrected by a later report', $body);
    }

    public function testSameCallsignInDifferentPeriodsCountsTwice(): void
    {
        // 10:05 and 10:40 are different 15-minute periods (as under 1.0,
        // neither replaces the other) in the same 2-hour cell.
        $this->insertReport('W5TWO', 'Heard', '10:05');
        $this->insertReport('W5TWO', 'Not Heard', '10:40');

        $body = (string) $this->newGuestClient()->get('/')->getBody();

        $this->assertStringContainsString(self::COLOR_CONFLICTING, $this->issRow($body));
        $this->assertStringNotContainsString('corrected by a later report', $body);
    }

    public function testDisagreementBetweenDifferentCallsignsIsConflicting(): void
    {
        $this->insertReport('W5AAA', 'Heard', '10:05');
        $this->insertReport('W5BBB', 'Not Heard', '10:40');

        $row = $this->issRow((string) $this->newGuestClient()->get('/')->getBody());

        $this->assertStringContainsString(self::COLOR_CONFLICTING, $row);
    }

    public function testRepeatedSubmissionsFromOneCallsignCountOnce(): void
    {
        $this->insertReport('W5REP', 'Heard', '10:01');
        $this->insertReport('W5REP', 'Heard', '10:02');
        $this->insertReport('W5REP', 'Heard', '10:03');
        $this->insertReport('W5OTH', 'Not Heard', '10:30');

        $row = $this->issRow((string) $this->newGuestClient()->get('/')->getBody());

        // 1 Heard vote vs 1 Not Heard vote -> conflicting, not "Heard".
        $this->assertStringContainsString(self::COLOR_CONFLICTING, $row);
        $this->assertStringNotContainsString(self::COLOR_HEARD, $row);
    }

    /**
     * Issue #46: the status page's 6-day window moved from
     * "<grid column> BETWEEN 0 AND 71" (a function of every row, so a full
     * table scan) to a plain range on observation_time. Both must select
     * exactly the same rows. Rows sit on each side of every boundary: the
     * old lower edge, each midnight in the window, late today, and
     * tomorrow (direct inserts can be future-dated; the API rejects that).
     */
    public function testWindowRangeSelectsSameRowsAsGridColumnExpression(): void
    {
        $this->db->query("SET time_zone = '+00:00'");
        $this->db->query("DELETE FROM satellite");

        $offsets = [];
        for ($day = -1; $day <= 7; $day++) {
            foreach (['00:00:00', '00:00:01', '11:59:59', '23:59:59'] as $time) {
                $offsets[] = [$day, $time];
            }
        }
        $stmt = $this->db->prepare(
            'INSERT INTO satellite (name, longname, callsign, report, grid_square, observation_time) '
            . "VALUES ('ISS', 'ISS', 'W5WIN', 'Heard', '', TIMESTAMP(CURDATE() - INTERVAL ? DAY, ?))"
        );
        foreach ($offsets as [$day, $time]) {
            $stmt->bind_param('is', $day, $time);
            $stmt->execute();
        }
        $stmt->close();

        $old = 'FLOOR((23 - HOUR(observation_time)) / 2)'
            . ' + ((TO_DAYS(NOW()) - TO_DAYS(observation_time)) * 12) BETWEEN 0 AND 71';
        $new = 'observation_time >= CURDATE() - INTERVAL 5 DAY'
            . ' AND observation_time < CURDATE() + INTERVAL 1 DAY';

        $oldIds = $this->ids("SELECT id FROM satellite WHERE $old ORDER BY id");
        $newIds = $this->ids("SELECT id FROM satellite WHERE $new ORDER BY id");

        // Days 0..5 x 4 times. Guards against both sides being empty.
        $this->assertCount(24, $oldIds);
        $this->assertSame($oldIds, $newIds);
    }

    /**
     * Issue #46, end to end: a report at midnight 5 days ago is on the
     * page; one a second earlier, or dated tomorrow, is not. Checked via
     * the "Reports From" list, which lists each callsign in the window.
     */
    public function testStatusPageWindowEdges(): void
    {
        $this->db->query("SET time_zone = '+00:00'");
        foreach ([
            ['W5IN',  'CURDATE() - INTERVAL 5 DAY'],
            ['W5OUT', 'CURDATE() - INTERVAL 5 DAY - INTERVAL 1 SECOND'],
            ['W5TMR', 'CURDATE() + INTERVAL 1 DAY'],
        ] as [$callsign, $expr]) {
            $this->db->query(
                'INSERT INTO satellite (name, longname, callsign, report, grid_square, observation_time) '
                . "VALUES ('ISS', 'ISS', '$callsign', 'Heard', '', $expr)"
            );
        }

        $body = (string) $this->newGuestClient()->get('/')->getBody();

        $this->assertStringContainsString('<b>W5IN</b>', $body);
        $this->assertStringNotContainsString('W5OUT', $body);
        $this->assertStringNotContainsString('W5TMR', $body);
    }

    /** @return list<int> */
    private function ids(string $sql): array
    {
        $result = $this->db->query($sql);
        $ids = [];
        while ($row = $result->fetch_row()) {
            $ids[] = (int) $row[0];
        }
        return $ids;
    }

    private function insertReport(string $callsign, string $report, string $time): void
    {
        $observationTime = gmdate('Y-m-d', time() - 86400) . ' ' . $time . ':00';
        $stmt = $this->db->prepare(
            'INSERT INTO satellite (name, longname, callsign, report, grid_square, observation_time) '
            . "VALUES ('ISS', 'ISS', ?, ?, '', ?)"
        );
        $stmt->bind_param('sss', $callsign, $report, $observationTime);
        $stmt->execute();
        $stmt->close();
    }

    /** The ISS row of the status table, from its name cell to the row's end. */
    private function issRow(string $body): string
    {
        $start = strpos($body, 'href="https://www.amsat.org/iss-info/">ISS</a></td>');
        $this->assertNotFalse($start, 'ISS row not found in status table');
        $end = strpos($body, '</tr>', $start);
        return substr($body, $start, $end - $start);
    }
}
