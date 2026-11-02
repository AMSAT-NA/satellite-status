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
