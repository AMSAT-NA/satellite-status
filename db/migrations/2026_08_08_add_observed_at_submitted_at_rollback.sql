-- Rollback for 2026_08_08_add_observed_at_submitted_at.sql (Issue #24).
--
-- Drops the index and both new columns. Safe to run any time after the
-- forward migration, but ONLY before any app code that writes
-- observed_at/submitted_at is deployed against this database -- once
-- new reports exist with no day/hour/period equivalent, rolling back
-- loses their timing information entirely (day/hour/period were never
-- populated for rows written after the Issue #24 app-code deploy).
--
--   mysql -h <dallas190-host> -u <user> -p <database> < 2026_08_08_add_observed_at_submitted_at_rollback.sql
--
-- Both statements are DDL (implicit commit each); ALTER TABLE ... DROP
-- COLUMN on MariaDB is not an instant operation the way ADD COLUMN is --
-- expect this to take noticeably longer than the forward migration's
-- step 1, though still nowhere near the multi-hour range discussed in
-- the forward script's header (no bulk row-by-row UPDATE is involved
-- here, just a table rebuild).

ALTER TABLE satellite
  DROP INDEX idx_satellite_name_callsign_observed_at;

ALTER TABLE satellite
  DROP COLUMN observed_at,
  DROP COLUMN submitted_at;
