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
