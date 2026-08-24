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
  -- Nothing writes to them anymore -- `observed_at` is the source of
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
  `observed_at`  timestamp                                                               NOT NULL,
  -- When the report was received by the server. Always DB-default or an
  -- explicit NOW() at insert time -- never accepted from request
  -- payloads. NULL for backfilled historical rows (no honest value).
  `submitted_at` timestamp                                                               NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_satellite_name_callsign_observed_at` (`name`, `callsign`, `observed_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;

CREATE TABLE `satellite_name` (
  `id`                int(11)      NOT NULL AUTO_INCREMENT,
  `name`              varchar(255) NOT NULL,
  `html_element_name` varchar(255) NOT NULL,
  `website`           varchar(255) NOT NULL,
  `date_changed`      timestamp    NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;

CREATE TABLE `users` (
  `id`       int(11)      NOT NULL AUTO_INCREMENT,
  `username` varchar(255) NOT NULL,
  `password` varchar(255) NOT NULL,
  `email`    varchar(255) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;
