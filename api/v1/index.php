<?php

declare(strict_types=1);

require_once __DIR__ . '/lib/bootstrap.php';

/**
 * Machine-readable discovery document for the v1 API root (/api/v1/).
 *
 * The endpoint list is derived from openapi.json at request time rather
 * than hand-maintained here, so it cannot drift out of sync with the
 * documented contract: adding, removing, or re-tagging a path in
 * openapi_spec.php (and regenerating openapi.json, which CI already
 * requires) is the only step needed for the change to show up here.
 *
 * Humans are served by overview.php (prose docs) and docs.php (Swagger
 * UI), both linked from this document; JSON_PRETTY_PRINT keeps this
 * response legible in a browser too.
 */

api_require_method(['GET']);

$specPath = __DIR__ . '/openapi.json';
$spec = is_file($specPath) ? json_decode((string) file_get_contents($specPath), true) : null;

if (!is_array($spec) || !isset($spec['paths']) || !is_array($spec['paths'])) {
    // Same failure mode and error code as openapi.php: without the spec
    // there is no authoritative endpoint list to publish.
    api_error_response(503, 'openapi_spec_missing', 'The OpenAPI document has not been generated.');
}

const API_DISCOVERY_METHODS = ['get', 'post', 'put', 'patch', 'delete'];

$endpoints = [];

foreach ($spec['paths'] as $path => $operations) {
    if (!is_array($operations)) {
        continue;
    }

    foreach ($operations as $method => $operation) {
        if (!in_array($method, API_DISCOVERY_METHODS, true) || !is_array($operation)) {
            continue;
        }

        $tags = is_array($operation['tags'] ?? null) ? $operation['tags'] : [];

        $endpoints[] = [
            'method' => strtoupper($method),
            'path' => $path,
            'url' => api_self_url($path),
            'summary' => $operation['summary'] ?? null,
            'legacy' => in_array('Legacy', $tags, true),
            'deprecated' => ($operation['deprecated'] ?? false) === true,
        ];
    }
}

api_json_response([
    'data' => [
        'name' => $spec['info']['title'] ?? 'AMSAT Satellite Status API',
        'version' => API_VERSION,
        'links' => [
            'self' => api_self_url(''),
            'overview' => api_self_url('overview.php'),
            'docs' => api_self_url('docs.php'),
            'openapi' => api_self_url('openapi.php'),
            'health' => api_self_url('health.php'),
            'changelog' => 'https://github.com/AMSAT-NA/satellite-status/blob/main/CHANGELOG',
        ],
        'endpoints' => $endpoints,
    ],
]);
