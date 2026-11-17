import { sql } from 'kysely';
import { afterAll, describe, expect, it } from 'vitest';

import { createDatabase } from '../src/db/index.js';
import { testDbConfig } from './helpers.js';

describe('database connections', () => {
  const db = createDatabase(testDbConfig());

  afterAll(async () => {
    await db.destroy();
  });

  // Issue #24: every connection is pinned to UTC, like v1's.
  it('pins every connection to UTC', async () => {
    const result = await sql<{ tz: string }>`SELECT @@session.time_zone AS tz`.execute(db);

    expect(result.rows[0]?.tz).toBe('+00:00');
  });

  it('talks utf8mb4', async () => {
    const result = await sql<{ cs: string }>`SELECT @@session.character_set_client AS cs`.execute(db);

    expect(result.rows[0]?.cs).toBe('utf8mb4');
  });
});
