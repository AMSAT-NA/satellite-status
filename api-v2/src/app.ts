import swagger from '@fastify/swagger';
import swaggerUi from '@fastify/swagger-ui';
import type { TypeBoxTypeProvider } from '@fastify/type-provider-typebox';
import Fastify, { type FastifyServerOptions } from 'fastify';

import type { Database } from './db/index.js';
import { healthRoutes } from './routes/health.js';
import { API_VERSION } from './version.js';

declare module 'fastify' {
  interface FastifyInstance {
    db: Database;
  }
}

/** Every api-v2 route lives under this prefix (v1 is under /api/v1). */
export const API_PREFIX = '/api/v2';

export interface AppOptions {
  db: Database;
  logger?: FastifyServerOptions['logger'];
}

/**
 * Builds the app without listening, so tests can call it with
 * app.inject() and server.ts can start it.
 */
export async function buildApp(options: AppOptions) {
  const app = Fastify({ logger: options.logger ?? false }).withTypeProvider<TypeBoxTypeProvider>();

  app.decorate('db', options.db);
  app.addHook('onClose', async (instance) => {
    await instance.db.destroy();
  });

  // OpenAPI is generated from the same route schemas that validate
  // requests and responses, so the docs can't drift from the code.
  await app.register(swagger, {
    openapi: {
      openapi: '3.1.0',
      info: {
        title: 'AMSAT Satellite Status API v2',
        version: API_VERSION,
        description: 'Version 2 of the AMSAT satellite status API. In development: v1 (/api/v1) is the stable API.',
      },
      tags: [{ name: 'Status', description: 'Service health' }],
    },
  });
  await app.register(swaggerUi, { routePrefix: `${API_PREFIX}/docs` });

  await app.register(healthRoutes, { prefix: API_PREFIX });

  app.setNotFoundHandler((request, reply) => {
    void reply.code(404).send({
      error: { code: 'not_found', message: `No route for ${request.method} ${request.url}.` },
    });
  });

  return app;
}
