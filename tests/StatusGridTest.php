<?php
declare(strict_types=1);

namespace AmsatStatus\Tests;

/**
 * Status grid counting under non-destructive writes (PR #30).
 *
 * Every submission is stored, so a resubmission no longer replaces the
 * earlier report. The grid instead counts only each callsign's most
 * recent submission per cell (satellite x 2-hour column). These tests pin
 * that down from three angles:
 *
 *  - a correction by the same operator shows the corrected status, not
 *    "conflicting" (the user-facing promise in the help text);
 *  - two *different* operators who disagree still show as conflicting
 *    (dedup is per callsign, not per cell);
 *  - one operator submitting the same status repeatedly gets one vote.
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
        $this->insertReport('W5FIX', 'Not Heard', '10:40');

        $body = (string) $this->newGuestClient()->get('/')->getBody();
        $row  = $this->issRow($body);

        $this->assertStringContainsString(self::COLOR_NOT_HEARD, $row);
        $this->assertStringNotContainsString(self::COLOR_CONFLICTING, $row);
        $this->assertStringNotContainsString(self::COLOR_HEARD, $row);

        // Both reports are still listed in the tooltip; the earlier one is
        // marked as replaced.
        $this->assertStringContainsString('replaced by a later report', $body);
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
