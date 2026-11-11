<?php

declare(strict_types=1);

require_once __DIR__ . '/lib/bootstrap.php';
require_once __DIR__ . '/lib/gpdata_sync.php';

/**
 * POST /api/v1/gpdata-sync -- run a GP data sync now (Issue #25).
 *
 * Admin-only: requires "Authorization: Bearer <GPDATA_SYNC_TOKEN>". Used by
 * the admin dashboard's "Sync now" button (e.g. to pick up new NORAD
 * catalog numbers during a launch); the daily run uses the same code via
 * api/bin/gpdata-sync.php. Rules: see lib/gpdata_sync.php.
 *
 * Named for GP data in general, not just NORAD IDs: frontend v2 may sync
 * more from the same feed. A public read-only GET (last-run status) may be
 * added on this same path later.
 *
 * Responses:
 * - 200: a run was recorded. data.status is "ok" (synced) or "aborted"
 *   (the feed couldn't be used; nothing written; data.reason says why).
 * - 401 unauthorized: missing or wrong token.
 * - 409 sync_in_progress: another run (scheduled or manual) holds the lock.
 * - 500 sync_failed: unexpected database error; nothing written.
 * - 503 sync_not_configured: GPDATA_SYNC_TOKEN isn't set on the server,
 *   so the endpoint is disabled.
 */

api_require_method(['POST']);

$expected = trim((string) getenv('GPDATA_SYNC_TOKEN'));

if ($expected === '') {
    api_error_response(503, 'sync_not_configured', 'GP data sync is not enabled on this server.');
}

if (!hash_equals($expected, gpdata_bearer_token())) {
    api_json_response(
        ['error' => ['code' => 'unauthorized', 'message' => 'A valid sync token is required.', 'status' => 401]],
        401,
        ['WWW-Authenticate' => 'Bearer realm="gpdata-sync"']
    );
}

$result = gpdata_sync(api_db(), 'manual');

if ($result['status'] === 'busy') {
    api_error_response(409, 'sync_in_progress', $result['message']);
}

if ($result['status'] === 'error') {
    api_error_response(500, 'sync_failed', 'The sync failed with a database error; nothing was written.', [
        'run_id' => $result['run_id'],
    ]);
}

api_json_response(['data' => $result, 'meta' => api_meta()]);

/**
 * The bearer token from the Authorization header. Apache's mod_php exposes
 * it as HTTP_AUTHORIZATION on most setups; getallheaders() covers the rest.
 */
function gpdata_bearer_token(): string
{
    $header = (string) ($_SERVER['HTTP_AUTHORIZATION'] ?? '');

    if ($header === '' && function_exists('getallheaders')) {
        foreach (getallheaders() as $name => $value) {
            if (strcasecmp((string) $name, 'Authorization') === 0) {
                $header = (string) $value;
                break;
            }
        }
    }

    return preg_match('/^Bearer\s+(\S+)$/i', trim($header), $m) === 1 ? $m[1] : '';
}
