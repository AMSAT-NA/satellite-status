<?php

declare(strict_types=1);

/**
 * /api front door: a small, machine-readable index of API versions.
 *
 * Deliberately NOT an alias for "the latest version". Clients hardcode a
 * versioned base URL (/api/v1/); an unversioned URL that silently starts
 * serving v2 when v2 ships is exactly the breakage versioning exists to
 * prevent. This document only points at versions -- it never serves
 * data endpoints, and nothing here redirects on its own.
 *
 * When a new version ships, add an entry to API_VERSIONS and move the
 * previous one's status to 'deprecated' (then 'retired' once it's gone).
 * See issue #38.
 */

// Reuse v1's bootstrap for config (SITE_URL), the JSON envelope, CORS,
// method handling, and the deploy-identity headers. This file ships in
// the v1 image, so v1's helpers are always present alongside it.
require_once __DIR__ . '/v1/lib/bootstrap.php';

api_require_method(['GET']);

/**
 * @var array<int, array{version: string, status: string, path: string}>
 */
const API_VERSIONS = [
    ['version' => 'v1', 'status' => 'current', 'path' => '/api/v1'],
];

$versions = array_map(
    static function (array $v) use ($siteUrl): array {
        $base = $siteUrl . $v['path'];

        return [
            'version' => $v['version'],
            'status' => $v['status'],
            'url' => $base . '/',
            'docs' => $base . '/docs.php',
            'openapi' => $base . '/openapi.php',
        ];
    },
    API_VERSIONS
);

api_json_response([
    'data' => [
        'name' => 'AMSAT Satellite Status API',
        'versions' => $versions,
        'links' => [
            'self' => $siteUrl . '/api/',
            'changelog' => 'https://github.com/AMSAT-NA/satellite-status/blob/main/CHANGELOG',
        ],
    ],
]);
