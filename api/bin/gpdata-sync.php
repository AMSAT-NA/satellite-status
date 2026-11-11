<?php

declare(strict_types=1);

/**
 * Scheduled GP data sync (Issue #25). Run daily by the scheduler baked into
 * the API image (api/crontab); also runnable by hand:
 *
 *   docker compose exec api php /opt/satellite-status/bin/gpdata-sync.php
 *
 * Lives outside the web root so it can't be requested over HTTP. Prints
 * one JSON line (the run result) for `docker logs`. Exit codes: 0 ok,
 * 1 error, 2 aborted (feed unusable), 3 busy (another run in progress).
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

// In the repo, the API code is a sibling directory; in the image it is in
// the web root.
$lib = is_dir(__DIR__ . '/../v1/lib') ? __DIR__ . '/../v1/lib' : '/var/www/html/api/v1/lib';

require_once $lib . '/bootstrap.php';
require_once $lib . '/gpdata_sync.php';

$result = gpdata_sync(api_db(), 'scheduled');

echo json_encode(['gpdata_sync' => $result], JSON_UNESCAPED_SLASHES), "\n";

exit(match ($result['status']) {
    'ok' => 0,
    'aborted' => 2,
    'busy' => 3,
    default => 1,
});
