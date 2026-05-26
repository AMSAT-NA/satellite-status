<?php

declare(strict_types=1);

require_once __DIR__ . '/lib/bootstrap.php';

header('Content-Type: text/plain; charset=utf-8');

echo "AMSAT Satellite Status API v" . API_VERSION . "\n\n";
echo "Documentation: " . rtrim((string) $siteUrl, '/') . "/api/\n";
echo "OpenAPI:       " . api_self_url('openapi.php') . "\n\n";
echo "Primary endpoints:\n";
echo "  GET  " . api_self_url('catalog.php') . "\n";
echo "  GET  " . api_self_url('reports.php?name=AO-91&hours=24') . "\n";
echo "  POST " . api_self_url('reports.php') . "\n";
echo "  GET  " . api_self_url('summary.php?hours=24') . "\n";
echo "  GET  " . api_self_url('statuses.php') . "\n";
echo "  GET  " . api_self_url('health.php') . "\n\n";
echo "Legacy compatibility:\n";
echo "  GET  " . api_self_url('satellites.php') . "\n";
echo "  GET  " . api_self_url('sat_info.php?name=AO-91&hours=24') . "\n";
