import { buildApp } from '../src/app.js';
import { createDatabase } from '../src/db/index.js';

/**
 * The test database: the CI MariaDB service loaded with db/schema.sql,
 * reached through the same TEST_DB_* variables PHPUnit uses.
 */
export function testDbConfig() {
  const env = process.env;
  return {
    host: env['TEST_DB_HOST'] ?? '127.0.0.1',
    port: Number(env['TEST_DB_PORT'] ?? 3306),
    user: env['TEST_DB_USER'] ?? 'satstatus',
    password: env['TEST_DB_PASS'] ?? 'satstatus',
    database: env['TEST_DB_NAME'] ?? 'satstatus',
  };
}

export async function testApp() {
  return buildApp({ db: createDatabase(testDbConfig()) });
}
