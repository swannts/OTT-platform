import type { NextRequest } from 'next/server';
import { NextResponse } from 'next/server';

const SESSION_COOKIE = 'noor_session';
const TOKEN_COOKIE_TTL = 60 * 60 * 24 * 30;

type JsonRecord = Record<string, unknown>;

function findToken(value: unknown, depth = 0): string | null {
  if (!value || typeof value !== 'object' || depth > 5) return null;
  const record = value as JsonRecord;
  for (const key of ['api_token', 'token']) {
    if (typeof record[key] === 'string' && record[key]) return record[key] as string;
  }
  for (const nested of Object.values(record)) {
    const token = findToken(nested, depth + 1);
    if (token) return token;
  }
  return null;
}

function stripTokens(value: unknown): unknown {
  if (Array.isArray(value)) return value.map(stripTokens);
  if (!value || typeof value !== 'object') return value;
  return Object.fromEntries(
    Object.entries(value as JsonRecord)
      .filter(([key]) => key !== 'api_token' && key !== 'token')
      .map(([key, nested]) => [key, stripTokens(nested)]),
  );
}

function clearSession(response: NextResponse, secure: boolean): void {
  response.cookies.set(SESSION_COOKIE, '', {
    httpOnly: true,
    secure,
    sameSite: 'lax',
    path: '/',
    maxAge: 0,
  });
}

async function forward(request: NextRequest, context: { params: Promise<{ path: string[] }> }): Promise<NextResponse> {
  const { path } = await context.params;
  if (path.length === 0 || path.some((segment) => !/^[A-Za-z0-9._~-]+$/.test(segment) || segment === '.' || segment === '..')) {
    return NextResponse.json({ message: 'Invalid API path.' }, { status: 400 });
  }
  const apiBase = process.env.INTERNAL_API_URL || process.env.NEXT_PUBLIC_API_URL || 'http://localhost:8000/api';
  const normalizedBase = apiBase.replace(/\/$/, '');
  const encodedPath = path.map((segment) => encodeURIComponent(segment)).join('/');
  const target = new URL(`${normalizedBase}/${encodedPath}${request.nextUrl.search}`);
  const origin = request.headers.get('origin');
  if (origin && origin !== request.nextUrl.origin) {
    return NextResponse.json({ message: 'Origin is not allowed.' }, { status: 403 });
  }
  const fetchSite = request.headers.get('sec-fetch-site');
  if (fetchSite && fetchSite !== 'same-origin') {
    return NextResponse.json({ message: 'Cross-site requests are not allowed.' }, { status: 403 });
  }

  const cookieToken = request.cookies.get(SESSION_COOKIE)?.value;
  const headers = new Headers({
    Accept: request.headers.get('accept') || 'application/json',
    'app-locale': request.headers.get('app-locale') || 'bn',
    'Accept-Language': request.headers.get('accept-language') || 'bn',
  });
  const contentType = request.headers.get('content-type');
  if (contentType) headers.set('content-type', contentType);
  if (cookieToken) headers.set('authorization', `Bearer ${cookieToken}`);

  let body: ArrayBuffer | undefined;
  if (!['GET', 'HEAD'].includes(request.method)) body = await request.arrayBuffer();

  let upstream: Response;
  try {
    upstream = await fetch(target, {
      method: request.method,
      headers,
      body,
      cache: 'no-store',
      signal: AbortSignal.timeout(25_000),
    });
  } catch {
    return NextResponse.json({ message: 'The service is temporarily unavailable.' }, { status: 502 });
  }

  const responseHeaders = new Headers({
    'cache-control': 'private, no-store, max-age=0',
    'content-type': upstream.headers.get('content-type') || 'application/json',
  });
  const routeName = path[0];
  if (routeName === 'login' || routeName === 'register') {
    const raw = await upstream.text();
    let payload: unknown = raw;
    try {
      payload = JSON.parse(raw);
    } catch {
      // Preserve an unexpected non-JSON authentication response.
    }
    const token = findToken(payload);
    const response = NextResponse.json(stripTokens(payload), { status: upstream.status, headers: responseHeaders });
    if (upstream.ok && token) {
      response.cookies.set(SESSION_COOKIE, token, {
        httpOnly: true,
        secure: request.nextUrl.protocol === 'https:',
        sameSite: 'lax',
        path: '/',
        maxAge: TOKEN_COOKIE_TTL,
      });
    }
    return response;
  }

  const response = new NextResponse(upstream.body, { status: upstream.status, headers: responseHeaders });

  if (upstream.status === 401 || routeName === 'logout') clearSession(response, request.nextUrl.protocol === 'https:');
  return response;
}

export const GET = forward;
export const POST = forward;
export const PUT = forward;
export const PATCH = forward;
export const DELETE = forward;
