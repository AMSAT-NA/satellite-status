<?php

declare(strict_types=1);

require_once __DIR__ . '/lib/bootstrap.php';
require_once __DIR__ . '/lib/repository.php';

api_require_method(['GET']);

$hours = api_int_param('hours', 24, API_MIN_REPORT_HOURS, API_MAX_REPORT_HOURS);
$repository = new ApiRepository(api_db());
$summary = $repository->summary($hours);

api_json_response([
    'data' => array_map(
        static function (array $row): array {
            return [
                'name' => $row['name'],
                'satellite_display_name' => $row['satellite_display_name'],
                'report' => $row['report'],
                'report_count' => (int) $row['report_count'],
                'latest_reported_time' => $row['latest_reported_time'],
            ];
        },
        $summary
    ),
    'meta' => [
        'hours' => $hours,
        'count' => count($summary),
    ],
]);
