-- AMSAT Satellite Status Page -- database schema
--
-- This schema is reverse-engineered from the SQL queries in the codebase
-- (submit.php, index.php, api/v1/sat_info.php, admin/*.php). It has not yet
-- been confirmed against production -- please correct anything that differs
-- from what the live database actually has.
--
-- What to check during review:
--   * Column types (lengths in particular)
--   * Indexes the production DB has that aren't represented here
--   * Whether `satellite.legacy1` / `satellite.legacy2` exist in production,
--     and if so, what they're actually called and used for. The code paths
--     that used to insert NULL into them positionally have been refactored
--     to use explicit column lists, so they're no longer written by the
--     application; they're kept here in case production has them as NOT NULL
--     with some default.

CREATE TABLE satellite (
  name        VARCHAR(64)  NOT NULL,
  longname    VARCHAR(128) NOT NULL,
  id          INT AUTO_INCREMENT PRIMARY KEY,
  legacy1     INT NULL,
  day         DATE         NOT NULL,
  hour        TINYINT      NOT NULL,
  period      TINYINT      NOT NULL,
  callsign    VARCHAR(16)  NOT NULL,
  report      VARCHAR(32)  NOT NULL,
  legacy2     INT NULL,
  grid_square VARCHAR(8)   NULL,
  INDEX idx_name_day (name, day)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE satellite_name (
  id                INT AUTO_INCREMENT PRIMARY KEY,
  name              VARCHAR(64)  NOT NULL,
  html_element_name VARCHAR(64)  NOT NULL,
  website           VARCHAR(255) NULL,
  UNIQUE KEY uniq_html (html_element_name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE users (
  id       INT AUTO_INCREMENT PRIMARY KEY,
  username VARCHAR(64)  NOT NULL UNIQUE,
  password VARCHAR(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
