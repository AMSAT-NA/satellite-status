import type { FastifyPluginAsyncTypebox } from '@fastify/type-provider-typebox';
import type { FastifyReply } from 'fastify';
import { Type } from 'typebox';

import { API_VERSION } from '../version.js';

/**
 * /api front door: a small, machine-readable index of API versions (#38).
 * It moved here from api/v1/api-root.php once api-v2 was deployed, so the
 * newest API owns the list of versions.
 *
 * Deliberately NOT an alias for "the latest version". Clients hardcode a
 * versioned base URL (/api/v1/); an unversioned URL that silently starts
 * serving v2 is exactly the breakage versioning exists to prevent. This
 * document only points at versions: it never serves data, and nothing
 * here redirects.
 *
 * When a version's status changes, edit API_VERSIONS: exactly one entry is
 * 'current'; a replaced one becomes 'deprecated', then 'retired' when it's
 * gone. v2 is 'development' until it replaces v1.
 */
export const API_VERSIONS = [
  { version: 'v1', status: 'current', path: '/api/v1', docs: '/docs.php', openapi: '/openapi.php' },
  { version: 'v2', status: 'development', path: '/api/v2', docs: '/docs', openapi: '/docs/json' },
] as const;

const Status = Type.Union([
  Type.Literal('current'),
  Type.Literal('development'),
  Type.Literal('deprecated'),
  Type.Literal('retired'),
]);

const VersionsIndex = Type.Object(
  {
    data: Type.Object({
      name: Type.String(),
      versions: Type.Array(
        Type.Object({
          version: Type.String(),
          status: Status,
          url: Type.String(),
          docs: Type.String(),
          openapi: Type.String(),
        }),
      ),
      links: Type.Object({ self: Type.String(), changelog: Type.String() }),
    }),
  },
  { additionalProperties: false },
);

const V2Root = Type.Object(
  {
    data: Type.Object({
      name: Type.String(),
      version: Type.String(),
      status: Status,
      links: Type.Object({
        self: Type.String(),
        docs: Type.String(),
        openapi: Type.String(),
        health: Type.String(),
        versions: Type.String(),
      }),
    }),
    meta: Type.Object({ api_version: Type.String() }),
  },
  { additionalProperties: false },
);

const ALLOW = 'GET, HEAD, OPTIONS';

/** v1's JSON conventions for these two documents: open CORS, explicit 405s. */
function cors(reply: FastifyReply): FastifyReply {
  return reply.header('Access-Control-Allow-Origin', '*').header('Access-Control-Allow-Headers', 'Content-Type');
}

export interface VersionsOptions {
  /** Public base URL, scheme + host, no trailing slash (SITE_URL). */
  siteUrl: string;
}

/**
 * GET /api and /api/ (the versions index) and GET /api/v2 and /api/v2/ (v2's own
 * root, which the index advertises as v2's url). Registered without a
 * prefix: /api sits outside /api/v2. Not in the v2 OpenAPI document, which
 * covers v2's endpoints only.
 */
export const versionsRoutes: FastifyPluginAsyncTypebox<VersionsOptions> = async (app, { siteUrl }) => {
  const index = {
    data: {
      name: 'AMSAT Satellite Status API',
      versions: API_VERSIONS.map((v) => {
        const base = siteUrl + v.path;
        return { version: v.version, status: v.status, url: `${base}/`, docs: base + v.docs, openapi: base + v.openapi };
      }),
      links: {
        self: `${siteUrl}/api/`,
        changelog: 'https://github.com/AMSAT-NA/satellite-status/blob/main/CHANGELOG',
      },
    },
  };

  const v2 = API_VERSIONS.find((v) => v.version === 'v2')!;
  const v2Base = siteUrl + v2.path;
  const root = {
    data: {
      name: 'AMSAT Satellite Status API v2',
      version: 'v2',
      status: v2.status,
      links: {
        self: `${v2Base}/`,
        docs: v2Base + v2.docs,
        openapi: v2Base + v2.openapi,
        health: `${v2Base}/health`,
        versions: `${siteUrl}/api/`,
      },
    },
    meta: { api_version: API_VERSION },
  };

  const documents = [
    { urls: ['/api', '/api/'], body: index, schema: VersionsIndex },
    { urls: ['/api/v2', '/api/v2/'], body: root, schema: V2Root },
  ];

  for (const { urls, body, schema } of documents) {
    for (const url of urls) {
      app.get(url, { schema: { hide: true, response: { 200: schema } } }, async (_request, reply) =>
        cors(reply).send(body),
      );
      app.options(url, { schema: { hide: true } }, async (_request, reply) =>
        cors(reply).code(204).header('Allow', ALLOW).header('Access-Control-Allow-Methods', ALLOW).send(),
      );
      app.route({
        method: ['POST', 'PUT', 'PATCH', 'DELETE'],
        url,
        schema: { hide: true },
        handler: async (request, reply) =>
          cors(reply)
            .code(405)
            .header('Allow', ALLOW)
            .send({
              error: { code: 'method_not_allowed', message: `${request.method} isn't supported here; use GET.` },
            }),
      });
    }
  }
};
