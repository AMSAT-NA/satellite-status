<?php

declare(strict_types=1);

require_once __DIR__ . '/lib/bootstrap.php';

/**
 * Liveness plus data freshness (Issue #47).
 *
 * A bare `SELECT 1` can't tell a working instance from one that is up but
 * serving no data: an empty database, or one where submissions silently
 * stopped. So this also reports how recent the newest report is, and says
 * "degraded" when there are none or the newest is older than
 * API_HEALTH_STALE_AFTER_SECONDS.
 *
 * - 200 + status "ok": database reachable, data fresh.
 * - 200 + status "degraded": database reachable, but no reports or stale
 *   data. Up, not down -- consumers and monitors decide what to do.
 * - 503 database_unavailable / database_unhealthy: database unreachable.
 */

api_require_method(['GET']);

$db = api_db();

// Newest report by insertion order: a primary-key lookup, so it's instant
// regardless of table size (no scan). Rows imported before Issue #24 have
// no submission_time (no honest receive time), so fall back to their
// observation_time -- right after the cutover, the newest row is still an
// imported one.
$result = $db->query(
    'SELECT DATE_FORMAT(COALESCE(submission_time, observation_time), "%Y-%m-%dT%H:%i:%sZ") AS latest_report_time,'
    . ' UNIX_TIMESTAMP(COALESCE(submission_time, observation_time)) AS latest_epoch'
    . ' FROM satellite ORDER BY id DESC LIMIT 1'
);

if (!$result) {
    api_error_response(503, 'database_unhealthy', 'The API database health check failed.');
}

$row = $result->fetch_assoc();
$now = time();
$status = 'ok';
$reason = null;
$latestReportTime = null;
$ageSeconds = null;

if ($row === null) {
    $status = 'degraded';
    $reason = 'no_reports';
} else {
    $latestReportTime = $row['latest_report_time'];
    // Clamp at 0: an observation_time slightly ahead of the server clock
    // counts as fresh, not as a negative age.
    $ageSeconds = max(0, $now - (int) $row['latest_epoch']);

    if ($ageSeconds > API_HEALTH_STALE_AFTER_SECONDS) {
        $status = 'degraded';
        $reason = 'stale_data';
    }
}

$data = [
    'status' => $status,
    'version' => API_VERSION,
    'time' => gmdate('Y-m-d\TH:i:s\Z', $now),
    'data_freshness' => [
        'latest_report_time' => $latestReportTime,
        'age_seconds' => $ageSeconds,
        'stale_after_seconds' => API_HEALTH_STALE_AFTER_SECONDS,
    ],
];

if ($reason !== null) {
    $data['reason'] = $reason;
}

api_json_response(['data' => $data, 'meta' => api_meta()]);
