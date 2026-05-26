<?php

declare(strict_types=1);

require_once __DIR__ . '/lib/bootstrap.php';
require_once __DIR__ . '/lib/repository.php';
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
                'reported_time' => $report['reported_time'],
                'callsign' => $report['callsign'],
                'report' => $report['report'],
                'grid_square' => $report['grid_square'],
                'replaced_count' => (int) $writeResult['replaced_count'],
            ],
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

if ($name !== null && $repository->satelliteByApiName($name) === null) {
    api_error_response(404, 'satellite_not_found', 'No satellite matched the requested name.');
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

$reports = $repository->reports([
    'name' => $name,
    'callsign' => api_string_param('callsign'),
    'grid_square' => $gridSquare,
    'status' => $status,
    'since' => $since,
    'limit' => $limit,
]);

api_json_response([
    'data' => array_map(
        static function (array $report): array {
            return [
                'id' => (int) $report['id'],
                'name' => $report['name'],
                'satellite_display_name' => $report['satellite_display_name'],
                'reported_time' => $report['reported_time'],
                'callsign' => $report['callsign'],
                'report' => $report['report'],
                'grid_square' => $report['grid_square'],
                'period' => (int) $report['period'],
            ];
        },
        $reports
    ),
    'meta' => [
        'count' => count($reports),
        'limit' => $limit,
        'since' => $since,
        'hours' => $hours,
    ],
]);
