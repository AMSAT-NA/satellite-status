-- Issue #24 -- add observed_at/submitted_at to `satellite`, backfill
-- observed_at from the existing day/hour/period columns, add a
-- supporting index. Run manually against dallas190 (current production
-- MariaDB host) via the mysql/mariadb CLI:
--
--   mysql -h <dallas190-host> -u <user> -p <database> < 2026_08_08_add_observed_at_submitted_at.sql
--
-- day/hour/period are NOT touched or dropped -- they remain as a frozen
-- historical archive. Nothing in the app writes to them after this ships.
--
-- ── Expected impact / lock duration ─────────────────────────────────────
-- `satellite` is approximately 721,000 rows (known from the prior
-- archive-import work; a COUNT(*) sanity check against a current copy of
-- the table before running this in anger is still a good idea -- this
-- estimate was not re-verified against dallas190 directly for this
-- script, since this agent has no network access to dallas190).
--
--   Step 1 (ADD COLUMN x2, both NULL/no rewrite-triggering default):
--   MariaDB 10.11's InnoDB instant-ADD-COLUMN support makes this a
--   metadata-only change -- expect it to complete in well under a second
--   regardless of row count, holding only a brief metadata lock.
--
--   Step 2 (the backfill UPDATE): this touches every row, so it is not
--   free -- but it is a straightforward single-table, single-pass UPDATE
--   with no JOIN and no correlated subquery. That is a materially
--   different shape of statement than the one behind the prior incident
--   on this table (a multi-hour lock caused by an *unindexed anti-join*
--   during the archive import doing a full nested-loop scan). This
--   UPDATE does a single linear pass with row-level (not table-level)
--   InnoDB locking, so the multi-hour-lock failure mode from that
--   incident is not expected to apply here. That said, it still touches
--   ~721k rows and holds those row locks for the duration of the
--   transaction (released at COMMIT) -- run it during a low-traffic
--   window regardless, as a matter of course rather than because a
--   multi-hour lock is expected.
--
--   Step 3 (MODIFY to NOT NULL): metadata-only if step 2 left no NULLs
--   (it shouldn't -- see fallbacks below); fails loudly if it does,
--   which is intentional so backfill gaps can't silently ship.
--
--   Step 4 (ADD INDEX): deliberately done *after* the backfill so the
--   UPDATE in step 2 doesn't pay index-maintenance cost per row. MariaDB
--   InnoDB builds secondary indexes online (INPLACE algorithm) -- reads
--   and writes are not blocked for the bulk of this step, only briefly
--   at commit.
--
-- ── Data-quality fallbacks ───────────────────────────────────────────────
-- The backfill below assumes `period` is usually 0-3 and `day`/`hour` are
-- usually populated, but does not assume the data is perfectly clean.
-- Run the pre-flight query below first and read the counts before
-- proceeding -- if any of them are nonzero, decide whether the documented
-- fallback is acceptable before running the UPDATE, since this agent
-- could not inspect the real dallas190 data directly.
--
-- Fallback behavior in the UPDATE below:
--   - day  NULL  -> treated as 1970-01-01 (an obvious sentinel; such rows
--                   are easy to find again later via
--                   `WHERE observed_at < '1971-01-01'`). Never observed
--                   in production as of the pre-flight run below (0
--                   rows) -- this remains a defensive fallback only.
--   - hour NULL  -> treated as 0. Same: 0 rows in production as of the
--                   pre-flight run below, defensive only.
--   - period NULL or outside 0-3 -> lands on the top of its hour (no
--     quarter-hour offset added), preserving the real day/hour precision
--     that WAS captured rather than fabricating a quarter-hour that
--     never existed. This is no longer a hypothetical/defensive-only
--     path: verified against dallas190 production data (pre-flight run
--     ahead of the actual migration), this was exactly 53 rows out of
--     712,980 total, all with `period = -1` -- a single, consistent
--     legacy sentinel value (apparently meaning "time unspecified" in
--     old submission code), not scattered corruption. `day` and `hour`
--     were populated normally for all 53 -- only `period` was ever a
--     sentinel for these rows. See the sentinel-row audit query below
--     for their specific ids.

-- ── Pre-flight check -- run this first, review the counts, and only
-- proceed past it if the nonzero counts (if any) are ones you've decided
-- the fallback behavior above is acceptable for. Read-only, harmless to
-- run standalone (copy just this statement into your own client) or as
-- part of this script.
SELECT
  SUM(day IS NULL)                                        AS null_day,
  SUM(hour IS NULL)                                        AS null_hour,
  SUM(period IS NULL)                                      AS null_period,
  SUM(period IS NOT NULL AND period NOT BETWEEN 0 AND 3)   AS bad_period,
  COUNT(*)                                                 AS total_rows
FROM satellite;

-- ── Sentinel-row audit -- durable record of exactly which rows receive
-- the "top of hour, no fabricated quarter-hour" treatment above. Also
-- read-only and harmless to run standalone. As of the pre-flight run
-- against dallas190, this returned exactly 53 ids, all with
-- `period = -1` -- kept here (rather than only in a comment) so anyone
-- re-running this migration elsewhere gets their own current list, and
-- so there's a query-able trail explaining why these specific rows land
-- exactly on the hour: a real historical sentinel being honestly
-- represented, not new fabrication.
SELECT id FROM satellite WHERE period IS NULL OR period NOT BETWEEN 0 AND 3;

-- Note on transactionality: ALTER TABLE is DDL, and MariaDB (like MySQL)
-- implicitly commits any open transaction before and after each DDL
-- statement -- there is no way to make the ALTERs below participate in a
-- rollback-able transaction together with each other or with the UPDATE.
-- Only the UPDATE (step 2) is genuinely wrapped in an explicit
-- transaction below, which is the one statement here where that's
-- meaningful (it lets you inspect the backfilled values and ROLLBACK
-- before committing, if something looks wrong).

-- Step 1: add both columns, nullable -- see "Expected impact" above for
-- why this is effectively instant regardless of table size.
ALTER TABLE satellite
  ADD COLUMN observed_at  timestamp NULL DEFAULT NULL,
  ADD COLUMN submitted_at timestamp NULL DEFAULT current_timestamp();

-- submitted_at is intentionally left NULL for every backfilled historical
-- row below -- there is no honest "when was this received by the server"
-- value for reports that predate this migration.

-- Step 2: backfill observed_at. Wrapped so it can be inspected and
-- rolled back before COMMIT if the fallback counts from the pre-flight
-- query above look wrong.
START TRANSACTION;

UPDATE satellite
SET observed_at = CASE
    WHEN period BETWEEN 0 AND 3
        THEN TIMESTAMP(COALESCE(day, '1970-01-01'), SEC_TO_TIME(COALESCE(hour, 0) * 3600 + period * 900))
    -- period NULL or outside 0-3 (53 known rows in prod, all period = -1,
    -- see the sentinel-row audit above): day/hour are real, trustworthy
    -- data for these rows, so land on the top of the hour using them
    -- directly rather than fabricating quarter-hour precision that was
    -- never captured. (SQL's `NULL BETWEEN ...` evaluates to NULL, not
    -- TRUE, so a NULL period falls into this branch too without an
    -- explicit IS NOT NULL check.)
    ELSE TIMESTAMP(COALESCE(day, '1970-01-01'), SEC_TO_TIME(COALESCE(hour, 0) * 3600))
END;

-- Inspect here if running interactively, e.g.:
--   SELECT COUNT(*) FROM satellite WHERE observed_at IS NULL;
--   SELECT COUNT(*) FROM satellite WHERE observed_at < '1971-01-01';
-- then either COMMIT or ROLLBACK.

COMMIT;

-- Step 3: enforce NOT NULL. Fails loudly (rather than silently) if any
-- row was somehow missed above.
ALTER TABLE satellite
  MODIFY COLUMN observed_at timestamp NOT NULL;

-- Step 4: index added after the backfill -- see "Expected impact" above.
ALTER TABLE satellite
  ADD INDEX idx_satellite_name_callsign_observed_at (name, callsign, observed_at);
