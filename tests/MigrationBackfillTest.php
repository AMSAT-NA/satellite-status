<?php
declare(strict_types=1);

namespace AmsatStatus\Tests;

/**
 * Exercises the actual backfill UPDATE from
 * db/migrations/2026_08_08_add_observed_at_submitted_at.sql -- not a
 * duplicated copy of the formula, but the literal statement extracted
 * from the migration file, so these tests can't silently drift from what
 * the migration script actually runs against dallas190.
 *
 * The migration file's ALTER TABLE statements aren't exercised here:
 * they assume the pre-migration schema (no observed_at/submitted_at
 * columns yet), which doesn't match this test DB's schema.sql-based
 * shape (those columns already exist). Only the backfill UPDATE itself
 * is schema-shape-independent -- it just reads day/hour/period and
 * writes observed_at -- so it's the only piece tested directly here,
 * scoped to a single seeded row via an appended WHERE clause so it
 * doesn't recompute observed_at for the rest of the fixture data.
 */
final class MigrationBackfillTest extends TestCase
{
    private function backfillUpdateSql(): string
    {
        $migrationPath = __DIR__ . '/../db/migrations/2026_08_08_add_observed_at_submitted_at.sql';
        $migrationSql = file_get_contents($migrationPath);

        if ($migrationSql === false) {
            throw new \RuntimeException("Could not read {$migrationPath}");
        }

        if (!preg_match('/UPDATE satellite\s+SET observed_at = CASE.*?END\s*;/s', $migrationSql, $matches)) {
            throw new \RuntimeException('Could not find the backfill UPDATE statement in the migration script.');
        }

        return $matches[0];
    }

    /**
     * Inserts a row directly (bypassing the API, like a pre-migration
     * historical row), then runs the migration's backfill UPDATE scoped
     * to just that row, and returns the resulting observed_at.
     */
    private function backfillObservedAt(string $day, int $hour, ?int $period): string
    {
        $stmt = $this->db->prepare(
            'INSERT INTO satellite (name, longname, day, hour, period, callsign, report, grid_square, observed_at) '
            . 'VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $name = 'AO-91';
        $callsign = 'W5MIG';
        $report = 'Heard';
        $grid = 'EM48';
        // Placeholder -- observed_at is NOT NULL, overwritten by the
        // backfill UPDATE below. Deliberately not a value the backfill
        // could plausibly produce, so the assertion can't pass by
        // accident if the UPDATE silently no-ops.
        $placeholder = '2099-01-01 00:00:00';
        $stmt->bind_param(
            'sssiissss',
            $name,
            $name,
            $day,
            $hour,
            $period,
            $callsign,
            $report,
            $grid,
            $placeholder
        );
        $stmt->execute();
        $id = $this->db->insert_id;
        $stmt->close();

        $updateSql = rtrim(rtrim($this->backfillUpdateSql()), ';') . " WHERE id = {$id}";
        $this->assertTrue((bool) $this->db->query($updateSql), $this->db->error);

        $row = $this->db->query("SELECT observed_at FROM satellite WHERE id = {$id}")->fetch_assoc();

        return $row['observed_at'];
    }

    public function testBackfillAppliesQuarterHourOffsetForInRangePeriod(): void
    {
        // period 2 -> :30 (2 * 15 minutes)
        $observedAt = $this->backfillObservedAt('2026-01-15', 14, 2);

        $this->assertSame('2026-01-15 14:30:00', $observedAt);
    }

    public function testBackfillLandsOnTopOfHourForPeriodMinusOneSentinel(): void
    {
        // Issue #24 follow-up: dallas190 production has 53 rows with
        // period = -1, a legacy "unspecified" sentinel -- day/hour are
        // real for these rows, so the backfill should preserve them
        // exactly and land on the top of the hour, not fabricate a
        // quarter-hour that was never captured.
        $observedAt = $this->backfillObservedAt('2026-01-15', 14, -1);

        $this->assertSame('2026-01-15 14:00:00', $observedAt);
    }

    public function testBackfillLandsOnTopOfHourForNullPeriod(): void
    {
        $observedAt = $this->backfillObservedAt('2026-01-15', 14, null);

        $this->assertSame('2026-01-15 14:00:00', $observedAt);
    }
}
