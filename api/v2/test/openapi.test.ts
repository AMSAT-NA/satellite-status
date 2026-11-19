import { afterAll, beforeAll, describe, expect, it } from 'vitest';

import { API_VERSION } from '../src/version.js';
import { testApp } from './helpers.js';

describe('OpenAPI document', () => {
  let app: Awaited<ReturnType<typeof testApp>>;

  beforeAll(async () => {
    app = await testApp();
  });

  afterAll(async () => {
    await app.close();
  });

  it('is served at /api/v2/docs/json and documents every route', async () => {
    const response = await app.inject({ method: 'GET', url: '/api/v2/docs/json' });

    expect(response.statusCode).toBe(200);
    const doc = response.json();
    expect(doc.openapi).toBe('3.1.0');
    expect(doc.info.version).toBe(API_VERSION);
    expect(Object.keys(doc.paths)).toEqual(['/api/v2/health']);
    expect(doc.paths['/api/v2/health'].get.responses).toHaveProperty('503');
  });

  it('serves the Swagger UI at /api/v2/docs', async () => {
    const response = await app.inject({ method: 'GET', url: '/api/v2/docs' });

    expect([200, 302]).toContain(response.statusCode);
  });
});
