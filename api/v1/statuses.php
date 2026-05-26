<?php

declare(strict_types=1);

require_once __DIR__ . '/lib/bootstrap.php';
require_once __DIR__ . '/lib/validation.php';

api_require_method(['GET']);

api_json_response([
    'data' => array_map(
        static function (string $status): array {
            $labels = [
                'Heard' => 'Satellite active',
                'Telemetry Only' => 'Telemetry or beacon only',
                'Not Heard' => 'No signal',
                'Crew Active' => 'ISS crew voice active',
            ];

            return [
                'value' => $status,
                'label' => $labels[$status] ?? $status,
            ];
        },
        api_status_values()
    ),
    'meta' => [
        'count' => count(api_status_values()),
    ],
]);
