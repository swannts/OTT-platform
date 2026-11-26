import assert from 'node:assert/strict';
import { spawn } from 'node:child_process';
import { once } from 'node:events';
import { cp, mkdtemp, rm, symlink } from 'node:fs/promises';
import { createServer } from 'node:http';
import { tmpdir } from 'node:os';
import { join } from 'node:path';
import { setTimeout as delay } from 'node:timers/promises';
import { fileURLToPath } from 'node:url';

const appDir = fileURLToPath(new URL('../', import.meta.url));
const endpoints = {
  '/api/v3/banner-data': { slider: [] },
  '/api/v3/dashboard-detail': {
    latest_movie: [{ id: 71, name: 'Homepage cache test movie', type: 'movie' }],
  },
  '/api/v3/livetv-dashboard': {
    slider: [{ id: 83, name: 'Homepage cache test live channel' }],
    category_data: [],
  },
};

let failLiveTv = true;
const requestCounts = Object.fromEntries(Object.keys(endpoints).map((path) => [path, 0]));
const backend = createServer((request, response) => {
  const path = new URL(request.url, 'http://127.0.0.1').pathname;
  if (Object.hasOwn(requestCounts, path)) requestCounts[path] += 1;

  response.setHeader('Content-Type', 'application/json');
  if (path === '/api/v3/livetv-dashboard' && failLiveTv) {
    response.writeHead(503).end(JSON.stringify({ status: false, message: 'Fixture outage' }));
    return;
  }
  response.end(JSON.stringify({ status: true, data: endpoints[path] ?? [] }));
});

const isolatedApp = await mkdtemp(join(tmpdir(), 'ott-home-cache-'));
let app;
let output = '';

try {
  await cp(join(appDir, '.next'), join(isolatedApp, '.next'), {
    recursive: true,
    filter: (path) => path !== join(appDir, '.next', 'cache'),
  });
  for (const name of ['node_modules', 'next.config.ts', 'package.json']) {
    await symlink(join(appDir, name), join(isolatedApp, name));
  }

  backend.listen(0, '127.0.0.1');
  await once(backend, 'listening');
  app = spawn(process.execPath, ['node_modules/next/dist/bin/next', 'start', '-H', '127.0.0.1', '-p', '0'], {
    cwd: isolatedApp,
    env: {
      ...process.env,
      INTERNAL_API_URL: 'http://127.0.0.1:' + backend.address().port + '/api',
      NEXT_TELEMETRY_DISABLED: '1',
    },
  });
  app.stdout.on('data', (data) => { output += data; });
  app.stderr.on('data', (data) => { output += data; });
  let origin;
  for (let attempt = 0; attempt < 100; attempt += 1) {
    origin = output.match(/http:\/\/127\.0\.0\.1:\d+/)?.[0];
    if (origin && /Ready in/.test(output)) break;
    if (app.exitCode !== null) throw new Error(output);
    await delay(100);
  }
  assert.ok(origin, output);

  async function fetchHome() {
    const response = await fetch(origin + '/', { signal: AbortSignal.timeout(10_000) });
    assert.equal(response.status, 200);
    return response.text();
  }

  const partialHome = await fetchHome();
  assert.match(partialHome, /Homepage cache test movie/);
  assert.doesNotMatch(partialHome, /Homepage cache test live channel/);
  assert.deepEqual(Object.values(requestCounts), [1, 1, 1]);

  failLiveTv = false;
  assert.match(await fetchHome(), /Homepage cache test live channel/);
  assert.deepEqual(Object.values(requestCounts), [2, 2, 2]);

  assert.match(await fetchHome(), /Homepage cache test live channel/);
  assert.deepEqual(Object.values(requestCounts), [2, 2, 2]);
  console.log('Homepage recovered after a partial outage and cached the healthy response.');
} finally {
  if (app && app.exitCode === null) {
    app.kill();
    await once(app, 'exit');
  }
  backend.close();
  await rm(isolatedApp, { recursive: true, force: true });
}
