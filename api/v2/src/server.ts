import { buildApp } from './app.js';
import { loadConfig } from './config.js';
import { createDatabase } from './db/index.js';

const config = loadConfig();
const app = await buildApp({ db: createDatabase(config.db), logger: true });

for (const signal of ['SIGINT', 'SIGTERM'] as const) {
  process.once(signal, () => {
    app.log.info({ signal }, 'shutting down');
    void app.close().then(() => process.exit(0));
  });
}

await app.listen({ host: '0.0.0.0', port: config.port });
