import type { FastifyPluginAsyncTypebox } from '@fastify/type-provider-typebox';
import { sql } from 'kysely';
import { Type } from 'typebox';

import { API_VERSION } from '../version.js';

const HealthResponse = Type.Object(
  {
    status: Type.Union([Type.Literal('ok'), Type.Literal('degraded')], {
      description: 'ok when the API and its database are both reachable.',
    }),
    database: Type.Union([Type.Literal('ok'), Type.Literal('unavailable')]),
    meta: Type.Object({
      api_version: Type.String({ description: 'api-v2 version, MAJOR.MINOR.', examples: [API_VERSION] }),
    }),
  },
  { additionalProperties: false },
);

/**
 * GET /api/v2/health: liveness plus a database round trip. 200 when both
 * are fine, 503 when the database can't be reached, so a load balancer
 * or uptime check can act on the status code alone.
 */
export const healthRoutes: FastifyPluginAsyncTypebox = async (app) => {
  app.get(
    '/health',
    {
      schema: {
        summary: 'Health check',
        description: 'Reports whether the API is up and can reach its database.',
        tags: ['Status'],
        response: { 200: HealthResponse, 503: HealthResponse },
      },
    },
    async (_request, reply) => {
      let database: 'ok' | 'unavailable' = 'ok';
      try {
        await sql`SELECT 1`.execute(app.db);
      } catch (error) {
        app.log.warn({ err: error }, 'health: database check failed');
        database = 'unavailable';
      }

      const ok = database === 'ok';
      return reply.code(ok ? 200 : 503).send({
        status: ok ? 'ok' : 'degraded',
        database,
        meta: { api_version: API_VERSION },
      });
    },
  );
};
