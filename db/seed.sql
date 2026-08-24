INSERT INTO satellite_name (name, html_element_name, website) VALUES
  ('AO-91', 'AO-91', 'https://www.amsat.org/two-way-satellites/ao-91/'),
  ('ISS FM Repeater', 'ISS-FM', 'https://www.amsat.org/status/'),
  ('SO-50', 'SO-50', 'https://www.amsat.org/two-way-satellites/so-50-saudisat-1c/'),
  ('RS-44', 'RS-44', 'https://www.amsat.org/status/'),
  ('FO-29', 'FO-29', 'https://www.amsat.org/status/');

-- observed_at is derived from the day/hour/period values on each row
-- below using the same TIMESTAMP(day, SEC_TO_TIME(hour*3600+period*900))
-- formula as the production backfill migration (db/migrations/), just so
-- dev seed data looks plausible -- day/hour/period themselves are a
-- frozen historical archive going forward (Issue #24) and nothing reads
-- or writes them anymore except this seed file.
INSERT INTO satellite
  (name, longname, upmode, downmode, day, hour, period, callsign, report, id, grid_square, observed_at)
VALUES
  ('AO-91', 'AO-91', NULL, NULL, UTC_DATE(), HOUR(UTC_TIME()), 1, 'N0CALL', 'Heard', NULL, 'EM48',
    TIMESTAMP(UTC_DATE(), SEC_TO_TIME(HOUR(UTC_TIME()) * 3600 + 1 * 900))),
  ('AO-91', 'AO-91', NULL, NULL, UTC_DATE(), HOUR(UTC_TIME()), 2, 'K1ABC', 'Telemetry Only', NULL, 'FN31',
    TIMESTAMP(UTC_DATE(), SEC_TO_TIME(HOUR(UTC_TIME()) * 3600 + 2 * 900))),
  ('ISS-FM', 'ISS-FM', NULL, NULL, UTC_DATE(), HOUR(UTC_TIME()), 0, 'W9XYZ', 'Crew Active', NULL, 'EN52',
    TIMESTAMP(UTC_DATE(), SEC_TO_TIME(HOUR(UTC_TIME()) * 3600 + 0 * 900))),
  ('SO-50', 'SO-50', NULL, NULL, UTC_DATE(), HOUR(UTC_TIME()), 3, 'VE3SAT', 'Not Heard', NULL, 'FN03',
    TIMESTAMP(UTC_DATE(), SEC_TO_TIME(HOUR(UTC_TIME()) * 3600 + 3 * 900))),
  ('RS-44', 'RS-44', NULL, NULL, DATE_SUB(UTC_DATE(), INTERVAL 1 DAY), 18, 1, 'G0SAT', 'Heard', NULL, 'IO91',
    TIMESTAMP(DATE_SUB(UTC_DATE(), INTERVAL 1 DAY), SEC_TO_TIME(18 * 3600 + 1 * 900))),
  ('FO-29', 'FO-29', NULL, NULL, DATE_SUB(UTC_DATE(), INTERVAL 2 DAY), 14, 2, 'JA1SAT', 'Heard', NULL, 'PM95',
    TIMESTAMP(DATE_SUB(UTC_DATE(), INTERVAL 2 DAY), SEC_TO_TIME(14 * 3600 + 2 * 900)));

INSERT INTO users (username, password, email) VALUES
  ('admin', '$2y$10$shR4zMvKh82tv6FYSrpCDeAOzSf3sow6Atqx7w2Rj7gks806607mO', 'admin@example.test');
