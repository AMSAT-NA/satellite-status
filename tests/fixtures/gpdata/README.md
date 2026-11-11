# GP feed fixture (Issue #25)

`daily-bulletin.json` is a 30-record subset of the AMSAT GP feed (the
address in the `GPDATA_FEED_URL` repository variable; captured Nov 2026),
trimmed to the fields gpdata-sync reads plus a few for realism. It includes
the name patterns the matcher must handle: leading zeros (`AO-07`), dashes
(`RS-38S`), alternate names on either side of a parenthesis
(`UmKA 1 (RS40S)`, `RS95S (QMR-KWT-2)`), and a record without an ID
(`LILIUM-4`). The CI test server points `GPDATA_FEED_URL` at this file.
