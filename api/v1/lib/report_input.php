<?php

declare(strict_types=1);

require_once __DIR__ . '/validation.php';

/**
 * NOTE: frontend/v1/submit.php duplicates this validation (callsign
 * format, grid square format, satellite-name match, future-time check)
 * so it can fail fast client-side before ever calling this API. If you
 * change validation rules here, check whether submit.php needs the same
 * change.
 *
 * @param array<string, mixed> $data
 * @return array<string, mixed>
 */
function api_validate_report_payload(array $data, ApiRepository $repository): array
{
    $name = trim((string) ($data['name'] ?? $data['satellite'] ?? ''));
    $report = api_normalize_report_status((string) ($data['report'] ?? $data['status'] ?? ''));
    $callsign = trim((string) ($data['callsign'] ?? ''));
    $gridSquare = trim((string) ($data['grid_square'] ?? ''));
    $reportedAt = trim((string) ($data['reported_at'] ?? ''));
    $observationTime = trim((string) ($data['observation_time'] ?? ''));

    if ($name === '') {
        api_error_response(422, 'missing_name', 'A satellite API name is required.');
    }

    $satellite = $repository->satelliteByApiName($name);

    if ($satellite === null) {
        api_error_response(422, 'unknown_satellite', 'The satellite name does not match a known API name.');
    }

    // Store the canonical API name. The lookup also matches a display name,
    // which would otherwise be stored as-is and never show up on the status
    // grid or in GET /reports.php (Issue #58).
    $name = (string) $satellite['html_element_name'];

    if (!in_array($report, api_status_values(), true)) {
        api_error_response(
            422,
            'invalid_report',
            'The report value is not supported.',
            ['allowed_values' => api_status_values()]
        );
    }

    $normalized = api_normalize_call_sign_and_grid($callsign, $gridSquare);
    $callsign = $normalized['callsign'];
    $gridSquare = $normalized['grid_square'];

    if ($callsign === '' || !api_call_sign_is_valid($callsign)) {
        api_error_response(422, 'invalid_callsign', 'A valid amateur radio callsign is required.');
    }

    if ($gridSquare !== '' && !api_grid_square_is_valid($gridSquare)) {
        api_error_response(422, 'invalid_grid_square', 'Grid square must be a valid Maidenhead locator.');
    }

    // Issue #45: observation_time is the preferred field (strict ISO 8601
    // with an explicit offset). reported_at and year/month/day/hour/minute
    // are deprecated aliases with their old lenient parsing, kept for the
    // life of /api/v1.
    if ($observationTime !== '') {
        $time = api_parse_observation_time($observationTime);

        if ($reportedAt !== '') {
            api_note_deprecated_input('reported_at');

            if (api_parse_reported_time($reportedAt, $data) !== $time) {
                api_error_response(
                    422,
                    'conflicting_observation_time',
                    'observation_time and reported_at were both sent and differ. Send only observation_time.'
                );
            }
        }

        if ($time > time()) {
            api_error_response(422, 'future_observation_time', 'observation_time cannot be in the future.');
        }
    } else {
        $time = api_parse_reported_time($reportedAt, $data);
        api_note_deprecated_input($reportedAt !== '' ? 'reported_at' : 'year/month/day/hour');

        if ($time > time()) {
            api_error_response(422, 'future_reported_at', 'The reported time cannot be in the future.');
        }
    }

    // submission_time is never read from $data, even if present -- it must
    // always be DB-default or an explicit NOW() at insert time, never
    // client-supplied. See ApiRepository::createReport().
    return [
        'name' => $name,
        'report' => $report,
        'callsign' => $callsign,
        'grid_square' => $gridSquare,
        'observation_time' => gmdate('Y-m-d H:i:s', $time),
        'reported_time' => gmdate('Y-m-d\TH:i:s\Z', $time),
    ];
}

/**
 * Parser for observation_time (Issue #45). The one strict rule: the offset
 * must be explicit. A timestamp with no offset is ambiguous (local time or
 * UTC?), so it's rejected rather than guessed. Everything else accepts the
 * forms common libraries actually emit, so app developers aren't tripped
 * up on formatting:
 *  - offset Z, +HH:MM, +HHMM or +HH (and the - forms); PHP DATE_ISO8601
 *    and Python %z write +HHMM
 *  - T or a space between date and time (RFC 3339 allows both); T/Z any case
 *  - seconds optional; fractional seconds accepted and dropped (times are
 *    stored to the second)
 * Impossible dates and times (Feb 30, hour 24) are rejected.
 */
function api_parse_observation_time(string $value): int
{
    $pattern = '/^(\d{4})-(\d{2})-(\d{2})[T ](\d{2}):(\d{2})(?::(\d{2})(?:\.\d+)?)?(?:Z|[+-](\d{2})(?::?(\d{2}))?)$/i';

    if (preg_match($pattern, $value, $m) !== 1
        || !checkdate((int) $m[2], (int) $m[3], (int) $m[1])
        || (int) $m[4] > 23 || (int) $m[5] > 59 || (int) ($m[6] ?? 0) > 59
        || (int) ($m[7] ?? 0) > 23 || (int) ($m[8] ?? 0) > 59
    ) {
        api_error_response(
            422,
            'invalid_observation_time',
            'observation_time must be an ISO 8601 date and time with an explicit UTC offset, for example 2026-05-23T08:30:00Z or 2026-05-23T10:30:00+02:00.'
        );
    }

    $time = strtotime($value);

    if ($time === false) {
        api_error_response(422, 'invalid_observation_time', 'observation_time could not be parsed.');
    }

    return $time;
}

/**
 * @param array<string, mixed> $data
 */
function api_parse_reported_time(string $reportedAt, array $data): int
{
    if ($reportedAt !== '') {
        $time = strtotime($reportedAt);

        if ($time === false) {
            api_error_response(422, 'invalid_reported_at', 'reported_at must be an ISO 8601 timestamp.');
        }

        return $time;
    }

    foreach (['year', 'month', 'day', 'hour'] as $key) {
        if (!array_key_exists($key, $data)) {
            api_error_response(
                422,
                'missing_reported_at',
                'Send observation_time (or the deprecated reported_at, or year, month, day and hour).'
            );
        }
    }

    $year = (int) $data['year'];
    $month = (int) $data['month'];
    $day = (int) $data['day'];
    $hour = (int) $data['hour'];
    $minute = (int) ($data['minute'] ?? 30);

    if (!checkdate($month, $day, $year) || $hour < 0 || $hour > 23 || $minute < 0 || $minute > 59) {
        api_error_response(422, 'invalid_reported_at', 'The supplied report date and time are invalid.');
    }

    return gmmktime($hour, $minute, 0, $month, $day, $year);
}
