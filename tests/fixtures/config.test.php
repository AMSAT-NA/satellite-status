<?php
/*
  Test-only config. Copied over config.php by the CI job before starting
  the test web server. Values come from CI environment variables; see
  .github/workflows/ci.yml.

  Not used in production; not used by the locally-running docker-compose
  stack (which sets SITE_URL directly via its own `environment:` block).
*/

$siteUrl       = rtrim(getenv('TEST_BASE_URL') ?: 'http://127.0.0.1:8000', '/');
$mysqlHost     = getenv('TEST_DB_HOST')     ?: 'mariadb';
$mysqlUsername = getenv('TEST_DB_USER')     ?: 'satstatus';
$mysqlPassword = getenv('TEST_DB_PASS')     ?: 'satstatus';
$mysqlDatabase = getenv('TEST_DB_NAME')     ?: 'satstatus';

// Frontend-only (Issue #24): submit.php POSTs to the API server-to-server.
// In this test harness frontend and API are served by the same php -S
// process on $siteUrl, so the API_INTERNAL_URL fallback in
// frontend/v1/config.php ($siteUrl . '/api/v1') is exactly right -- set it
// explicitly here too since this file replaces that one wholesale rather
// than extending it.
$apiInternalUrl = $siteUrl . '/api/v1';

// Deploy-identity values, only ever set by the CD pipeline. Left null here
// (same as an un-deployed local dev run) so tests exercise the "not
// deployed" code path rather than a fabricated commit/timestamp.
$appCommitSha  = null;
$appDeployedAt = null;
