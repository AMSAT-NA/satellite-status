import { afterAll, beforeAll, describe, expect, it } from 'vitest';

import { buildApp } from '../src/app.js';
import { createDatabase } from '../src/db/index.js';
import { API_VERSION } from '../src/version.js';
import { TEST_SITE_URL, testApp, testDbConfig } from './helpers.js';

describe('GET /api/v2/health', () => {
  let app: Awaited<ReturnType<typeof testApp>>;

  beforeAll(async () => {
    app = await testApp();
  });

  afterAll(async () => {
    await app.close();
  });

  it('reports ok when the database is reachable', async () => {
    const response = await app.inject({ method: 'GET', url: '/api/v2/health' });

    expect(response.statusCode).toBe(200);
    expect(response.json()).toEqual({
      status: 'ok',
      database: 'ok',
      meta: { api_version: API_VERSION },
    });
  });

  it('answers unknown /api/v2 routes with a JSON 404', async () => {
    const response = await app.inject({ method: 'GET', url: '/api/v2/no-such-thing' });

    expect(response.statusCode).toBe(404);
    expect(response.json()).toMatchObject({ error: { code: 'not_found' } });
  });
});

describe('GET /api/v2/health without a database', () => {
  it('returns 503 and database: unavailable', async () => {
    // Nothing listens on port 1, so the connection fails fast.
    const db = createDatabase({ ...testDbConfig(), host: '127.0.0.1', port: 1 }, { connectTimeoutMs: 1000 });
    const app = await buildApp({ db, siteUrl: TEST_SITE_URL });

    try {
      const response = await app.inject({ method: 'GET', url: '/api/v2/health' });

      expect(response.statusCode).toBe(503);
      expect(response.json()).toMatchObject({ status: 'degraded', database: 'unavailable' });
    } finally {
      await app.close();
    }
  });
});
