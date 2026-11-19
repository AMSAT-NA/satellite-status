import { describe, expect, it } from 'vitest';

import { loadConfig } from '../src/config.js';

const base = { MYSQL_HOST: 'db', MYSQL_USER: 'u', MYSQL_PASSWORD: 'p', MYSQL_DATABASE: 'd' };

describe('SITE_URL', () => {
  it('is required', () => {
    expect(() => loadConfig(base)).toThrow(/SITE_URL is not set/);
  });

  it('drops a trailing slash', () => {
    expect(loadConfig({ ...base, SITE_URL: 'https://status.amsat.org/' }).siteUrl).toBe('https://status.amsat.org');
  });

  it('keeps a port (local dev)', () => {
    expect(loadConfig({ ...base, SITE_URL: 'http://localhost:8082' }).siteUrl).toBe('http://localhost:8082');
  });

  for (const bad of ['status.amsat.org', 'ftp://status.amsat.org', 'https://status.amsat.org/api', 'https://x.org/?a=1']) {
    it(`rejects ${bad}`, () => {
      expect(() => loadConfig({ ...base, SITE_URL: bad })).toThrow(/SITE_URL must be/);
    });
  }
});
