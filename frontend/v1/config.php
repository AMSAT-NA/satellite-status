<?php

/*
  Configuration Items
*/

$siteUrl = getenv("SITE_URL") ?: "[SITEURL]";

$mysqlHost = getenv("MYSQL_HOST") ?: "[HOST]";
$mysqlUsername = getenv("MYSQL_USER") ?: "[USERNAME]";
$mysqlPassword = getenv("MYSQL_PASSWORD") ?: "[PASSWORD]";
$mysqlDatabase = getenv("MYSQL_DATABASE") ?: "[DATABASE]";

// Set by the CD pipeline at deploy time (see the configure-env job in
// .github/workflows/cd.yml). Not present in local dev, where there is no
// deploy step — code below must treat both as optional.
$appCommitSha = getenv("APP_COMMIT_SHA") ?: null;
$appDeployedAt = getenv("APP_DEPLOYED_AT") ?: null;

 ?>
