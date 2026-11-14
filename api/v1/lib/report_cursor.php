<?php

declare(strict_types=1);

/**
 * Opaque paging cursors for GET /reports.php (Issue #67).
 *
 * A cursor holds three numbers: the snapshot id (the highest report id
 * when page 1 was served), plus the observation time (Unix seconds) and id
 * of the previous page's last row. Clients must treat it as opaque. The
 * leading "1" is a format version, so the encoding can change later
 * without breaking cursors already handed out.
 */

function api_encode_report_cursor(int $snapshotId, string $observationTime, int $id): string
{
    $raw = sprintf('1:%d:%d:%d', $snapshotId, (int) strtotime($observationTime), $id);

    return rtrim(strtr(base64_encode($raw), '+/', '-_'), '=');
}

/**
 * @return array{snapshot_id: int, observation_time: string, id: int}|null
 *         null when the cursor is malformed.
 */
function api_decode_report_cursor(string $cursor): ?array
{
    if (!preg_match('/^[A-Za-z0-9_-]{1,128}$/', $cursor)) {
        return null;
    }

    $raw = base64_decode(strtr($cursor, '-_', '+/'), true);
    if ($raw === false || !preg_match('/^1:(\d{1,18}):(\d{1,12}):(\d{1,18})$/', $raw, $m)) {
        return null;
    }

    return [
        'snapshot_id' => (int) $m[1],
        'observation_time' => gmdate('Y-m-d\TH:i:s\Z', (int) $m[2]),
        'id' => (int) $m[3],
    ];
}
