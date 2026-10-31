<?php

declare(strict_types=1);

/**
 * The API contract version: the single source of truth (Issue #32).
 *
 * Sent as the X-AMSAT-API-Version header on every v1 response, shown in
 * the /api/v1/ discovery document and health.php, and used as the OpenAPI
 * document's info.version (openapi_spec.php requires this file).
 *
 * Format: MAJOR.MINOR, no patch number.
 * - MAJOR always equals the URL version (/api/v1 -> 1.x). A breaking
 *   change means a new /api/vN URL, not a bump here. (1.1 was the one
 *   exception: the last breaking change shipped within v1 -- Issue #24.)
 * - MINOR bumps for anything additive: new fields, endpoints or
 *   parameters, and deprecations.
 * - Bug fixes that don't change the response shape don't bump it. The
 *   X-AMSAT-API-Commit / X-AMSAT-API-Deployed-At headers identify the
 *   exact build.
 *
 * Note every bump in the CHANGELOG entry for the PR that makes it.
 */
const API_VERSION = '1.1';
