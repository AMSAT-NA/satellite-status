-- Issue #25 -- NORAD catalog numbers from the AMSAT GP feed.
--
--   1. satellite_name.norad_cat_id (nullable; filled only by gpdata-sync)
--   2. gpdata_sync_run (one row per sync run)
--
-- Run manually against the production database on azure193 BEFORE the PR
-- that ships the sync code is merged (the new code reads both). From
-- /opt/services/satellite-status:
--
--   docker compose exec -T db sh -c 'mariadb -u"$MYSQL_USER" -p"$MYSQL_PASSWORD" "$MYSQL_DATABASE"' \
--     < 2026_11_11_add_norad_cat_id_gpdata_sync_run.sql
--
-- Impact: satellite_name is ~95 rows; ADD COLUMN with a NULL default is an
-- instant (metadata-only) change in MariaDB. CREATE TABLE is new. Both are
-- DDL with implicit commits; expect well under a second, no app downtime.
-- The old code ignores both, so running this early is safe.
--
-- Rollback: 2026_11_11_add_norad_cat_id_gpdata_sync_run_rollback.sql

ALTER TABLE satellite_name
  ADD COLUMN `norad_cat_id` int(10) unsigned DEFAULT NULL AFTER `website`;

CREATE TABLE `gpdata_sync_run` (
  `id`             int(11)                                   NOT NULL AUTO_INCREMENT,
  `trigger_source` enum('scheduled','manual')                NOT NULL,
  `status`         enum('running','ok','aborted','error')    NOT NULL DEFAULT 'running',
  `started_at`     timestamp                                 NOT NULL DEFAULT current_timestamp(),
  `finished_at`    timestamp                                 NULL DEFAULT NULL,
  `feed_records`   int(10) unsigned                          DEFAULT NULL,
  `matched`        int(10) unsigned                          DEFAULT NULL,
  `changed`        int(10) unsigned                          DEFAULT NULL,
  `detail`         text                                      DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_gpdata_sync_run_started_at` (`started_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;
