<?php

declare(strict_types=1);

require_once __DIR__ . '/lib/bootstrap.php';

api_require_method(['GET']);

$specPath = __DIR__ . '/openapi.json';

if (!is_file($specPath)) {
    api_error_response(503, 'openapi_spec_missing', 'The OpenAPI document has not been generated.');
}

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
readfile($specPath);
