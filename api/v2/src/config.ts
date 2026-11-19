/**
 * Runtime configuration from the environment. Uses the same MYSQL_*
 * and SITE_URL variables as the v1 API, so api-v2 reads the deploy `.env` unchanged.
 */
export interface Config {
  port: number;
  /** Public base URL, scheme + host, no trailing slash, like v1's SITE_URL. */
  siteUrl: string;
  db: {
    host: string;
    port: number;
    user: string;
    password: string;
    database: string;
  };
}

function required(env: NodeJS.ProcessEnv, name: string): string {
  const value = env[name];
  if (value === undefined || value === '') {
    throw new Error(`${name} is not set.`);
  }
  return value;
}

/** SITE_URL as v1 uses it: an http(s) origin; a trailing slash is dropped. */
function siteUrl(value: string): string {
  let url: URL;
  try {
    url = new URL(value);
  } catch {
    throw new Error(`SITE_URL must be a URL like https://status.amsat.org, got "${value}".`);
  }
  if ((url.protocol !== 'https:' && url.protocol !== 'http:') || url.pathname !== '/' || url.search || url.hash) {
    throw new Error(`SITE_URL must be scheme + host only (e.g. https://status.amsat.org), got "${value}".`);
  }
  return url.origin;
}

function port(value: string | undefined, fallback: number, name: string): number {
  if (value === undefined || value === '') {
    return fallback;
  }
  const parsed = Number(value);
  if (!Number.isInteger(parsed) || parsed < 1 || parsed > 65535) {
    throw new Error(`${name} must be a port number, got "${value}".`);
  }
  return parsed;
}

export function loadConfig(env: NodeJS.ProcessEnv = process.env): Config {
  return {
    port: port(env['PORT'], 3000, 'PORT'),
    siteUrl: siteUrl(required(env, 'SITE_URL')),
    db: {
      host: required(env, 'MYSQL_HOST'),
      port: port(env['MYSQL_PORT'], 3306, 'MYSQL_PORT'),
      user: required(env, 'MYSQL_USER'),
      password: required(env, 'MYSQL_PASSWORD'),
      database: required(env, 'MYSQL_DATABASE'),
    },
  };
}
