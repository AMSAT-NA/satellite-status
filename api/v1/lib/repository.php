<?php

declare(strict_types=1);

final class ApiRepository
{
    /**
     * Every report row plus rn = its rank within (satellite, UTC date, hour,
     * 15-minute period, callsign), newest submission first. rn = 1 is the
     * report that counts; rn > 1 was corrected by a later submission from
     * the same callsign for the same period.
     *
     * Writes are non-destructive since Issue #24 (every submission is
     * stored), so this reproduces on read exactly what 1.0's delete+insert
     * did on write: one report per callsign per satellite per 15-minute
     * period, the latest one. The status grid in frontend/v1/index.php
     * uses the same rule (PR #30). "Latest" is highest id, which is
     * monotonic and so also orders historical rows whose submission_time
     * is NULL. Rows with an empty callsign (legacy data only) are never
     * collapsed. Callers append their own WHERE on s.* and wrap this as a
     * derived table.
     */
    private const LATEST_PER_CALLSIGN_SQL =
        'SELECT s.*, ROW_NUMBER() OVER ('
        . 'PARTITION BY s.name, ' . self::PERIOD_CALLSIGN_KEY_SQL
        . ' ORDER BY s.id DESC) AS rn'
        . ' FROM satellite s';

    /**
     * The (period, callsign) part of the dedup key above, shared so the
     * two can't drift apart. Within one satellite, the number of distinct
     * values of this key is the number of rn = 1 reports, which lets
     * satellites() count latest reports with COUNT(DISTINCT ...) instead
     * of ranking the whole table (Issue #59).
     */
    private const PERIOD_CALLSIGN_KEY_SQL =
        'DATE(s.observation_time), HOUR(s.observation_time),'
        . ' FLOOR(MINUTE(s.observation_time) / 15),'
        . " COALESCE(NULLIF(s.callsign, ''), CONCAT('#', s.id))";

    /** Seconds in one dedup period (see LATEST_PER_CALLSIGN_SQL). */
    private const PERIOD_SECONDS = 900;

    /**
     * Start of the 15-minute period containing $isoTime. Ranking runs from
     * here rather than from the exact lower bound, so a report corrected by
     * a submission just before the bound is still recognised as corrected.
     */
    private static function periodStart(string $isoTime): string
    {
        $ts = (int) strtotime($isoTime);
        return gmdate('Y-m-d\TH:i:s\Z', $ts - ($ts % self::PERIOD_SECONDS));
    }

    private mysqli $db;

    public function __construct(mysqli $db)
    {
        $this->db = $db;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function satellites(?string $name = null, bool $includeStats = false): array
    {
        $params = [];
        $types = '';
        $where = '';

        if ($name !== null && $name !== '') {
            $where = ' WHERE sn.html_element_name = ? OR sn.name = ?';
            $params = [$name, $name];
            $types = 'ss';
        }

        $sql = 'SELECT sn.id, sn.name, sn.html_element_name, sn.website, sn.norad_cat_id'
            . ($includeStats
                // report_count follows the same rule as reports() and
                // summary(): corrected reports don't count (Issue #59).
                // COUNT(DISTINCT) skips the NULL key of a satellite with
                // no reports, so that still counts 0.
                ? ', DATE_FORMAT(MAX(s.observation_time), "%Y-%m-%dT%H:%i:%sZ") AS latest_observation_time,'
                    . ' COUNT(DISTINCT ' . self::PERIOD_CALLSIGN_KEY_SQL . ') AS report_count'
                : '')
            . ' FROM satellite_name sn'
            . ($includeStats ? ' LEFT JOIN satellite s ON s.name = sn.html_element_name' : '')
            . $where
            . ' GROUP BY sn.id, sn.name, sn.html_element_name, sn.website, sn.norad_cat_id'
            . ' ORDER BY sn.name ASC';

        return $this->fetchAll($sql, $types, $params);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function satelliteByApiName(string $name): ?array
    {
        $rows = $this->satellites($name, false);

        return $rows[0] ?? null;
    }

    /**
     * @param array<string, mixed> $filters
     * @return array<int, array<string, mixed>>
     */
    public function reports(array $filters): array
    {
        // Corrected reports (rn > 1) are excluded, matching 1.0, where a
        // resubmission deleted them (see LATEST_PER_CALLSIGN_SQL). Filters
        // on the partition keys (name, callsign) and the time lower bound
        // go inside the ranking; status and grid_square go outside it, so
        // e.g. status=Heard can't return a Heard report that was later
        // corrected to something else.
        $inner = [];
        $outer = ['l.rn = 1'];
        $params = [];
        $types = '';

        if (($filters['name'] ?? '') !== '') {
            $inner[] = 's.name = ?';
            $params[] = $filters['name'];
            $types .= 's';
        }

        if (($filters['callsign'] ?? '') !== '') {
            $inner[] = 's.callsign = ?';
            $params[] = strtoupper((string) $filters['callsign']);
            $types .= 's';
        }

        if (($filters['since'] ?? '') !== '') {
            $inner[] = 's.observation_time >= ?';
            $params[] = self::periodStart((string) $filters['since']);
            $types .= 's';
        }

        // Paging (Issue #67). snapshot_id pins the result set to the rows
        // that existed when page 1 was served: writes are append-only, so
        // ranking only ids <= snapshot_id gives every page the same
        // corrected view, however many reports arrive in between.
        if (isset($filters['snapshot_id'])) {
            $inner[] = 's.id <= ?';
            $params[] = (int) $filters['snapshot_id'];
            $types .= 'i';
        }

        // after = the last row of the previous page. Rows in later
        // 15-minute periods can't change the ranking of earlier ones (the
        // period is part of the partition key), so the ranking can stop at
        // the end of that row's period instead of covering the whole window.
        $after = $filters['after'] ?? null;
        if (is_array($after)) {
            $inner[] = 's.observation_time < ?';
            $params[] = gmdate('Y-m-d\TH:i:s\Z', strtotime(self::periodStart((string) $after['observation_time'])) + self::PERIOD_SECONDS);
            $types .= 's';
        }

        if (($filters['grid_square'] ?? '') !== '') {
            $outer[] = 'l.grid_square = ?';
            $params[] = $filters['grid_square'];
            $types .= 's';
        }

        if (($filters['status'] ?? '') !== '') {
            $outer[] = 'l.report = ?';
            $params[] = $filters['status'];
            $types .= 's';
        }

        if (($filters['since'] ?? '') !== '') {
            $outer[] = 'l.observation_time >= ?';
            $params[] = $filters['since'];
            $types .= 's';
        }

        if (is_array($after)) {
            // Keyset on the sort order below: strictly after the previous
            // page's last (observation_time, id).
            $outer[] = '(l.observation_time < ? OR (l.observation_time = ? AND l.id < ?))';
            $params[] = $after['observation_time'];
            $params[] = $after['observation_time'];
            $params[] = (int) $after['id'];
            $types .= 'ssi';
        }

        $innerSql = $inner === [] ? '' : ' WHERE ' . implode(' AND ', $inner);
        $limit = (int) ($filters['limit'] ?? API_DEFAULT_LIMIT);

        $sql = 'SELECT l.id, l.name, sn.name AS satellite_display_name, sn.norad_cat_id,'
            . ' DATE_FORMAT(l.observation_time, "%Y-%m-%dT%H:%i:%sZ") AS observation_time,'
            . ' DATE_FORMAT(l.submission_time, "%Y-%m-%dT%H:%i:%sZ") AS submission_time,'
            . ' l.callsign, l.report, l.grid_square'
            . ' FROM (' . self::LATEST_PER_CALLSIGN_SQL . $innerSql . ') l'
            . ' LEFT JOIN satellite_name sn ON sn.html_element_name = l.name'
            . ' WHERE ' . implode(' AND ', $outer)
            . ' ORDER BY l.observation_time DESC, l.id DESC'
            . ' LIMIT ?';

        $params[] = $limit;
        $types .= 'i';

        return $this->fetchAll($sql, $types, $params);
    }

    /**
     * Highest report id stored now: page 1's snapshot for paging (see
     * reports()). 0 when there are no reports.
     */
    public function maxReportId(): int
    {
        $rows = $this->fetchAll('SELECT COALESCE(MAX(id), 0) AS max_id FROM satellite');

        return (int) ($rows[0]['max_id'] ?? 0);
    }

    /**
     * Pure INSERT -- every submission is stored, nothing is ever deleted
     * or replaced (Issue #24). submission_time is not accepted as a
     * parameter here: it is always DB-default (CURRENT_TIMESTAMP()),
     * never client-supplied.
     *
     * @param array<string, mixed> $report
     * @return array<string, mixed>
     */
    public function createReport(array $report): array
    {
        // correction: does this submission correct an earlier report? True
        // when the same callsign already has a report for this satellite in
        // the same 15-minute UTC period -- the report this one now replaces
        // in the grid and the API (LATEST_PER_CALLSIGN_SQL). Nothing is
        // deleted. Equivalent to 1.0's replaced_count > 0. Uses the
        // (name, callsign, observation_time) index.
        $ts = (int) strtotime($report['observation_time'] . ' UTC');
        $periodStart = $ts - ($ts % self::PERIOD_SECONDS);
        $existing = $this->fetchAll(
            'SELECT 1 FROM satellite'
            . ' WHERE name = ? AND callsign = ? AND observation_time >= ? AND observation_time < ?'
            . ' LIMIT 1',
            'ssss',
            [
                $report['name'],
                $report['callsign'],
                gmdate('Y-m-d H:i:s', $periodStart),
                gmdate('Y-m-d H:i:s', $periodStart + self::PERIOD_SECONDS),
            ]
        );

        $this->execute(
            'INSERT INTO satellite (name, longname, callsign, report, grid_square, observation_time)'
            . ' VALUES (?, ?, ?, ?, ?, ?)',
            'ssssss',
            [
                $report['name'],
                $report['name'],
                $report['callsign'],
                $report['report'],
                $report['grid_square'],
                $report['observation_time'],
            ]
        );

        $id = $this->db->insert_id;

        // Read submission_time back rather than computing it here: the DB
        // default sets it, so this is the value actually stored (Issue #45).
        $stored = $this->fetchAll(
            'SELECT DATE_FORMAT(submission_time, "%Y-%m-%dT%H:%i:%sZ") AS submission_time FROM satellite WHERE id = ?',
            'i',
            [$id]
        );

        return [
            'id' => $id,
            'correction' => $existing !== [],
            'submission_time' => $stored[0]['submission_time'] ?? null,
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function summary(int $hours): array
    {
        // Counts follow the same rule as reports() and the status grid
        // (PR #30): corrected reports don't count -- one report per callsign
        // per satellite per 15-minute period, the latest, exactly as 1.0's
        // delete+insert left it. See LATEST_PER_CALLSIGN_SQL.
        $since = gmdate('Y-m-d\TH:i:s\Z', time() - ($hours * 3600));
        $blockStart = self::periodStart($since);

        return $this->fetchAll(
            'SELECT l.name, sn.name AS satellite_display_name, l.report, COUNT(*) AS report_count,'
            . ' DATE_FORMAT(MAX(l.observation_time), "%Y-%m-%dT%H:%i:%sZ") AS latest_observation_time'
            . ' FROM (' . self::LATEST_PER_CALLSIGN_SQL . ' WHERE s.observation_time >= ?) l'
            . ' LEFT JOIN satellite_name sn ON sn.html_element_name = l.name'
            . ' WHERE l.rn = 1 AND l.observation_time >= ?'
            . ' GROUP BY l.name, sn.name, l.report'
            . ' ORDER BY l.name ASC, report_count DESC',
            'ss',
            [$blockStart, $since]
        );
    }

    /**
     * @param array<int, mixed> $params
     * @return array<int, array<string, mixed>>
     */
    private function fetchAll(string $sql, string $types = '', array $params = []): array
    {
        $stmt = $this->prepare($sql);

        if ($types !== '') {
            $this->bindParams($stmt, $types, $params);
        }

        if (!$stmt->execute()) {
            api_error_response(500, 'query_failed', 'The API could not complete the database query.');
        }

        $result = $stmt->get_result();
        $rows = [];

        while ($row = $result->fetch_assoc()) {
            $rows[] = $row;
        }

        $stmt->close();

        return $rows;
    }

    /**
     * @param array<int, mixed> $params
     */
    private function execute(string $sql, string $types, array $params): void
    {
        $stmt = $this->prepare($sql);
        $this->bindParams($stmt, $types, $params);

        if (!$stmt->execute()) {
            api_error_response(500, 'write_failed', 'The API could not write the requested data.');
        }

        $stmt->close();
    }

    private function prepare(string $sql): mysqli_stmt
    {
        $stmt = $this->db->prepare($sql);

        if (!$stmt) {
            api_error_response(500, 'query_prepare_failed', 'The API could not prepare the database query.');
        }

        return $stmt;
    }

    /**
     * @param array<int, mixed> $params
     */
    private function bindParams(mysqli_stmt $stmt, string $types, array $params): void
    {
        $refs = [$types];

        foreach ($params as $key => $value) {
            $refs[] = &$params[$key];
        }

        call_user_func_array([$stmt, 'bind_param'], $refs);
    }
}
