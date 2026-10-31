<?php

/*
  Configuration Items
*/

$siteUrl = rtrim(getenv("SITE_URL") ?: "[SITEURL]", '/');

$mysqlHost = getenv("MYSQL_HOST") ?: "[HOST]";
$mysqlUsername = getenv("MYSQL_USER") ?: "[USERNAME]";
$mysqlPassword = getenv("MYSQL_PASSWORD") ?: "[PASSWORD]";
$mysqlDatabase = getenv("MYSQL_DATABASE") ?: "[DATABASE]";

// Set by the CD pipeline at deploy time (see the configure-env job in
// .github/workflows/cd.yml). Not present in local dev, where there is no
// deploy step — code below must treat both as optional.
$appCommitSha = getenv("APP_COMMIT_SHA") ?: null;
$appDeployedAt = getenv("APP_DEPLOYED_AT") ?: null;

// Base URL submit.php uses to call the API server-to-server (Issue #24:
// submit.php POSTs to the API instead of writing to MySQL directly).
// Distinct from the public-facing $siteUrl: in a real multi-container
// deploy (docker-compose.yml / deploy/docker-compose.yml), the frontend
// and API are separate containers, and dialing the public $siteUrl from
// inside a container is not reliably routable back in (no guaranteed
// hairpin NAT/DNS path, and in CI it would be the literal production
// domain). Set explicitly to the API container's internal address in both
// compose files; falls back to $siteUrl/api/v1 for environments where
// frontend and API are served by the same process (e.g. the PHPUnit/
// Playwright CI job's single `php -S` dev server).
$apiInternalUrl = rtrim(getenv("API_INTERNAL_URL") ?: ($siteUrl . '/api/v1'), '/');

 ?>
