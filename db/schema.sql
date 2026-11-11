-- AMSAT Satellite Status Page -- database schema
--
-- Mirrors the production schema as of 2026-08-08 (MariaDB 10.11.14).
-- Derived from a mysqldump --no-data of the live database, with the
-- dump preamble, DROP TABLE / IF EXISTS statements, and AUTO_INCREMENT
-- state values removed. Column order, types, defaults, character sets,
-- and storage engines mirror production exactly -- please keep them in
-- sync if you change the live schema.

CREATE TABLE `satellite` (
  `name`         char(25)                                                                DEFAULT NULL,
  `longname`     char(25)                                                                DEFAULT NULL,
  `upmode`       enum('A','B','J','K','L','S','T','V','U','C','X')                       DEFAULT NULL,
  `downmode`     enum('A','B','J','K','L','S','T','V','U','C','X')                       DEFAULT NULL,
  -- `day`/`hour`/`period` are a frozen historical archive (Issue #24).
  -- Nothing writes to them anymore -- `observation_time` is the source of
  -- truth for when a report happened. Kept structurally so old data
  -- isn't silently dropped; not dropped in this migration.
  `day`          date                                                                    DEFAULT NULL,
  `hour`         int(11)                                                                 DEFAULT NULL,
  `period`       int(11)                                                                 DEFAULT NULL,
  `callsign`     char(15)                                                                DEFAULT NULL,
  `report`       enum('Heard','Not Heard','Telemetry Only','Crew Active')                DEFAULT NULL,
  `id`           int(11)                                                                 NOT NULL AUTO_INCREMENT,
  `grid_square`  varchar(6)                                                              DEFAULT NULL,
  -- When the satellite activity happened (source of truth going forward,
  -- Issue #24). Backfilled for historical rows from day/hour/period at
  -- 15-minute precision; see db/migrations/.
  `observation_time` timestamp                                                           NOT NULL,
  -- When the report was received by the server. Always DB-default or an
  -- explicit NOW() at insert time -- never accepted from request
  -- payloads. NULL for backfilled historical rows (no honest value).
  `submission_time` timestamp                                                            NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_satellite_name_callsign_observation_time` (`name`, `callsign`, `observation_time`),
  KEY `idx_satellite_observation_time` (`observation_time`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;

CREATE TABLE `satellite_name` (
  `id`                int(11)      NOT NULL AUTO_INCREMENT,
  `name`              varchar(255) NOT NULL,
  `html_element_name` varchar(255) NOT NULL,
  `website`           varchar(255) NOT NULL,
  -- Issue #25: NORAD catalog number (GP/OMM NORAD_CAT_ID), from the AMSAT
  -- GP feed only (gpdata-sync). Shared by all mode rows of one spacecraft;
  -- NULL until the spacecraft appears in the feed with an ID.
  `norad_cat_id`      int(10) unsigned DEFAULT NULL,
  `date_changed`      timestamp    NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;

-- Issue #25: one row per gpdata-sync run (scheduled or manual), so the
-- admin page -- and possibly a future public GET /api/v1/gpdata-sync -- can
-- show what the last run did. detail is JSON: changed IDs, catalog
-- spacecraft not in the feed, ambiguous matches, abort reason.
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

CREATE TABLE `users` (
  `id`       int(11)      NOT NULL AUTO_INCREMENT,
  `username` varchar(255) NOT NULL,
  `password` varchar(255) NOT NULL,
  `email`    varchar(255) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;
