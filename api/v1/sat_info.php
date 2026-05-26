<?php

declare(strict_types=1);

require_once __DIR__ . '/lib/bootstrap.php';
require_once __DIR__ . '/lib/repository.php';

api_require_method(['GET']);

$name = api_string_param('name');

// Preserve the historical contract: missing name returns an empty JSON array.
if ($name === null) {
    api_legacy_json_response([]);
}

$hours = API_DEFAULT_REPORT_HOURS;
if (array_key_exists('hours', $_GET) && is_numeric($_GET['hours'])) {
    $hours = max(API_MIN_REPORT_HOURS, min(API_LEGACY_MAX_REPORT_HOURS, (int) $_GET['hours']));
}

$repository = new ApiRepository(api_db());
$reports = $repository->reports([
    'name' => $name,
    'since' => gmdate('Y-m-d\TH:i:s\Z', time() - ($hours * 3600)),
    'limit' => API_DEFAULT_LIMIT,
]);

api_legacy_json_response(array_map(
    static function (array $report): array {
        return [
            'name' => $report['name'],
            'reported_time' => $report['reported_time'],
            'callsign' => $report['callsign'],
            'report' => $report['report'],
            'grid_square' => $report['grid_square'],
        ];
    },
    $reports
));
