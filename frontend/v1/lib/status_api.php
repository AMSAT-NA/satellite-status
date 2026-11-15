<?php
declare(strict_types=1);

/**
 * Read helpers for frontend-v1's public pages (Issue #67). index.php and
 * submit.php get their data from the API, server to server via
 * $apiInternalUrl (see config.php), instead of querying MariaDB directly.
 * Only the admin pages still use the database; they're replaced in
 * frontend-v2 (docs/agents/api-v1-v2-dividing-line.md).
 */

/**
 * GET an API endpoint.
 *
 * @param array<string, scalar> $query
 * @return array{status: int, body: ?array} status 0 when the API couldn't
 *         be reached at all
 */
function status_api_get(string $apiBase, string $path, array $query = []): array
{
    $url = rtrim($apiBase, '/') . '/' . ltrim($path, '/');
    if ($query !== []) {
        $url .= '?' . http_build_query($query, '', '&', PHP_QUERY_RFC3986);
    }

    $context = stream_context_create([
        'http' => [
            'method' => 'GET',
            'header' => "Accept: application/json\r\nUser-Agent: amsat-status-frontend-v1\r\n",
            'timeout' => 10,
            'ignore_errors' => true, // so 4xx/5xx bodies are still readable
        ],
    ]);

    $body = @file_get_contents($url, false, $context);

    $status = 0;
    foreach (http_get_last_response_headers() ?? [] as $header) {
        if (preg_match('#^HTTP/\S+\s+(\d{3})#', $header, $matches)) {
            $status = (int) $matches[1];
        }
    }

    $decoded = $body === false ? null : json_decode($body, true);

    return ['status' => $status, 'body' => is_array($decoded) ? $decoded : null];
}

/**
 * Every report observed at or after $since, superseded ones included (each
 * with superseded_by set), newest first. Follows the cursor until the last
 * page. The next page is requested from $apiBase with meta.next_cursor
 * rather than from links.next, because links.next uses the public site
 * URL, which isn't reliably reachable from inside the container.
 *
 * @return list<array<string, mixed>>|null null if any page failed
 */
function status_api_reports_since(string $apiBase, string $since): ?array
{
    $query = ['since' => $since, 'limit' => 500, 'include_superseded' => 'true'];
    $reports = [];

    // 50 pages x 500 = 25,000 reports: far above any real 6-day window,
    // and a guard against looping forever.
    for ($page = 0; $page < 50; $page++) {
        $response = status_api_get($apiBase, 'reports.php', $query);
        if ($response['status'] !== 200 || !isset($response['body']['data'])) {
            return null;
        }

        array_push($reports, ...$response['body']['data']);

        $cursor = $response['body']['meta']['next_cursor'] ?? null;
        if ($cursor === null) {
            return $reports;
        }
        $query['cursor'] = $cursor;
    }

    return null;
}

/**
 * The satellite catalog, ordered by display name (as catalog.php returns
 * it).
 *
 * @return list<array<string, mixed>>|null
 */
function status_api_catalog(string $apiBase): ?array
{
    $response = status_api_get($apiBase, 'catalog.php');

    if ($response['status'] !== 200 || !isset($response['body']['data'])) {
        return null;
    }

    return $response['body']['data'];
}

/**
 * Compares two names the way the satellite tables' utf8mb3_general_ci
 * collation does for ASCII: case-insensitively, by upper-cased byte. Used
 * so the grid's row order is the same as when it came from ORDER BY name.
 * (strcasecmp lower-cases instead, which moves "_" relative to letters.)
 */
function status_name_compare(string $a, string $b): int
{
    return strcmp(strtoupper($a), strtoupper($b));
}
