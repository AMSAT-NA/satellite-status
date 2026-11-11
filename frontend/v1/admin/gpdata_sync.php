<?php
// Admin "Sync now" (Issue #25): runs a GP data sync by calling
// POST /api/v1/gpdata-sync.php over the internal network with the shared
// token, then shows the result on the dashboard. The sync itself lives in
// the API; this page never touches norad_cat_id directly.
session_start();

include("../config.php");

if (empty($_SESSION["auth_status"])) {
    header('Location: ' . $siteUrl . '/admin/index.php');
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST'
    || empty($_SESSION['csrf_token'])
    || !hash_equals($_SESSION['csrf_token'], (string) ($_POST['csrf_token'] ?? ''))
) {
    header('Location: ' . $siteUrl . '/admin/dashboard.php');
    exit;
}

$_SESSION['gpdata_sync_flash'] = runGpdataSync($apiInternalUrl, (string) getenv('GPDATA_SYNC_TOKEN'));

header('Location: ' . $siteUrl . '/admin/dashboard.php#gpdata-sync-result');
exit;

/**
 * @return array{run: array<string, mixed>}|array{error: string}
 */
function runGpdataSync($apiInternalUrl, $token) {
    if (trim($token) === '') {
        return ['error' => 'GPDATA_SYNC_TOKEN is not set for the frontend container.'];
    }

    $context = stream_context_create([
        'http' => [
            'method' => 'POST',
            'header' => "Authorization: Bearer " . trim($token) . "\r\nContent-Length: 0\r\n",
            'timeout' => 90,
            'ignore_errors' => true,
        ],
    ]);

    $body = @file_get_contents(rtrim($apiInternalUrl, '/') . '/gpdata-sync.php', false, $context);
    $status = 0;
    // http_get_last_response_headers() (PHP 8.4+): $http_response_header is
    // deprecated in PHP 8.5, and its notice would break the redirect below.
    $statusLine = (http_get_last_response_headers() ?? [])[0] ?? '';
    if (preg_match('#^HTTP/\S+\s+(\d{3})#', $statusLine, $m)) {
        $status = (int) $m[1];
    }
    $payload = $body === false ? null : json_decode($body, true);

    if ($status === 200 && isset($payload['data']['status'])) {
        return ['run' => $payload['data']];
    }

    $message = $payload['error']['message'] ?? 'The API could not be reached.';
    return ['error' => ($status !== 0 ? "HTTP {$status}: " : '') . $message];
}
