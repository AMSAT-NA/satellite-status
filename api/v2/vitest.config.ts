import { defineConfig } from 'vitest/config';

export default defineConfig({
  test: {
    include: ['test/**/*.test.ts'],
    // Tests share one real MariaDB (see the api-v2 job in ci.yml), so run
    // files one at a time.
    fileParallelism: false,
  },
});
