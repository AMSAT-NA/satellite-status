<?php

declare(strict_types=1);

use OpenApi\Attributes as OA;

#[OA\OpenApi(
    info: new OA\Info(
        title: 'AMSAT Satellite Status API',
        description: 'Public API for amateur satellite status reports.',
        version: '1.0.0'
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
    properties: [
        new OA\Property(
            property: 'error',
            properties: [
                new OA\Property(property: 'code', type: 'string', example: 'invalid_parameter'),
                new OA\Property(property: 'message', type: 'string'),
                new OA\Property(property: 'status', type: 'integer', example: 400),
            ],
            type: 'object'
        ),
    ],
    type: 'object'
)]
#[OA\Schema(
    schema: 'ReportCreate',
    required: ['name', 'report', 'callsign', 'reported_at'],
    properties: [
        new OA\Property(property: 'name', type: 'string', example: 'AO-91'),
        new OA\Property(
            property: 'report',
            type: 'string',
            enum: ['Heard', 'Telemetry Only', 'Not Heard', 'Crew Active'],
            example: 'Heard'
        ),
        new OA\Property(property: 'callsign', type: 'string', example: 'N0CALL'),
        new OA\Property(property: 'grid_square', type: 'string', example: 'EM48'),
        new OA\Property(property: 'reported_at', type: 'string', format: 'date-time'),
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
        new OA\Property(property: 'period', type: 'integer'),
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
                description: 'Exact satellite API name.',
                required: false,
                schema: new OA\Schema(type: 'string')
            ),
            new OA\Parameter(
                name: 'include_stats',
                in: 'query',
                description: 'Include report count and latest report timestamp.',
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
                    ],
                    type: 'object'
                )
            ),
            new OA\Response(response: 404, description: 'Satellite not found.'),
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
            new OA\Parameter(name: 'name', in: 'query', schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'hours', in: 'query', schema: new OA\Schema(type: 'integer', minimum: 1, maximum: 720)),
            new OA\Parameter(name: 'since', in: 'query', schema: new OA\Schema(type: 'string', format: 'date-time')),
            new OA\Parameter(name: 'limit', in: 'query', schema: new OA\Schema(type: 'integer', minimum: 1, maximum: 500)),
            new OA\Parameter(name: 'callsign', in: 'query', schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'grid_square', in: 'query', schema: new OA\Schema(type: 'string')),
            new OA\Parameter(
                name: 'status',
                in: 'query',
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
                    ],
                    type: 'object'
                )
            ),
            new OA\Response(response: 400, description: 'Invalid filter.'),
            new OA\Response(response: 404, description: 'Satellite not found.'),
        ]
    )]
    #[OA\Post(
        path: '/reports.php',
        operationId: 'createReport',
        summary: 'Submit a satellite status report.',
        tags: ['Reports'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(ref: '#/components/schemas/ReportCreate')
        ),
        responses: [
            new OA\Response(response: 201, description: 'Report created.'),
            new OA\Response(response: 422, description: 'Validation failed.'),
        ]
    )]
    public function reports(): void
    {
    }

    #[OA\Get(
        path: '/summary.php',
        operationId: 'getSummary',
        summary: 'Summarize report activity by satellite and report status.',
        tags: ['Reports'],
        parameters: [
            new OA\Parameter(name: 'hours', in: 'query', schema: new OA\Schema(type: 'integer', minimum: 1, maximum: 720)),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Summary response.'),
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
            new OA\Response(response: 200, description: 'Status values response.'),
        ]
    )]
    public function statuses(): void
    {
    }

    #[OA\Get(
        path: '/health.php',
        operationId: 'getHealth',
        summary: 'Check API and database availability.',
        tags: ['Metadata'],
        responses: [
            new OA\Response(response: 200, description: 'API and database are reachable.'),
            new OA\Response(response: 503, description: 'Database is unavailable.'),
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
            new OA\Response(response: 200, description: 'Plain JSON array of satellites.'),
        ]
    )]
    #[OA\Get(
        path: '/sat_info.php',
        operationId: 'getLegacySatInfo',
        summary: 'Legacy-compatible satellite report array.',
        tags: ['Legacy'],
        parameters: [
            new OA\Parameter(name: 'name', in: 'query', schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'hours', in: 'query', schema: new OA\Schema(type: 'integer', minimum: 1, maximum: 96)),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Plain JSON array of reports.'),
        ]
    )]
    public function legacy(): void
    {
    }
}
