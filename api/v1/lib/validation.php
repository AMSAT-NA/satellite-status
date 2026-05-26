<?php

declare(strict_types=1);

/**
 * @return array<int, string>
 */
function api_status_values(): array
{
    return ['Heard', 'Telemetry Only', 'Not Heard', 'Crew Active'];
}

function api_normalize_report_status(string $status): string
{
    $status = trim($status);
    $aliases = [
        'active' => 'Heard',
        'heard' => 'Heard',
        'satellite active' => 'Heard',
        'telemetry' => 'Telemetry Only',
        'telemetry only' => 'Telemetry Only',
        'telemetry/beacon only' => 'Telemetry Only',
        'beacon' => 'Telemetry Only',
        'not heard' => 'Not Heard',
        'no signal' => 'Not Heard',
        'crew active' => 'Crew Active',
        'iss crew active' => 'Crew Active',
        'iss crew (voice) active' => 'Crew Active',
    ];

    $key = strtolower($status);

    return $aliases[$key] ?? $status;
}

function api_call_sign_component_is_valid(string $callSignComponent): bool
{
    if (strlen($callSignComponent) === 0) {
        return false;
    }

    $isAlNum = preg_match('/^[a-zA-Z0-9]+$/', $callSignComponent) === 1;
    $hasDigit = preg_match('/[0-9]/', $callSignComponent) === 1;
    $hasLetter = preg_match('/[a-zA-Z]/', $callSignComponent) === 1;
    $validSeparatorPosition = preg_match('/^\D\D\D/', $callSignComponent) !== 1
        || preg_match('/^onl/i', $callSignComponent) === 1;
    $usWithTwoDigits = preg_match('/^[kwKW]\D*\d\D*\d/', $callSignComponent) === 1
        || preg_match('/^[nN]\d\D*\d/', $callSignComponent) === 1
        || preg_match('/^[nN][a-km-zA-KM-Z]\D*\d\D*\d/', $callSignComponent) === 1;
    $pmWithMoreThanOneDigit = preg_match('/^pm\d\d+/i', $callSignComponent) === 1;
    $callTooLong = strlen($callSignComponent) > 10
        || (
            strlen($callSignComponent) > 6
            && !(
                preg_match('/^i\d{5}[a-zA-Z][a-zA-Z]$/', $callSignComponent) === 1
                || preg_match('/^nl\d{5}$/i', $callSignComponent) === 1
                || preg_match('/^pa\d{5}$/i', $callSignComponent) === 1
                || preg_match('/^onl\d{4,5}$/i', $callSignComponent) === 1
                || preg_match('/^oe\d{4,8}$/i', $callSignComponent) === 1
                || preg_match('/^vk\d[f][a-zA-Z]{3}$/i', $callSignComponent) === 1
            )
        );
    $callTooShort = strlen($callSignComponent) < 4;

    return $isAlNum
        && $hasDigit
        && $hasLetter
        && $validSeparatorPosition
        && !$usWithTwoDigits
        && !$pmWithMoreThanOneDigit
        && !$callTooLong
        && !$callTooShort;
}

function api_call_sign_is_valid(string $callSign): bool
{
    if (strlen($callSign) === 0) {
        return false;
    }

    if (preg_match('/[\\\\"~`=<>|\'_+.,!@#$%^&*(){}\[\]]/', $callSign) === 1) {
        return false;
    }

    $components = preg_split('/[-\/]/', $callSign) ?: [];

    foreach ($components as $component) {
        if (api_call_sign_component_is_valid($component)) {
            return true;
        }
    }

    return false;
}

function api_grid_square_is_valid(string $gridSquare): bool
{
    return preg_match('/^[A-Ra-r]{2}[0-9]{2}([a-xA-X]{2})?$/', $gridSquare) === 1;
}

function api_standardized_grid_square(string $gridSquare): string
{
    $result = '';

    for ($i = 0, $len = strlen($gridSquare); $i < $len; $i++) {
        $nextChar = $gridSquare[$i];
        if ($i < 2) {
            $nextChar = strtoupper($nextChar);
        } elseif ($i >= 4) {
            $nextChar = strtolower($nextChar);
        }
        $result .= $nextChar;
    }

    return $result;
}

function api_extract_grid_square_from_call_sign(string $callSign): string
{
    $components = preg_split('/[-\/]/', $callSign) ?: [];
    $lastComponent = end($components);

    if (is_string($lastComponent) && api_grid_square_is_valid($lastComponent)) {
        return api_standardized_grid_square($lastComponent);
    }

    return '';
}

/**
 * @return array{callsign: string, grid_square: string}
 */
function api_normalize_call_sign_and_grid(string $callSign, string $gridSquare): array
{
    $callSign = strtoupper(trim($callSign));
    $gridSquare = api_standardized_grid_square(trim($gridSquare));
    $extractedGrid = api_extract_grid_square_from_call_sign($callSign);

    if ($gridSquare === '' && $extractedGrid !== '') {
        $gridSquare = $extractedGrid;
        $callSign = preg_replace('/[-\/]?' . preg_quote($extractedGrid, '/') . '$/i', '', $callSign) ?? $callSign;
    }

    return [
        'callsign' => substr($callSign, 0, 14),
        'grid_square' => $gridSquare,
    ];
}
