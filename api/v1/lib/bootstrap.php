<?php

declare(strict_types=1);

date_default_timezone_set('UTC');

require_once __DIR__ . '/../../../config.php';

const API_VERSION = '1.0.0';
const API_DEFAULT_REPORT_HOURS = 72;
const API_MIN_REPORT_HOURS = 1;
const API_MAX_REPORT_HOURS = 720;
const API_LEGACY_MAX_REPORT_HOURS = 96;
const API_DEFAULT_LIMIT = 100;
const API_MAX_LIMIT = 500;

function api_db(): mysqli
{
    global $mysqlHost, $mysqlUsername, $mysqlPassword, $mysqlDatabase;

    mysqli_report(MYSQLI_REPORT_OFF);
    $db = new mysqli($mysqlHost, $mysqlUsername, $mysqlPassword, $mysqlDatabase);

    if ($db->connect_errno) {
        api_error_response(503, 'database_unavailable', 'The status database is unavailable.');
    }

    $db->set_charset('utf8mb4');

    return $db;
}

/**
 * @param array<string, string> $headers
 */
function api_json_response($payload, int $status = 200, array $headers = []): void
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Headers: Content-Type');
    header('X-AMSAT-API-Version: ' . API_VERSION);

    foreach ($headers as $name => $value) {
        header($name . ': ' . $value);
    }

    echo json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    exit;
}

function api_legacy_json_response($payload): void
{
    header('Content-Type: application/json');
    echo json_encode($payload);
    exit;
}

/**
 * @param array<string, mixed> $details
 */
function api_error_response(int $status, string $code, string $message, array $details = []): void
{
    $error = [
        'error' => [
            'code' => $code,
            'message' => $message,
            'status' => $status,
        ],
    ];

    if ($details !== []) {
        $error['error']['details'] = $details;
    }

    api_json_response($error, $status);
}

/**
 * @param array<int, string> $methods
 */
function api_require_method(array $methods): string
{
    $method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');

    if ($method === 'OPTIONS') {
        http_response_code(204);
        header('Allow: ' . implode(', ', $methods));
        header('Access-Control-Allow-Origin: *');
        header('Access-Control-Allow-Methods: ' . implode(', ', $methods));
        header('Access-Control-Allow-Headers: Content-Type');
        exit;
    }

    if (!in_array($method, $methods, true)) {
        header('Allow: ' . implode(', ', $methods));
        api_error_response(
            405,
            'method_not_allowed',
            'This endpoint supports ' . implode(', ', $methods) . ' requests.'
        );
    }

    return $method;
}

function api_string_param(string $name, ?string $default = null): ?string
{
    if (!array_key_exists($name, $_GET)) {
        return $default;
    }

    $value = trim((string) $_GET[$name]);

    return $value === '' ? $default : $value;
}

function api_int_param(string $name, int $default, int $min, int $max): int
{
    if (!array_key_exists($name, $_GET) || $_GET[$name] === '') {
        return $default;
    }

    if (!is_numeric($_GET[$name])) {
        api_error_response(
            400,
            'invalid_parameter',
            sprintf('The "%s" parameter must be an integer.', $name),
            [$name => $_GET[$name]]
        );
    }

    return max($min, min($max, (int) $_GET[$name]));
}

function api_bool_param(string $name, bool $default = false): bool
{
    if (!array_key_exists($name, $_GET)) {
        return $default;
    }

    $value = strtolower(trim((string) $_GET[$name]));

    return in_array($value, ['1', 'true', 'yes', 'on'], true);
}

/**
 * @return array<string, mixed>
 */
function api_request_data(): array
{
    $contentType = strtolower($_SERVER['CONTENT_TYPE'] ?? '');

    if (strpos($contentType, 'application/json') !== false) {
        $body = file_get_contents('php://input');
        $data = json_decode($body === false ? '' : $body, true);

        if (!is_array($data)) {
            api_error_response(400, 'invalid_json', 'The request body must be valid JSON.');
        }

        return $data;
    }

    return $_POST;
}

function api_base_url(): string
{
    global $siteUrl;

    return rtrim((string) $siteUrl, '/') . '/api/v1';
}

function api_self_url(string $path): string
{
    return api_base_url() . '/' . ltrim($path, '/');
}
