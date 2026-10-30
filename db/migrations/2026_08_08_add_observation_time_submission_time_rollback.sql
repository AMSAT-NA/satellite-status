-- Rollback for 2026_08_08_add_observation_time_submission_time.sql (Issue #24).
--
-- Drops the index and both new columns. Safe to run any time after the
-- forward migration, but ONLY before any app code that writes
-- observation_time/submission_time is deployed against this database -- once
-- new reports exist with no day/hour/period equivalent, rolling back
-- loses their timing information entirely (day/hour/period were never
-- populated for rows written after the Issue #24 app-code deploy).
--
--   # on azure193, from /opt/services/satellite-status:
--   docker compose exec -T db mariadb -u <user> -p<password> <database> < 2026_08_08_add_observation_time_submission_time_rollback.sql
--
-- Both statements are DDL (implicit commit each); ALTER TABLE ... DROP
-- COLUMN on MariaDB is not an instant operation the way ADD COLUMN is --
-- expect this to take noticeably longer than the forward migration's
-- step 1, though still nowhere near the multi-hour range discussed in
-- the forward script's header (no bulk row-by-row UPDATE is involved
-- here, just a table rebuild).

ALTER TABLE satellite
  DROP INDEX idx_satellite_name_callsign_observation_time;

ALTER TABLE satellite
  DROP COLUMN observation_time,
  DROP COLUMN submission_time;
