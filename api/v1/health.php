<?php

declare(strict_types=1);

require_once __DIR__ . '/lib/bootstrap.php';

api_require_method(['GET']);

$db = api_db();
$result = $db->query('SELECT 1 AS ok');

if (!$result) {
    api_error_response(503, 'database_unhealthy', 'The API database health check failed.');
}

api_json_response([
    'data' => [
        'status' => 'ok',
        'version' => API_VERSION,
        'time' => gmdate('Y-m-d\TH:i:s\Z'),
    ],
]);
