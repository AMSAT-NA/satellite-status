<?php

declare(strict_types=1);

require_once __DIR__ . '/lib/bootstrap.php';
require_once __DIR__ . '/lib/repository.php';

api_require_method(['GET']);

$repository = new ApiRepository(api_db());
$name = api_string_param('name');
$includeStats = api_bool_param('include_stats', false);
$satellites = $repository->satellites($name, $includeStats);

if ($name !== null && $satellites === []) {
    api_error_response(404, 'satellite_not_found', 'No satellite matched the requested name.');
}

api_json_response([
    'data' => array_map(
        static function (array $satellite): array {
            $item = [
                'id' => (int) $satellite['id'],
                'name' => $satellite['html_element_name'],
                'display_name' => $satellite['name'],
                'website' => $satellite['website'],
                'links' => [
                    'self' => api_self_url('catalog.php?name=' . rawurlencode($satellite['html_element_name'])),
                    'reports' => api_self_url('reports.php?name=' . rawurlencode($satellite['html_element_name'])),
                ],
            ];

            if (array_key_exists('report_count', $satellite)) {
                $item['report_count'] = (int) $satellite['report_count'];
                $item['latest_reported_time'] = $satellite['latest_reported_time'];
            }

            return $item;
        },
        $satellites
    ),
    'meta' => [
        'count' => count($satellites),
    ],
]);
