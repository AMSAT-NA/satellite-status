<?php

declare(strict_types=1);

use OpenApi\Attributes as OA;

require_once __DIR__ . '/lib/version.php';

#[OA\OpenApi(
    info: new OA\Info(
        title: 'AMSAT Satellite Status API',
        description: 'Public API for amateur satellite status reports. '
            . 'Before building on it, read the usage guidelines (linked as Terms of Service): '
            . 'identify your client with a descriptive User-Agent, poll no more often than every 5 minutes, '
            . 'and submit only real observations made by the operator whose callsign the report carries. '
            . 'Code is licensed under Apache-2.0; report data is available under CC BY 4.0 with attribution.',
        termsOfService: 'https://status.amsat.org/api/v1/overview.php#usage',
        contact: new OA\Contact(
            name: 'AMSAT IT',
            url: 'https://github.com/AMSAT-NA/satellite-status/issues',
            email: 'IT@amsat.org'
        ),
        license: new OA\License(
            name: 'Apache-2.0',
            url: 'https://www.apache.org/licenses/LICENSE-2.0'
        ),
        version: API_VERSION
    ),
    servers: [
        new OA\Server(url: '/api/v1', description: 'Current host'),
    ],
    tags: [
        new OA\Tag(name: 'Catalog', description: 'Satellite catalog endpoints'),
        new OA\Tag(name: 'Reports', description: 'Satellite status report endpoints'),
        new OA\Tag(name: 'Metadata', description: 'API metadata and health endpoints'),
        new OA\Tag(name: 'Legacy', description: 'Compatibility endpoints for existing clients'),
    ]
)]
#[OA\Schema(
    schema: 'ErrorResponse',
    description: 'Error envelope used by every endpoint except the legacy satellites.php and sat_info.php. '
        . 'code is stable and meant for programs; message is for people and may change. New codes may be added. '
        . 'Codes by HTTP status -- 400: invalid_parameter (hours or limit is not an integer), invalid_since, '
        . 'invalid_grid_square, invalid_status, invalid_json. 404: satellite_not_found. '
        . '405: method_not_allowed (any endpoint; the Allow header lists the supported methods). '
        . '422: missing_name, unknown_satellite, invalid_report, invalid_callsign, invalid_grid_square, '
        . 'invalid_reported_at, missing_reported_at, future_reported_at. '
        . '500: query_failed, query_prepare_failed, write_failed. '
        . '503: database_unavailable (any endpoint that reads the database), database_unhealthy (health.php), '
        . 'openapi_spec_missing (openapi.php and the /api/v1/ index).',
    properties: [
        new OA\Property(
            property: 'error',
            properties: [
                new OA\Property(property: 'code', type: 'string', example: 'invalid_parameter'),
                new OA\Property(property: 'message', type: 'string'),
                new OA\Property(property: 'status', type: 'integer', example: 400),
                new OA\Property(
                    property: 'details',
                    description: 'Present on some errors only: the rejected parameter and value for invalid_parameter, '
                        . 'or allowed_values for invalid_report.',
                    type: 'object',
                    additionalProperties: true
                ),
            ],
            type: 'object'
        ),
    ],
    type: 'object'
)]
#[OA\Schema(
    schema: 'ReportCreate',
    description: 'New clients should send name, report, callsign, grid_square and reported_at. '
        . 'The other fields are accepted for compatibility with older clients and the v1 submit form.',
    required: ['name', 'report', 'callsign', 'reported_at'],
    properties: [
        new OA\Property(property: 'name', description: 'Satellite API name, as listed by /catalog.php.', type: 'string', example: 'AO-91'),
        new OA\Property(
            property: 'report',
            description: 'Report value. Matched case-insensitively, and these aliases are also accepted: '
                . 'active, satellite active (Heard); telemetry, beacon, telemetry/beacon only (Telemetry Only); '
                . 'no signal (Not Heard); iss crew active, iss crew (voice) active (Crew Active).',
            type: 'string',
            enum: ['Heard', 'Telemetry Only', 'Not Heard', 'Crew Active'],
            example: 'Heard'
        ),
        new OA\Property(
            property: 'callsign',
            description: 'Amateur radio callsign, uppercased and truncated to 14 characters. If grid_square is empty '
                . 'and the callsign ends in a grid square after - or / (for example N0CALL/EM48), the grid is taken from the callsign.',
            type: 'string',
            example: 'N0CALL'
        ),
        new OA\Property(property: 'grid_square', description: 'Optional Maidenhead locator (4 or 6 characters).', type: 'string', example: 'EM48'),
        new OA\Property(
            property: 'reported_at',
            description: 'Time of the observation (not of submission). Any ISO 8601 timestamp is parsed; send UTC. Must not be in the future.',
            type: 'string',
            format: 'date-time'
        ),
        new OA\Property(property: 'satellite', description: 'Alias for name. Used only when name is absent.', type: 'string'),
        new OA\Property(property: 'status', description: 'Alias for report. Used only when report is absent.', type: 'string'),
        new OA\Property(
            property: 'year',
            description: 'Legacy time fields, used only when reported_at is absent: year, month, day and hour (UTC) '
                . 'are then all required.',
            type: 'integer'
        ),
        new OA\Property(property: 'month', description: 'Legacy; see year.', type: 'integer'),
        new OA\Property(property: 'day', description: 'Legacy; see year.', type: 'integer'),
        new OA\Property(property: 'hour', description: 'Legacy; see year. 0-23, UTC.', type: 'integer'),
        new OA\Property(
            property: 'minute',
            description: 'Legacy; see year. 0-59. Defaults to 30 if omitted, so legacy clients should always send it.',
            type: 'integer'
        ),
    ],
    type: 'object'
)]
#[OA\Schema(
    schema: 'Report',
    properties: [
        new OA\Property(property: 'id', type: 'integer'),
        new OA\Property(property: 'name', type: 'string', example: 'AO-91'),
        new OA\Property(property: 'satellite_display_name', type: 'string', nullable: true),
        new OA\Property(property: 'reported_time', type: 'string', format: 'date-time'),
        new OA\Property(property: 'callsign', type: 'string'),
        new OA\Property(property: 'report', type: 'string'),
        new OA\Property(property: 'grid_square', type: 'string', nullable: true),
    ],
    type: 'object'
)]
#[OA\Schema(
    schema: 'Satellite',
    properties: [
        new OA\Property(property: 'id', type: 'integer'),
        new OA\Property(property: 'name', type: 'string', example: 'AO-91'),
        new OA\Property(property: 'display_name', type: 'string', example: 'AO-91 Mode U/v FM'),
        new OA\Property(property: 'website', type: 'string'),
        new OA\Property(
            property: 'links',
            properties: [
                new OA\Property(property: 'self', type: 'string', format: 'uri'),
                new OA\Property(property: 'reports', type: 'string', format: 'uri'),
            ],
            type: 'object'
        ),
        new OA\Property(
            property: 'report_count',
            description: 'Only with include_stats=true. All stored reports for this satellite, all time, '
                . 'including corrected ones.',
            type: 'integer'
        ),
        new OA\Property(
            property: 'latest_reported_time',
            description: 'Only with include_stats=true. Observation time of the newest report; null if there are none.',
            type: 'string',
            format: 'date-time',
            nullable: true
        ),
    ],
    type: 'object'
)]
#[OA\Schema(
    schema: 'SummaryEntry',
    properties: [
        new OA\Property(property: 'name', type: 'string', example: 'AO-91'),
        new OA\Property(property: 'satellite_display_name', type: 'string', nullable: true),
        new OA\Property(property: 'report', type: 'string', enum: ['Heard', 'Telemetry Only', 'Not Heard', 'Crew Active']),
        new OA\Property(property: 'report_count', type: 'integer'),
        new OA\Property(property: 'latest_reported_time', type: 'string', format: 'date-time'),
    ],
    type: 'object'
)]
#[OA\Schema(
    schema: 'StatusValue',
    properties: [
        new OA\Property(property: 'value', type: 'string', enum: ['Heard', 'Telemetry Only', 'Not Heard', 'Crew Active']),
        new OA\Property(property: 'label', type: 'string', example: 'Satellite active'),
    ],
    type: 'object'
)]
#[OA\Schema(
    schema: 'LegacySatellite',
    properties: [
        new OA\Property(property: 'id', type: 'integer'),
        new OA\Property(property: 'name', description: 'Display name.', type: 'string', example: 'AO-91 Mode U/v FM'),
        new OA\Property(property: 'html_element_name', description: 'API name.', type: 'string', example: 'AO-91'),
        new OA\Property(property: 'website', type: 'string'),
    ],
    type: 'object'
)]
#[OA\Schema(
    schema: 'LegacyReport',
    properties: [
        new OA\Property(property: 'name', type: 'string', example: 'AO-91'),
        new OA\Property(property: 'reported_time', type: 'string', format: 'date-time'),
        new OA\Property(property: 'callsign', type: 'string'),
        new OA\Property(property: 'report', type: 'string'),
        new OA\Property(property: 'grid_square', type: 'string', nullable: true),
    ],
    type: 'object'
)]
final class ApiV1OpenApiSpec
{
    #[OA\Get(
        path: '/catalog.php',
        operationId: 'getCatalog',
        summary: 'List satellites with links and optional report statistics.',
        tags: ['Catalog'],
        parameters: [
            new OA\Parameter(
                name: 'name',
                in: 'query',
                description: 'Exact satellite API name or display name. No match returns 404 satellite_not_found.',
                required: false,
                schema: new OA\Schema(type: 'string')
            ),
            new OA\Parameter(
                name: 'include_stats',
                in: 'query',
                description: 'Add report_count and latest_reported_time to each satellite. '
                    . 'Accepts 1, true, yes or on; anything else is false.',
                required: false,
                schema: new OA\Schema(type: 'boolean')
            ),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Catalog response.',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(
                            property: 'data',
                            type: 'array',
                            items: new OA\Items(ref: '#/components/schemas/Satellite')
                        ),
                        new OA\Property(
                            property: 'meta',
                            properties: [new OA\Property(property: 'count', type: 'integer')],
                            type: 'object'
                        ),
                    ],
                    type: 'object'
                )
            ),
            new OA\Response(response: 404, description: 'Satellite not found (satellite_not_found).', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
        ]
    )]
    public function catalog(): void
    {
    }

    #[OA\Get(
        path: '/reports.php',
        operationId: 'getReports',
        summary: 'Search recent satellite reports.',
        tags: ['Reports'],
        parameters: [
            new OA\Parameter(
                name: 'name',
                in: 'query',
                description: 'Exact satellite API name. An unknown name returns 404 satellite_not_found.',
                schema: new OA\Schema(type: 'string')
            ),
            new OA\Parameter(
                name: 'hours',
                in: 'query',
                description: 'Rolling UTC window, in hours. Ignored when since is given. Out-of-range values are clamped to the nearest bound, not rejected; a non-integer returns 400 invalid_parameter.',
                schema: new OA\Schema(type: 'integer', default: 72, maximum: 720, minimum: 1)
            ),
            new OA\Parameter(
                name: 'since',
                in: 'query',
                description: 'Return reports observed at or after this time (ISO 8601). Overrides hours. '
                    . 'An unparseable value returns 400 invalid_since.',
                schema: new OA\Schema(type: 'string', format: 'date-time')
            ),
            new OA\Parameter(
                name: 'limit',
                in: 'query',
                description: 'Maximum number of reports, newest first. Out-of-range values are clamped to the nearest bound, not rejected; a non-integer returns 400 invalid_parameter.',
                schema: new OA\Schema(type: 'integer', default: 100, maximum: 500, minimum: 1)
            ),
            new OA\Parameter(
                name: 'callsign',
                in: 'query',
                description: 'Exact callsign, case-insensitive.',
                schema: new OA\Schema(type: 'string')
            ),
            new OA\Parameter(
                name: 'grid_square',
                in: 'query',
                description: 'Exact Maidenhead locator, case-insensitive. A 4-character grid does not match 6-character reports. '
                    . 'An invalid locator returns 400 invalid_grid_square.',
                schema: new OA\Schema(type: 'string')
            ),
            new OA\Parameter(
                name: 'status',
                in: 'query',
                description: 'Report value. Accepts the same aliases as POST /reports.php. '
                    . 'An unsupported value returns 400 invalid_status.',
                schema: new OA\Schema(type: 'string', enum: ['Heard', 'Telemetry Only', 'Not Heard', 'Crew Active'])
            ),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Report search response.',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(
                            property: 'data',
                            type: 'array',
                            items: new OA\Items(ref: '#/components/schemas/Report')
                        ),
                        new OA\Property(
                            property: 'meta',
                            properties: [
                                new OA\Property(property: 'count', type: 'integer'),
                                new OA\Property(property: 'limit', description: 'The limit applied, after clamping.', type: 'integer'),
                                new OA\Property(property: 'since', description: 'The lower bound applied, normalized to UTC.', type: 'string', format: 'date-time'),
                                new OA\Property(
                                    property: 'hours',
                                    description: 'The hours value after clamping (default 72). Still present when since was given, but not applied.',
                                    type: 'integer'
                                ),
                            ],
                            type: 'object'
                        ),
                    ],
                    type: 'object'
                )
            ),
            new OA\Response(response: 400, description: 'Invalid filter (invalid_parameter, invalid_since, invalid_grid_square or invalid_status).', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
            new OA\Response(response: 404, description: 'Satellite not found (satellite_not_found).', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
        ]
    )]
    #[OA\Post(
        path: '/reports.php',
        operationId: 'createReport',
        summary: 'Submit a satellite status report.',
        tags: ['Reports'],
        requestBody: new OA\RequestBody(
            description: 'Send JSON with Content-Type: application/json, or form data (application/x-www-form-urlencoded).',
            required: true,
            content: [
                new OA\JsonContent(ref: '#/components/schemas/ReportCreate'),
                new OA\MediaType(
                    mediaType: 'application/x-www-form-urlencoded',
                    schema: new OA\Schema(ref: '#/components/schemas/ReportCreate')
                ),
            ]
        ),
        responses: [
            new OA\Response(
                response: 201,
                description: 'Report created.',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(
                            property: 'data',
                            type: 'object',
                            properties: [
                                new OA\Property(property: 'id', type: 'integer'),
                                new OA\Property(property: 'name', type: 'string'),
                                new OA\Property(property: 'reported_time', type: 'string', format: 'date-time'),
                                new OA\Property(property: 'callsign', type: 'string'),
                                new OA\Property(property: 'report', type: 'string'),
                                new OA\Property(property: 'grid_square', type: 'string'),
                                new OA\Property(
                                    property: 'correction',
                                    type: 'boolean',
                                    description: 'True if this report corrects an earlier one: the same callsign already reported this satellite in the same 15-minute UTC period (:00-:14, :15-:29, :30-:44, :45-:59). The earlier report is kept but no longer counted or returned. False if this is the first report from this callsign for that period.'
                                ),
                            ]
                        ),
                        new OA\Property(
                            property: 'links',
                            properties: [
                                new OA\Property(property: 'satellite_reports', type: 'string', format: 'uri'),
                            ],
                            type: 'object'
                        ),
                    ],
                    type: 'object'
                )
            ),
            new OA\Response(response: 400, description: 'Request body is not valid JSON (invalid_json).', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
            new OA\Response(response: 422, description: 'Validation failed (missing_name, unknown_satellite, invalid_report, invalid_callsign, invalid_grid_square, invalid_reported_at, missing_reported_at or future_reported_at).', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
        ]
    )]
    public function reports(): void
    {
    }

    #[OA\Get(
        path: '/summary.php',
        operationId: 'getSummary',
        summary: 'Summarize report activity by satellite and report status.',
        description: "Counts are per satellite and report value over a rolling UTC window. As in API 1.0, a resubmission from the same callsign for the same satellite and 15-minute UTC period corrects the earlier report: only the latest one is counted. Corrected reports are still stored, but are not counted here or returned by /reports.php.",
        tags: ['Reports'],
        parameters: [
            new OA\Parameter(
                name: 'hours',
                in: 'query',
                description: 'Rolling UTC window, in hours. Out-of-range values are clamped to the nearest bound, not rejected; a non-integer returns 400 invalid_parameter.',
                schema: new OA\Schema(type: 'integer', default: 24, maximum: 720, minimum: 1)
            ),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Summary response: one entry per satellite and report value, ordered by satellite name, then count.',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(
                            property: 'data',
                            type: 'array',
                            items: new OA\Items(ref: '#/components/schemas/SummaryEntry')
                        ),
                        new OA\Property(
                            property: 'meta',
                            properties: [
                                new OA\Property(property: 'hours', description: 'The window applied, after clamping.', type: 'integer'),
                                new OA\Property(property: 'count', type: 'integer'),
                            ],
                            type: 'object'
                        ),
                    ],
                    type: 'object'
                )
            ),
            new OA\Response(response: 400, description: 'hours is not an integer (invalid_parameter).', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
        ]
    )]
    public function summary(): void
    {
    }

    #[OA\Get(
        path: '/statuses.php',
        operationId: 'getStatuses',
        summary: 'List canonical report status values.',
        tags: ['Metadata'],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Status values response.',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(
                            property: 'data',
                            type: 'array',
                            items: new OA\Items(ref: '#/components/schemas/StatusValue')
                        ),
                        new OA\Property(
                            property: 'meta',
                            properties: [new OA\Property(property: 'count', type: 'integer')],
                            type: 'object'
                        ),
                    ],
                    type: 'object'
                )
            ),
        ]
    )]
    public function statuses(): void
    {
    }

    #[OA\Get(
        path: '/health.php',
        operationId: 'getHealth',
        summary: 'Check API and database availability, and data freshness.',
        description: 'Returns 200 whenever the database is reachable. status is "ok" when data is fresh, or "degraded" '
            . 'when there are no reports (reason no_reports) or the newest report is older than '
            . 'data_freshness.stale_after_seconds (reason stale_data). Freshness is the newest report\'s receive time, '
            . 'or its observation time for reports imported before receive times were recorded.',
        tags: ['Metadata'],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Database reachable; status is "ok" or "degraded".',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(
                            property: 'data',
                            properties: [
                                new OA\Property(property: 'status', type: 'string', enum: ['ok', 'degraded']),
                                new OA\Property(property: 'reason', type: 'string', enum: ['no_reports', 'stale_data'], description: 'Present only when status is "degraded".'),
                                new OA\Property(property: 'version', type: 'string', example: '1.2'),
                                new OA\Property(property: 'time', type: 'string', format: 'date-time'),
                                new OA\Property(
                                    property: 'data_freshness',
                                    properties: [
                                        new OA\Property(property: 'latest_report_time', type: 'string', format: 'date-time', nullable: true),
                                        new OA\Property(property: 'age_seconds', type: 'integer', nullable: true),
                                        new OA\Property(property: 'stale_after_seconds', type: 'integer', example: 86400),
                                    ],
                                    type: 'object'
                                ),
                            ],
                            type: 'object'
                        ),
                    ],
                    type: 'object'
                )
            ),
new OA\Response(response: 503, description: 'Database is unavailable (database_unavailable or database_unhealthy).', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
        ]
    )]
    public function health(): void
    {
    }

    #[OA\Get(
        path: '/satellites.php',
        operationId: 'getLegacySatellites',
        summary: 'Legacy-compatible satellite catalog array.',
        tags: ['Legacy'],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Plain JSON array of satellites.',
                content: new OA\JsonContent(type: 'array', items: new OA\Items(ref: '#/components/schemas/LegacySatellite'))
            ),
        ]
    )]
    #[OA\Get(
        path: '/sat_info.php',
        operationId: 'getLegacySatInfo',
        summary: 'Legacy-compatible satellite report array.',
        tags: ['Legacy'],
        parameters: [
            new OA\Parameter(
                name: 'name',
                in: 'query',
                description: 'Satellite API name. If missing or unknown, the response is an empty array.',
                schema: new OA\Schema(type: 'string')
            ),
            new OA\Parameter(
                name: 'hours',
                in: 'query',
                description: 'Rolling UTC window, in hours. Out-of-range values are clamped to the nearest bound; '
                    . 'a non-numeric value is ignored and the default is used.',
                schema: new OA\Schema(type: 'integer', default: 72, maximum: 96, minimum: 1)
            ),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Plain JSON array of up to 100 reports, newest first.',
                content: new OA\JsonContent(type: 'array', items: new OA\Items(ref: '#/components/schemas/LegacyReport'))
            ),
        ]
    )]
    public function legacy(): void
    {
    }
}
