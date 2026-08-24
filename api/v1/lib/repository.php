<?php

declare(strict_types=1);

final class ApiRepository
{
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

        $sql = 'SELECT sn.id, sn.name, sn.html_element_name, sn.website'
            . ($includeStats
                ? ', DATE_FORMAT(MAX(s.observed_at), "%Y-%m-%dT%H:%i:%sZ") AS latest_reported_time, COUNT(s.id) AS report_count'
                : '')
            . ' FROM satellite_name sn'
            . ($includeStats ? ' LEFT JOIN satellite s ON s.name = sn.html_element_name' : '')
            . $where
            . ' GROUP BY sn.id, sn.name, sn.html_element_name, sn.website'
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
        $where = [];
        $params = [];
        $types = '';

        if (($filters['name'] ?? '') !== '') {
            $where[] = 's.name = ?';
            $params[] = $filters['name'];
            $types .= 's';
        }

        if (($filters['callsign'] ?? '') !== '') {
            $where[] = 's.callsign = ?';
            $params[] = strtoupper((string) $filters['callsign']);
            $types .= 's';
        }

        if (($filters['grid_square'] ?? '') !== '') {
            $where[] = 's.grid_square = ?';
            $params[] = $filters['grid_square'];
            $types .= 's';
        }

        if (($filters['status'] ?? '') !== '') {
            $where[] = 's.report = ?';
            $params[] = $filters['status'];
            $types .= 's';
        }

        if (($filters['since'] ?? '') !== '') {
            $where[] = 's.observed_at >= ?';
            $params[] = $filters['since'];
            $types .= 's';
        }

        $whereSql = $where === [] ? '' : ' WHERE ' . implode(' AND ', $where);
        $limit = (int) ($filters['limit'] ?? API_DEFAULT_LIMIT);

        $sql = 'SELECT s.id, s.name, sn.name AS satellite_display_name,'
            . ' DATE_FORMAT(s.observed_at, "%Y-%m-%dT%H:%i:%sZ") AS reported_time,'
            . ' s.callsign, s.report, s.grid_square'
            . ' FROM satellite s'
            . ' LEFT JOIN satellite_name sn ON sn.html_element_name = s.name'
            . $whereSql
            . ' ORDER BY s.observed_at DESC, s.id DESC'
            . ' LIMIT ?';

        $params[] = $limit;
        $types .= 'i';

        return $this->fetchAll($sql, $types, $params);
    }

    /**
     * Pure INSERT -- every submission is stored, nothing is ever deleted
     * or replaced (Issue #24). submitted_at is not accepted as a
     * parameter here: it is always DB-default (CURRENT_TIMESTAMP()),
     * never client-supplied.
     *
     * @param array<string, mixed> $report
     * @return array<string, mixed>
     */
    public function createReport(array $report): array
    {
        $this->execute(
            'INSERT INTO satellite (name, longname, callsign, report, grid_square, observed_at)'
            . ' VALUES (?, ?, ?, ?, ?, ?)',
            'ssssss',
            [
                $report['name'],
                $report['name'],
                $report['callsign'],
                $report['report'],
                $report['grid_square'],
                $report['observed_at'],
            ]
        );

        return [
            'id' => $this->db->insert_id,
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function summary(int $hours): array
    {
        $since = gmdate('Y-m-d\TH:i:s\Z', time() - ($hours * 3600));

        return $this->fetchAll(
            'SELECT s.name, sn.name AS satellite_display_name, s.report, COUNT(*) AS report_count,'
            . ' DATE_FORMAT(MAX(s.observed_at), "%Y-%m-%dT%H:%i:%sZ") AS latest_reported_time'
            . ' FROM satellite s'
            . ' LEFT JOIN satellite_name sn ON sn.html_element_name = s.name'
            . ' WHERE s.observed_at >= ?'
            . ' GROUP BY s.name, sn.name, s.report'
            . ' ORDER BY s.name ASC, report_count DESC',
            's',
            [$since]
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
