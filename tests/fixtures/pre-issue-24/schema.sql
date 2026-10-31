-- AMSAT Satellite Status Page -- database schema
--
-- Mirrors the production schema as of 2026-05-20 (MariaDB 10.11.14).
-- Derived from a mysqldump --no-data of the live database, with the
-- dump preamble, DROP TABLE / IF EXISTS statements, and AUTO_INCREMENT
-- state values removed. Column order, types, defaults, character sets,
-- and storage engines mirror production exactly -- please keep them in
-- sync if you change the live schema.

CREATE TABLE `satellite` (
  `name`        char(25)                                                                DEFAULT NULL,
  `longname`    char(25)                                                                DEFAULT NULL,
  `upmode`      enum('A','B','J','K','L','S','T','V','U','C','X')                       DEFAULT NULL,
  `downmode`    enum('A','B','J','K','L','S','T','V','U','C','X')                       DEFAULT NULL,
  `day`         date                                                                    DEFAULT NULL,
  `hour`        int(11)                                                                 DEFAULT NULL,
  `period`      int(11)                                                                 DEFAULT NULL,
  `callsign`    char(15)                                                                DEFAULT NULL,
  `report`      enum('Heard','Not Heard','Telemetry Only','Crew Active')                DEFAULT NULL,
  `id`          int(11)                                                                 NOT NULL AUTO_INCREMENT,
  `grid_square` varchar(6)                                                              DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;

CREATE TABLE `satellite_name` (
  `id`                int(11)      NOT NULL AUTO_INCREMENT,
  `name`              varchar(255) NOT NULL,
  `html_element_name` varchar(255) NOT NULL,
  `website`           varchar(255) NOT NULL,
  `date_changed`      timestamp    NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;

-- Legacy table preserved on the live database. Not read or written by
-- the current application code; kept here so a fresh dev environment
-- mirrors production.
CREATE TABLE `satellite_old` (
  `name`        char(10)                                                                 CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `longname`    char(25)                                                                 CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `upmode`      enum('A','B','J','K','L','S','T','V','U','C','X')                        CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `downmode`    enum('A','B','J','K','L','S','T','V','U','C','X')                        CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `day`         date                                                                     DEFAULT NULL,
  `hour`        int(11)                                                                  DEFAULT NULL,
  `period`      int(11)                                                                  DEFAULT NULL,
  `callsign`    char(15)                                                                 CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `report`      enum('Heard','Not Heard','Telemetry Only','Crew Active')                 CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `id`          int(11)                                                                  NOT NULL DEFAULT 0,
  `grid_square` varchar(6)                                                               CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL
) ENGINE=MyISAM DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

CREATE TABLE `users` (
  `id`       int(11)      NOT NULL AUTO_INCREMENT,
  `username` varchar(255) NOT NULL,
  `password` varchar(255) NOT NULL,
  `email`    varchar(255) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;
