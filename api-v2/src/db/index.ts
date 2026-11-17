import { CompiledQuery, Kysely, MysqlDialect } from 'kysely';
import { createPool } from 'mysql2';

import type { Config } from '../config.js';
import type { DB } from './types.js';

export type Database = Kysely<DB>;

/**
 * Kysely on a mysql2 connection pool.
 *
 * UTC everywhere (Issue #24): every new connection runs
 * `SET time_zone = '+00:00'` before any query, the same pin every v1
 * connection has. `timezone: 'Z'` makes mysql2 read and write JavaScript
 * Dates as UTC too. test/db.test.ts checks the session time zone.
 */
export function createDatabase(db: Config['db'], options: { connectTimeoutMs?: number } = {}): Database {
  const pool = createPool({
    host: db.host,
    port: db.port,
    user: db.user,
    password: db.password,
    database: db.database,
    timezone: 'Z',
    charset: 'utf8mb4',
    connectionLimit: 10,
    connectTimeout: options.connectTimeoutMs ?? 10_000,
  });

  return new Kysely<DB>({
    dialect: new MysqlDialect({
      pool,
      onCreateConnection: async (connection) => {
        await connection.executeQuery(CompiledQuery.raw("SET time_zone = '+00:00'"));
      },
    }),
  });
}
