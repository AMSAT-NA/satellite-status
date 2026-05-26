<?php

declare(strict_types=1);

require_once __DIR__ . '/validation.php';

/**
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

    if ($name === '') {
        api_error_response(422, 'missing_name', 'A satellite API name is required.');
    }

    if ($repository->satelliteByApiName($name) === null) {
        api_error_response(422, 'unknown_satellite', 'The satellite name does not match a known API name.');
    }

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

    $time = api_parse_reported_time($reportedAt, $data);

    if ($time > time()) {
        api_error_response(422, 'future_reported_at', 'The reported time cannot be in the future.');
    }

    return [
        'name' => $name,
        'report' => $report,
        'callsign' => $callsign,
        'grid_square' => $gridSquare,
        'day' => gmdate('Y-m-d', $time),
        'hour' => (int) gmdate('G', $time),
        'period' => api_period_for_minute((int) gmdate('i', $time)),
        'reported_time' => gmdate('Y-m-d\TH:i:s\Z', $time),
    ];
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
                'Use reported_at or provide year, month, day, and hour fields.'
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

function api_period_for_minute(int $minute): int
{
    if ($minute <= 15) {
        return 0;
    }

    if ($minute <= 30) {
        return 1;
    }

    if ($minute <= 45) {
        return 2;
    }

    return 3;
}
