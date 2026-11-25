import assert from 'node:assert/strict';
import { spawn } from 'node:child_process';
import { createServer } from 'node:http';
import { once } from 'node:events';
import { existsSync, readFileSync, unlinkSync } from 'node:fs';
import { dirname, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';
import test from 'node:test';

const adminUiRoot = resolve(dirname(fileURLToPath(import.meta.url)), '..');
const nextBinary = resolve(adminUiRoot, 'node_modules/next/dist/bin/next');
const agentRulesPath = resolve(adminUiRoot, 'AGENTS.md');
const productionMode = process.env.NEXT_BFF_TEST_MODE === 'production';

function listen(server) {
  return new Promise((resolveListen, reject) => {
    server.once('error', reject);
    server.listen(0, '127.0.0.1', () => {
      server.removeListener('error', reject);
      resolveListen(server.address().port);
    });
  });
}

async function reservePort() {
  const server = createServer();
  const port = await listen(server);
  await new Promise((resolveClose, reject) => {
    server.close((error) => (error ? reject(error) : resolveClose()));
  });
  return port;
}

async function closeServer(server) {
  if (!server?.listening) return;
  await new Promise((resolveClose, reject) => {
    server.close((error) => (error ? reject(error) : resolveClose()));
  });
}

async function stopProcess(child) {
  if (!child || child.exitCode !== null) return;
  const exited = once(child, 'exit');
  child.kill('SIGTERM');
  const timeout = setTimeout(() => child.kill('SIGKILL'), 2_000);
  try {
    await exited;
  } finally {
    clearTimeout(timeout);
  }
}

function responseJson(response) {
  return response.json();
}

test('admin BFF keeps the Sanctum token server-side across login, session, and logout', async (t) => {
  assert.ok(existsSync(nextBinary), 'install admin-ui dependencies before running this test');

  const backendRequests = [];
  const backend = createServer(async (request, response) => {
    const chunks = [];
    for await (const chunk of request) chunks.push(chunk);
    const body = Buffer.concat(chunks).toString('utf8');
    const authorization = request.headers.authorization ?? '';
    backendRequests.push({ method: request.method, url: request.url, authorization });

    let status = 200;
    let payload;
    if (request.url === '/api/admin/login' && request.method === 'POST') {
      const credentials = JSON.parse(body || '{}');
      if (credentials.email !== 'admin@example.test' || credentials.password !== 'correct-password') {
        status = 422;
        payload = { message: 'Invalid credentials' };
      } else {
        payload = {
          token: 'test-admin-token',
          user: { id: 7, email: 'admin@example.test', permissions: ['admin.access'] },
        };
      }
    } else if (request.url === '/api/admin/me' && request.method === 'GET') {
      if (authorization !== 'Bearer test-admin-token') {
        status = 401;
        payload = { message: 'Unauthenticated' };
      } else {
        payload = { user: { id: 7, email: 'admin@example.test', permissions: ['admin.access'] } };
      }
    } else if (request.url === '/api/admin/logout' && request.method === 'POST') {
      if (authorization !== 'Bearer test-admin-token') {
        status = 401;
        payload = { message: 'Unauthenticated' };
      } else {
        payload = { message: 'Signed out' };
      }
    } else {
      status = 404;
      payload = { message: 'Not found' };
    }

    const encoded = Buffer.from(JSON.stringify(payload));
    response.writeHead(status, {
      'content-type': 'application/json',
      'content-length': encoded.length,
    });
    response.end(encoded);
  });

  const backendPort = await listen(backend);
  const nextPort = await reservePort();
  const origin = `http://localhost:${nextPort}`;
  const hadGeneratedAgentRules = existsSync(agentRulesPath);
  const next = spawn(
    process.execPath,
    [nextBinary, ...(productionMode ? ['start'] : ['dev', '--webpack']), '-p', String(nextPort)],
    {
      cwd: adminUiRoot,
      env: {
        ...process.env,
        NODE_ENV: productionMode ? 'production' : 'development',
        LARAVEL_API_URL: `http://127.0.0.1:${backendPort}`,
        NEXT_TELEMETRY_DISABLED: '1',
      },
      stdio: ['ignore', 'pipe', 'pipe'],
    }
  );

  let nextOutput = '';
  next.stdout.on('data', (chunk) => (nextOutput = `${nextOutput}${chunk}`.slice(-8_000)));
  next.stderr.on('data', (chunk) => (nextOutput = `${nextOutput}${chunk}`.slice(-8_000)));

  t.after(async () => {
    await stopProcess(next);
    await closeServer(backend);
    if (!hadGeneratedAgentRules && existsSync(agentRulesPath)) {
      const generated = readFileSync(agentRulesPath, 'utf8');
      if (generated.includes('BEGIN:nextjs-agent-rules')) unlinkSync(agentRulesPath);
    }
  });

  let ready = false;
  for (let attempt = 0; attempt < 90; attempt += 1) {
    if (next.exitCode !== null) break;
    try {
      const response = await fetch(`${origin}/api/admin/me/`, { signal: AbortSignal.timeout(2_000) });
      if (response.status === 401) {
        ready = true;
        break;
      }
    } catch {
      // Next.js may still be compiling the Route Handler.
    }
    await new Promise((resolveDelay) => setTimeout(resolveDelay, 1_000));
  }
  assert.ok(ready, `Next.js did not become ready.\n${nextOutput}`);

  const homeResponse = await fetch(`${origin}/`);
  assert.equal(homeResponse.status, 200, `admin homepage failed to render.\n${nextOutput}`);
  const signInPage = await fetch(`${origin}/login`);
  assert.equal(signInPage.status, 200, `admin sign-in page failed to render.\n${nextOutput}`);
  const legacySignInPage = await fetch(`${origin}/auth/jwt/sign-in/`, { redirect: 'manual' });
  assert.equal(legacySignInPage.status, 307);
  assert.equal(legacySignInPage.headers.get('location'), '/login/');

  const loginResponse = await fetch(`${origin}/api/admin/login/`, {
    method: 'POST',
    headers: { Origin: origin, 'Content-Type': 'application/json' },
    body: JSON.stringify({ email: 'admin@example.test', password: 'correct-password' }),
  });
  assert.equal(loginResponse.status, 200);
  const loginBody = await responseJson(loginResponse);
  const sessionCookie = loginResponse.headers.get('set-cookie') ?? '';
  assert.equal(loginBody.user.id, 7);
  assert.equal(Object.hasOwn(loginBody, 'token'), false);
  assert.match(sessionCookie, /ott_admin_token=test-admin-token/);
  assert.match(sessionCookie, /HttpOnly/i);
  assert.match(sessionCookie, /SameSite=lax/i);
  assert.equal(/;\s*Secure(?:;|$)/i.test(sessionCookie), productionMode);
  assert.equal(backendRequests.at(-1)?.authorization, '');

  const meResponse = await fetch(`${origin}/api/admin/me/`, {
    headers: { Cookie: 'ott_admin_token=test-admin-token' },
  });
  assert.equal(meResponse.status, 200);
  assert.equal((await responseJson(meResponse)).user.id, 7);
  assert.equal(backendRequests.at(-1)?.authorization, 'Bearer test-admin-token');

  const expiredResponse = await fetch(`${origin}/api/admin/me/`, {
    headers: { Cookie: 'ott_admin_token=expired-token' },
  });
  assert.equal(expiredResponse.status, 401);
  assert.match(expiredResponse.headers.get('set-cookie') ?? '', /ott_admin_token=/);
  assert.match(expiredResponse.headers.get('set-cookie') ?? '', /(?:Max-Age=0|Expires=Thu, 01 Jan 1970)/i);

  const logoutResponse = await fetch(`${origin}/api/admin/logout/`, {
    method: 'POST',
    headers: { Origin: origin, Cookie: 'ott_admin_token=test-admin-token' },
  });
  assert.equal(logoutResponse.status, 200);
  assert.equal(backendRequests.at(-1)?.url, '/api/admin/logout');
  assert.equal(backendRequests.at(-1)?.authorization, 'Bearer test-admin-token');
  assert.match(logoutResponse.headers.get('set-cookie') ?? '', /(?:Max-Age=0|Expires=Thu, 01 Jan 1970)/i);

  const requestsBeforeForgery = backendRequests.length;
  const forgedLoginResponse = await fetch(`${origin}/api/admin/login/`, {
    method: 'POST',
    headers: { Origin: 'http://evil.example', 'Content-Type': 'application/json' },
    body: JSON.stringify({ email: 'admin@example.test', password: 'correct-password' }),
  });
  assert.equal(forgedLoginResponse.status, 403);
  assert.equal(backendRequests.length, requestsBeforeForgery);
});
