-- Rollback for 2026_11_11_add_norad_cat_id_gpdata_sync_run.sql (Issue #25).
--
-- Only after the sync code is rolled back too: that code reads and writes
-- both objects. Loses the synced IDs (re-populated by the next sync after
-- re-applying the forward migration) and the run history.
--
--   docker compose exec -T db sh -c 'mariadb -u"$MYSQL_USER" -p"$MYSQL_PASSWORD" "$MYSQL_DATABASE"' \
--     < 2026_11_11_add_norad_cat_id_gpdata_sync_run_rollback.sql

DROP TABLE IF EXISTS `gpdata_sync_run`;
ALTER TABLE satellite_name DROP COLUMN `norad_cat_id`;
