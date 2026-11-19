import { afterAll, beforeAll, describe, expect, it } from 'vitest';

import { API_VERSIONS } from '../src/routes/versions.js';
import { API_VERSION } from '../src/version.js';
import { TEST_SITE_URL, testApp } from './helpers.js';

/**
 * The /api versions index (#38), owned by api-v2 since it was deployed,
 * and v2's own root at /api/v2/. These ports PHPUnit's old ApiRootTest.
 * That every advertised link resolves through the real site is checked
 * after each deploy (cd.yml), since v1's links are served by another
 * container.
 */
describe('/api versions index', () => {
  let app: Awaited<ReturnType<typeof testApp>>;

  beforeAll(async () => {
    app = await testApp();
  });

  afterAll(async () => {
    await app.close();
  });

  for (const url of ['/api', '/api/']) {
    it(`GET ${url} lists the versions, with absolute links from SITE_URL`, async () => {
      const response = await app.inject({ method: 'GET', url });

      expect(response.statusCode).toBe(200);
      expect(response.headers['content-type']).toBe('application/json; charset=utf-8');
      expect(response.headers['access-control-allow-origin']).toBe('*');

      const { data } = response.json();
      expect(data.name).toBe('AMSAT Satellite Status API');
      expect(data.links.self).toBe(`${TEST_SITE_URL}/api/`);
      expect(data.versions).toEqual([
        {
          version: 'v1',
          status: 'current',
          url: `${TEST_SITE_URL}/api/v1/`,
          docs: `${TEST_SITE_URL}/api/v1/docs.php`,
          openapi: `${TEST_SITE_URL}/api/v1/openapi.php`,
        },
        {
          version: 'v2',
          status: 'development',
          url: `${TEST_SITE_URL}/api/v2/`,
          docs: `${TEST_SITE_URL}/api/v2/docs`,
          openapi: `${TEST_SITE_URL}/api/v2/docs/json`,
        },
      ]);
    });
  }

  it('has exactly one current version', () => {
    expect(API_VERSIONS.filter((v) => v.status === 'current')).toHaveLength(1);
  });

  it("resolves every link it advertises for v2 (this app's own)", async () => {
    const { data } = (await app.inject({ method: 'GET', url: '/api/' })).json();
    const v2 = data.versions.find((v: { version: string }) => v.version === 'v2');

    for (const rel of ['url', 'docs', 'openapi'] as const) {
      const path = new URL(v2[rel]).pathname;
      const response = await app.inject({ method: 'GET', url: path });
      expect([200, 302], `${rel} -> ${path}`).toContain(response.statusCode);
    }
  });

  it('is not an alias: unversioned data paths are 404', async () => {
    for (const url of ['/api/reports.php', '/api/catalog.php', '/api/health.php', '/api/health']) {
      const response = await app.inject({ method: 'GET', url });
      expect(response.statusCode, url).toBe(404);
    }
  });

  it('answers OPTIONS (CORS preflight) with 204 and the allowed methods', async () => {
    const response = await app.inject({ method: 'OPTIONS', url: '/api/' });

    expect(response.statusCode).toBe(204);
    expect(response.headers['allow']).toBe('GET, HEAD, OPTIONS');
    expect(response.headers['access-control-allow-origin']).toBe('*');
  });

  it('rejects other methods with 405 and an Allow header', async () => {
    for (const method of ['POST', 'PUT', 'PATCH', 'DELETE'] as const) {
      const response = await app.inject({ method, url: '/api/' });
      expect(response.statusCode, method).toBe(405);
      expect(response.headers['allow']).toBe('GET, HEAD, OPTIONS');
      expect(response.json()).toMatchObject({ error: { code: 'method_not_allowed' } });
    }
  });
});

describe('/api/v2/ root', () => {
  let app: Awaited<ReturnType<typeof testApp>>;

  beforeAll(async () => {
    app = await testApp();
  });

  afterAll(async () => {
    await app.close();
  });

  for (const url of ['/api/v2', '/api/v2/']) {
    it(`GET ${url} links to v2's docs, OpenAPI, health and the versions index`, async () => {
      const response = await app.inject({ method: 'GET', url });

      expect(response.statusCode).toBe(200);
      expect(response.json()).toEqual({
        data: {
          name: 'AMSAT Satellite Status API v2',
          version: 'v2',
          status: 'development',
          links: {
            self: `${TEST_SITE_URL}/api/v2/`,
            docs: `${TEST_SITE_URL}/api/v2/docs`,
            openapi: `${TEST_SITE_URL}/api/v2/docs/json`,
            health: `${TEST_SITE_URL}/api/v2/health`,
            versions: `${TEST_SITE_URL}/api/`,
          },
        },
        meta: { api_version: API_VERSION },
      });
    });
  }
});
