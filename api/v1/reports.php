<?php

declare(strict_types=1);

require_once __DIR__ . '/lib/bootstrap.php';
require_once __DIR__ . '/lib/repository.php';
require_once __DIR__ . '/lib/report_cursor.php';
require_once __DIR__ . '/lib/report_input.php';
require_once __DIR__ . '/lib/validation.php';

$method = api_require_method(['GET', 'POST']);
$repository = new ApiRepository(api_db());

if ($method === 'POST') {
    $report = api_validate_report_payload(api_request_data(), $repository);
    $writeResult = $repository->createReport($report);

    api_json_response(
        [
            'data' => [
                'id' => (int) $writeResult['id'],
                'name' => $report['name'],
                'observation_time' => $report['reported_time'],
                'submission_time' => $writeResult['submission_time'],
                'reported_time' => $report['reported_time'],
                'callsign' => $report['callsign'],
                'report' => $report['report'],
                'grid_square' => $report['grid_square'],
                'correction' => (bool) $writeResult['correction'],
            ],
            'meta' => api_meta(),
            'links' => [
                'satellite_reports' => api_self_url('reports.php?name=' . rawurlencode($report['name'])),
            ],
        ],
        201,
        ['Location' => api_self_url('reports.php?name=' . rawurlencode($report['name']))]
    );
}

$hours = api_int_param('hours', API_DEFAULT_REPORT_HOURS, API_MIN_REPORT_HOURS, API_MAX_REPORT_HOURS);
$limit = api_int_param('limit', API_DEFAULT_LIMIT, 1, API_MAX_LIMIT);
$name = api_string_param('name');
$since = api_string_param('since');

if ($name !== null) {
    $satellite = $repository->satelliteByApiName($name);

    if ($satellite === null) {
        api_error_response(404, 'satellite_not_found', 'No satellite matched the requested name.');
    }

    // The lookup also matches a display name (as catalog.php does), but
    // reports are stored under the API name, so filter on that (Issue #58).
    $name = (string) $satellite['html_element_name'];
}

if ($since !== null) {
    $sinceTime = strtotime($since);

    if ($sinceTime === false) {
        api_error_response(400, 'invalid_since', 'The since parameter must be an ISO 8601 timestamp.');
    }

    $since = gmdate('Y-m-d\TH:i:s\Z', $sinceTime);
} else {
    $since = gmdate('Y-m-d\TH:i:s\Z', time() - ($hours * 3600));
}

$gridSquare = api_string_param('grid_square');
if ($gridSquare !== null) {
    $gridSquare = api_standardized_grid_square($gridSquare);
    if (!api_grid_square_is_valid($gridSquare)) {
        api_error_response(400, 'invalid_grid_square', 'Grid square must be a valid Maidenhead locator.');
    }
}

$status = api_string_param('status');
if ($status !== null) {
    $status = api_normalize_report_status($status);
    if (!in_array($status, api_status_values(), true)) {
        api_error_response(400, 'invalid_status', 'The status parameter is not supported.');
    }
}

// Paging (Issue #67): page 1 pins a snapshot; later pages carry it, plus
// the previous page's last row, in an opaque cursor. Fetch one extra row
// to learn whether there is a next page.
$cursorParam = api_string_param('cursor');
$cursor = null;
if ($cursorParam !== null) {
    $cursor = api_decode_report_cursor($cursorParam);
    if ($cursor === null) {
        api_error_response(400, 'invalid_cursor', 'The cursor parameter is not valid. Use the links.next URL from the previous page unchanged.');
    }
}

$snapshotId = $cursor['snapshot_id'] ?? $repository->maxReportId();
$callsign = api_string_param('callsign');

$reports = $repository->reports([
    'name' => $name,
    'callsign' => $callsign,
    'grid_square' => $gridSquare,
    'status' => $status,
    'since' => $since,
    'limit' => $limit + 1,
    'snapshot_id' => $snapshotId,
    'after' => $cursor === null ? null : [
        'observation_time' => $cursor['observation_time'],
        'id' => $cursor['id'],
    ],
]);

$nextCursor = null;
$nextUrl = null;
if (count($reports) > $limit) {
    $reports = array_slice($reports, 0, $limit);
    $last = $reports[$limit - 1];
    $nextCursor = api_encode_report_cursor($snapshotId, (string) $last['observation_time'], (int) $last['id']);

    // The next link repeats every filter with since resolved to a fixed
    // time, so a default hours window doesn't slide between pages.
    $nextUrl = api_self_url('reports.php?' . http_build_query(array_filter([
        'name' => $name,
        'callsign' => $callsign,
        'grid_square' => $gridSquare,
        'status' => $status,
        'since' => $since,
        'limit' => (string) $limit,
        'cursor' => $nextCursor,
    ], static fn ($v) => $v !== null), '', '&', PHP_QUERY_RFC3986));
}

api_json_response([
    'data' => array_map(
        static function (array $report): array {
            return [
                'id' => (int) $report['id'],
                'name' => $report['name'],
                'satellite_display_name' => $report['satellite_display_name'],
                'norad_cat_id' => $report['norad_cat_id'] === null ? null : (int) $report['norad_cat_id'],
                'observation_time' => $report['observation_time'],
                'submission_time' => $report['submission_time'],
                // Deprecated alias of observation_time (Issue #45).
                'reported_time' => $report['observation_time'],
                'callsign' => $report['callsign'],
                'report' => $report['report'],
                'grid_square' => $report['grid_square'],
            ];
        },
        $reports
    ),
    'meta' => api_meta([
        'count' => count($reports),
        'limit' => $limit,
        'since' => $since,
        'hours' => $hours,
        'next_cursor' => $nextCursor,
    ]),
    'links' => [
        'next' => $nextUrl,
    ],
]);
