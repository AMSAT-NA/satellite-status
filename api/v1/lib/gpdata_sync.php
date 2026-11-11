<?php

declare(strict_types=1);

/**
 * gpdata-sync (Issue #25): pull NORAD catalog numbers from the AMSAT GP feed
 * into satellite_name.norad_cat_id.
 *
 * The feed is the only source of these IDs; nothing else in the app writes
 * them. Called from three places, all with the same behaviour:
 *   - the daily scheduler in the API image (api/bin/gpdata-sync.php)
 *   - POST /api/v1/gpdata-sync (token-protected; the admin "Sync now" button)
 *   - tests
 *
 * This file has no dependency on config, HTTP or the response helpers: it
 * takes a mysqli connection and a feed location, and returns a result
 * array. Rules (decided on #25):
 *   - The feed is read and validated in full first. If it can't be read, isn't
 *     a JSON list, has malformed records or has suspiciously few records,
 *     the run aborts with no writes.
 *   - A catalog spacecraft (all its mode rows: QO-100 [NB] and [WB], ...)
 *     is matched to the feed by name -- see gpdata_name_key().
 *   - Matched with an ID: every mode row gets that ID (set or corrected).
 *   - Not in the feed, or in the feed without an ID yet: current IDs are
 *     kept. A feed glitch or a re-entered satellite must not wipe IDs.
 *   - Name matches several feed entries with different IDs: skipped,
 *     reported, never guessed.
 *   - One run at a time (named DB lock); every run is recorded in
 *     gpdata_sync_run.
 */

const GPDATA_DEFAULT_FEED_URL = 'https://newark192.amsat.org/gpdata/current/daily-bulletin.json';

// The feed had 94 records in Nov 2026. Far fewer means a truncated or
// placeholder response, not a real bulletin -- abort rather than sync it.
const GPDATA_MIN_FEED_RECORDS = 20;

const GPDATA_LOCK_NAME = 'amsat-gpdata-sync';
const GPDATA_FETCH_TIMEOUT_SECONDS = 30;

final class GpdataFeedException extends RuntimeException
{
}

/**
 * Feed location from GPDATA_FEED_URL, else the default. Repointing to the
 * production gpdata service is a config change, not a code change.
 */
function gpdata_feed_url(): string
{
    $url = trim((string) getenv('GPDATA_FEED_URL'));

    return $url !== '' ? $url : GPDATA_DEFAULT_FEED_URL;
}

/**
 * Comparison key for a spacecraft name: its runs of letters and digits,
 * letters upper-cased, digits without leading zeros. Covers the mismatch
 * patterns between the feed's AMSAT_NAME and the catalog:
 *   "AO-07"   and "AO-7"    -> "AO 7"
 *   "RS-38S"  and "RS38S"   -> "RS 38 S"
 *   "TEVEL2-1"              -> "TEVEL 2 1"  (kept distinct from "TEVEL-21")
 * Token boundaries are kept, so concatenation can't create false matches.
 */
function gpdata_name_key(string $name): string
{
    preg_match_all('/[A-Za-z]+|\d+/', $name, $m);

    $tokens = array_map(
        static fn (string $t): string => ctype_digit($t) ? (ltrim($t, '0') !== '' ? ltrim($t, '0') : '0') : strtoupper($t),
        $m[0]
    );

    return implode(' ', $tokens);
}

/**
 * Catalog API name without its mode suffix: "AO-123_[FM]" -> "AO-123",
 * "Out_of_the_Box_[Music]" -> "Out of the Box". Also accepts the display
 * form ("AO-123 [FM]").
 */
function gpdata_catalog_spacecraft_name(string $catalogName): string
{
    $base = preg_replace('/[_ ]?\[[^\]]*\]$/', '', trim($catalogName)) ?? $catalogName;

    return str_replace('_', ' ', $base);
}

/**
 * Names a feed record answers to. "HYPERVIEW-1G (RS66S)" answers to both
 * "HYPERVIEW-1G" and "RS66S" -- the catalog uses either side
 * ("UmKA 1 (RS40S)" -> RS40S, "RS95S (QMR-KWT-2)" -> RS95S).
 *
 * @return list<string>
 */
function gpdata_feed_names(string $amsatName): array
{
    if (preg_match('/^(.*?)\s*\(([^()]*)\)\s*$/', $amsatName, $m) === 1 && trim($m[1]) !== '') {
        return [trim($m[1]), trim($m[2])];
    }

    return [$amsatName];
}

/**
 * Fetches and validates the feed. Throws GpdataFeedException (with a
 * reason fit for the run record) on anything that should abort the run.
 *
 * Accepts an https:// URL or a local file path (tests, and an operator
 * pointing at a downloaded copy). Plain http:// is refused.
 *
 * @return list<array{amsat_name: string, norad_cat_id: ?int}>
 */
function gpdata_fetch_feed(string $location): array
{
    $isHttps = stripos($location, 'https://') === 0;

    if (!$isHttps && preg_match('#^[a-z][a-z0-9+.-]*://#i', $location) === 1) {
        throw new GpdataFeedException('Feed location must be an https:// URL or a local file path.');
    }

    $context = stream_context_create([
        'http' => [
            'timeout' => GPDATA_FETCH_TIMEOUT_SECONDS,
            'ignore_errors' => true,
            'header' => "User-Agent: AMSAT-satellite-status-gpdata-sync (+https://status.amsat.org)\r\n",
        ],
    ]);

    $body = @file_get_contents($location, false, $context);

    if ($isHttps) {
        // http_get_last_response_headers() (PHP 8.4+): $http_response_header
        // is deprecated in PHP 8.5. Fallback only for PHP < 8.4.
        $responseHeaders = function_exists('http_get_last_response_headers')
            ? (http_get_last_response_headers() ?? [])
            : ($http_response_header ?? []);
        $statusLine = $responseHeaders[0] ?? '';
        if (preg_match('#^HTTP/\S+\s+(\d{3})#', $statusLine, $m) !== 1 || $m[1] !== '200') {
            throw new GpdataFeedException('Feed request failed: ' . ($statusLine !== '' ? $statusLine : 'no response'));
        }
    }

    if ($body === false || $body === '') {
        throw new GpdataFeedException('Feed could not be read.');
    }

    try {
        $decoded = json_decode($body, true, 32, JSON_THROW_ON_ERROR);
    } catch (JsonException $e) {
        throw new GpdataFeedException('Feed is not valid JSON: ' . $e->getMessage());
    }

    if (!is_array($decoded) || !array_is_list($decoded)) {
        throw new GpdataFeedException('Feed is not a JSON list of records.');
    }

    if (count($decoded) < GPDATA_MIN_FEED_RECORDS) {
        throw new GpdataFeedException(sprintf(
            'Feed has %d records; at least %d expected.',
            count($decoded),
            GPDATA_MIN_FEED_RECORDS
        ));
    }

    $records = [];
    foreach ($decoded as $i => $record) {
        $name = is_array($record) ? ($record['AMSAT_NAME'] ?? null) : null;
        $id = is_array($record) && array_key_exists('NORAD_CAT_ID', $record) ? $record['NORAD_CAT_ID'] : false;

        if (!is_string($name) || trim($name) === '') {
            throw new GpdataFeedException("Feed record {$i} has no AMSAT_NAME.");
        }

        // NORAD_CAT_ID: up to nine digits (CCSDS OMM). null = not assigned yet.
        if ($id !== null && !(is_int($id) && $id > 0 && $id <= 999999999)) {
            throw new GpdataFeedException("Feed record {$i} ({$name}) has an invalid NORAD_CAT_ID.");
        }

        $records[] = ['amsat_name' => trim($name), 'norad_cat_id' => $id];
    }

    return $records;
}

/**
 * Runs one sync. Returns the run result; never throws for feed problems
 * (those are an "aborted" run). Unexpected DB errors are an "error" run.
 * "busy" means another run held the lock; nothing was done or recorded.
 *
 * @param 'scheduled'|'manual' $triggerSource
 * @return array<string, mixed>
 */
function gpdata_sync(mysqli $db, string $triggerSource, ?string $feedLocation = null): array
{
    $feedLocation ??= gpdata_feed_url();

    $lock = $db->query("SELECT GET_LOCK('" . GPDATA_LOCK_NAME . "', 0)");
    if ($lock === false || (int) $lock->fetch_row()[0] !== 1) {
        return ['status' => 'busy', 'message' => 'Another gpdata-sync run is in progress.'];
    }

    try {
        $stmt = $db->prepare('INSERT INTO gpdata_sync_run (trigger_source) VALUES (?)');
        if ($stmt === false) {
            throw new RuntimeException('Could not record the run: ' . $db->error);
        }
        $stmt->bind_param('s', $triggerSource);
        $stmt->execute();
        $runId = $db->insert_id;
        $stmt->close();

        try {
            $records = gpdata_fetch_feed($feedLocation);
        } catch (GpdataFeedException $e) {
            return gpdata_finish_run($db, $runId, 'aborted', null, null, [], ['reason' => $e->getMessage()]);
        }

        try {
            $outcome = gpdata_apply($db, $records);
        } catch (Throwable $e) {
            return gpdata_finish_run($db, $runId, 'error', count($records), null, [], ['reason' => $e->getMessage()]);
        }

        return gpdata_finish_run($db, $runId, 'ok', count($records), $outcome['matched'], $outcome['changed'], [
            'not_in_feed' => $outcome['not_in_feed'],
            'in_feed_without_id' => $outcome['in_feed_without_id'],
            'ambiguous' => $outcome['ambiguous'],
        ]);
    } finally {
        $db->query("SELECT RELEASE_LOCK('" . GPDATA_LOCK_NAME . "')");
    }
}

/**
 * Matches catalog spacecraft to feed records and writes changed IDs, in
 * one transaction.
 *
 * @param list<array{amsat_name: string, norad_cat_id: ?int}> $records
 * @return array{matched: int, changed: list<array{spacecraft: string, from: ?int, to: int}>,
 *               not_in_feed: list<string>, in_feed_without_id: list<string>,
 *               ambiguous: list<array{spacecraft: string, feed: list<string>}>}
 */
function gpdata_apply(mysqli $db, array $records): array
{
    $index = [];
    foreach ($records as $record) {
        foreach (gpdata_feed_names($record['amsat_name']) as $alias) {
            $index[gpdata_name_key($alias)][] = $record;
        }
    }

    // Group catalog mode rows by spacecraft.
    $spacecraft = [];
    // Match on the API name (html_element_name, e.g. "AO-7_[U/v]"): that's
    // the canonical identifier; the display name is free text.
    $result = $db->query('SELECT id, html_element_name, norad_cat_id FROM satellite_name ORDER BY html_element_name');
    if ($result === false) {
        throw new RuntimeException('Catalog query failed: ' . $db->error);
    }
    while ($row = $result->fetch_assoc()) {
        $displayName = gpdata_catalog_spacecraft_name((string) $row['html_element_name']);
        $key = gpdata_name_key($displayName);
        $spacecraft[$key]['name'] ??= $displayName;
        $spacecraft[$key]['rows'][] = [
            'id' => (int) $row['id'],
            'norad_cat_id' => $row['norad_cat_id'] === null ? null : (int) $row['norad_cat_id'],
        ];
    }

    $outcome = ['matched' => 0, 'changed' => [], 'not_in_feed' => [], 'in_feed_without_id' => [], 'ambiguous' => []];
    $update = $db->prepare('UPDATE satellite_name SET norad_cat_id = ? WHERE id = ?');

    $db->begin_transaction();
    try {
        foreach ($spacecraft as $key => $craft) {
            $hits = $index[$key] ?? [];

            if ($hits === []) {
                $outcome['not_in_feed'][] = $craft['name'];
                continue;
            }

            $ids = array_values(array_unique(array_map(static fn (array $h) => $h['norad_cat_id'], $hits), SORT_REGULAR));
            if (count($ids) > 1) {
                $outcome['ambiguous'][] = [
                    'spacecraft' => $craft['name'],
                    'feed' => array_values(array_unique(array_map(static fn (array $h) => $h['amsat_name'], $hits))),
                ];
                continue;
            }

            $id = $ids[0];
            if ($id === null) {
                $outcome['in_feed_without_id'][] = $craft['name'];
                continue;
            }

            $outcome['matched']++;
            $previous = null;
            $changed = false;
            foreach ($craft['rows'] as $row) {
                if ($row['norad_cat_id'] !== $id) {
                    $previous ??= $row['norad_cat_id'];
                    $update->bind_param('ii', $id, $row['id']);
                    if ($update->execute() === false) {
                        throw new RuntimeException('Catalog update failed: ' . $update->error);
                    }
                    $changed = true;
                }
            }
            if ($changed) {
                $outcome['changed'][] = ['spacecraft' => $craft['name'], 'from' => $previous, 'to' => $id];
            }
        }
        $db->commit();
    } catch (Throwable $e) {
        $db->rollback();
        throw $e;
    } finally {
        $update->close();
    }

    return $outcome;
}

/**
 * Records the outcome on the run row and returns the result array.
 *
 * @param list<array{spacecraft: string, from: ?int, to: int}> $changed
 * @param array<string, mixed> $detail
 * @return array<string, mixed>
 */
function gpdata_finish_run(
    mysqli $db,
    int $runId,
    string $status,
    ?int $feedRecords,
    ?int $matched,
    array $changed,
    array $detail
): array {
    $detail = ['changed' => $changed] + $detail;
    $json = json_encode($detail, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    $changedCount = $status === 'ok' ? count($changed) : null;

    $stmt = $db->prepare(
        'UPDATE gpdata_sync_run SET status = ?, finished_at = CURRENT_TIMESTAMP, feed_records = ?, matched = ?, changed = ?, detail = ? WHERE id = ?'
    );
    $stmt->bind_param('siiisi', $status, $feedRecords, $matched, $changedCount, $json, $runId);
    $stmt->execute();
    $stmt->close();

    return gpdata_run_result($db, $runId);
}

/**
 * One recorded run, in API shape.
 *
 * @return array<string, mixed>
 */
function gpdata_run_result(mysqli $db, int $runId): array
{
    $stmt = $db->prepare(
        'SELECT id, trigger_source, status,'
        . ' DATE_FORMAT(started_at, "%Y-%m-%dT%H:%i:%sZ") AS started_at,'
        . ' DATE_FORMAT(finished_at, "%Y-%m-%dT%H:%i:%sZ") AS finished_at,'
        . ' feed_records, matched, changed, detail'
        . ' FROM gpdata_sync_run WHERE id = ?'
    );
    $stmt->bind_param('i', $runId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    $detail = json_decode((string) $row['detail'], true) ?: [];

    return [
        'run_id' => (int) $row['id'],
        'trigger' => $row['trigger_source'],
        'status' => $row['status'],
        'started_at' => $row['started_at'],
        'finished_at' => $row['finished_at'],
        'feed_records' => $row['feed_records'] === null ? null : (int) $row['feed_records'],
        'matched' => $row['matched'] === null ? null : (int) $row['matched'],
        'changed' => $detail['changed'] ?? [],
        'not_in_feed' => $detail['not_in_feed'] ?? [],
        'in_feed_without_id' => $detail['in_feed_without_id'] ?? [],
        'ambiguous' => $detail['ambiguous'] ?? [],
        'reason' => $detail['reason'] ?? null,
    ];
}
