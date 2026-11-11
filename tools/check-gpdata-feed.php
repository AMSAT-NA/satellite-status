<?php

declare(strict_types=1);

/**
 * CI check (Issue #25): GPDATA_FEED_URL is set and serves a usable GP feed.
 *
 * The feed address has no default in the code; it lives only in the
 * GPDATA_FEED_URL repository variable, which CD writes to the server's
 * .env. This check runs the sync's own fetch-and-validate code
 * (gpdata_fetch_feed: https only, HTTP 200, a JSON list, at least
 * GPDATA_MIN_FEED_RECORDS records, each with AMSAT_NAME and a valid
 * NORAD_CAT_ID), so CI accepts exactly what a production run would.
 *
 * Usage: GPDATA_FEED_URL=https://... php tools/check-gpdata-feed.php
 */

require_once __DIR__ . '/../api/v1/lib/gpdata_sync.php';

$url = gpdata_feed_url();

if ($url === '') {
    fwrite(STDERR, "::error title=check-gpdata-feed::GPDATA_FEED_URL is empty. Set the repository variable "
        . "(Settings > Secrets and variables > Actions > Variables).\n");
    exit(1);
}

if (stripos($url, 'https://') !== 0) {
    fwrite(STDERR, "::error title=check-gpdata-feed::GPDATA_FEED_URL must be an https:// URL (got: {$url}).\n");
    exit(1);
}

try {
    $records = gpdata_fetch_feed($url);
} catch (GpdataFeedException $e) {
    fwrite(STDERR, "::error title=check-gpdata-feed::{$e->getMessage()} ({$url})\n");
    exit(1);
}

$withId = count(array_filter($records, static fn (array $r): bool => $r['norad_cat_id'] !== null));

printf("OK: %s serves %d records (%d with NORAD_CAT_ID).\n", $url, count($records), $withId);
