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
                ? ', MAX(CONCAT(s.day, "T", LPAD(s.hour, 2, "0"), ":30:00Z")) AS latest_reported_time, COUNT(s.id) AS report_count'
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
            $where[] = 'CONCAT(s.day, "T", LPAD(s.hour, 2, "0"), ":30:00Z") >= ?';
            $params[] = $filters['since'];
            $types .= 's';
        }

        $whereSql = $where === [] ? '' : ' WHERE ' . implode(' AND ', $where);
        $limit = (int) ($filters['limit'] ?? API_DEFAULT_LIMIT);

        $sql = 'SELECT s.id, s.name, sn.name AS satellite_display_name,'
            . ' CONCAT(s.day, "T", LPAD(s.hour, 2, "0"), ":30:00Z") AS reported_time,'
            . ' s.day, s.hour, s.period, s.callsign, s.report, s.grid_square'
            . ' FROM satellite s'
            . ' LEFT JOIN satellite_name sn ON sn.html_element_name = s.name'
            . $whereSql
            . ' ORDER BY s.day DESC, s.hour DESC, s.period DESC, s.id DESC'
            . ' LIMIT ?';

        $params[] = $limit;
        $types .= 'i';

        return $this->fetchAll($sql, $types, $params);
    }

    /**
     * @param array<string, mixed> $report
     * @return array<string, mixed>
     */
    public function createReport(array $report): array
    {
        $existing = $this->fetchAll(
            'SELECT id FROM satellite'
            . ' WHERE name = ? AND longname = ? AND day = ? AND hour = ? AND period = ? AND callsign = ?',
            'sssiis',
            [
                $report['name'],
                $report['name'],
                $report['day'],
                (int) $report['hour'],
                (int) $report['period'],
                $report['callsign'],
            ]
        );

        foreach ($existing as $row) {
            $this->execute('DELETE FROM satellite WHERE id = ?', 'i', [(int) $row['id']]);
        }

        $this->execute(
            'INSERT INTO satellite (name, longname, day, hour, period, callsign, report, grid_square)'
            . ' VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
            'sssiisss',
            [
                $report['name'],
                $report['name'],
                $report['day'],
                (int) $report['hour'],
                (int) $report['period'],
                $report['callsign'],
                $report['report'],
                $report['grid_square'],
            ]
        );

        return [
            'id' => $this->db->insert_id,
            'replaced_count' => count($existing),
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
            . ' MAX(CONCAT(s.day, "T", LPAD(s.hour, 2, "0"), ":30:00Z")) AS latest_reported_time'
            . ' FROM satellite s'
            . ' LEFT JOIN satellite_name sn ON sn.html_element_name = s.name'
            . ' WHERE CONCAT(s.day, "T", LPAD(s.hour, 2, "0"), ":30:00Z") >= ?'
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
