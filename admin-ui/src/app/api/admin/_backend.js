import { cookies } from 'next/headers';

const TOKEN_COOKIE = 'ott_admin_token';
const SAFE_METHODS = new Set(['GET', 'HEAD', 'OPTIONS']);

export function rejectCrossOriginMutation(request) {
  if (SAFE_METHODS.has(request.method.toUpperCase())) return null;

  const requestUrl = new URL(request.url);
  const host = request.headers.get('host');
  const protocol = request.headers.get('x-forwarded-proto')?.split(',')[0]?.trim() || requestUrl.protocol.slice(0, -1);
  const expectedOrigin = host ? new URL(`${protocol}://${host}`).origin : requestUrl.origin;
  const origin = request.headers.get('origin');
  let originMatches = false;
  try {
    originMatches = new URL(origin).origin === expectedOrigin;
  } catch {
    // Missing or malformed origins are rejected for mutating requests.
  }
  if (!originMatches) {
    return Response.json({ message: 'Cross-origin requests are not allowed.' }, { status: 403 });
  }

  const fetchSite = request.headers.get('sec-fetch-site');
  if (fetchSite && fetchSite !== 'same-origin') {
    return Response.json({ message: 'Cross-site requests are not allowed.' }, { status: 403 });
  }

  return null;
}

export function backendUrl(path) {
  const base = process.env.LARAVEL_API_URL;
  if (!base) throw new Error('LARAVEL_API_URL is not configured');
  return `${base.replace(/\/$/, '')}${path}`;
}

export async function backendRequest(path, options = {}) {
  const cookieStore = await cookies();
  const token = cookieStore.get(TOKEN_COOKIE)?.value;
  if (!token) return Response.json({ message: 'Unauthenticated.' }, { status: 401 });
  const response = await fetch(backendUrl(path), {
    ...options,
    headers: { Accept: 'application/json', Authorization: `Bearer ${token}`, ...options.headers },
    cache: 'no-store',
  });
  const contentType = response.headers.get('content-type') || '';
  if (contentType.includes('application/json')) {
    const body = await response.json().catch(() => ({}));
    return Response.json(body, { status: response.status });
  }
  const body = await response.arrayBuffer();
  const headers = new Headers();
  for (const name of ['content-type', 'content-disposition', 'cache-control']) {
    const value = response.headers.get(name);
    if (value) headers.set(name, value);
  }
  return new Response(body, { status: response.status, headers });
}

export { TOKEN_COOKIE };
