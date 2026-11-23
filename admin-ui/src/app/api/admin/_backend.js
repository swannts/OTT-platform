import { cookies } from 'next/headers';

const TOKEN_COOKIE = 'ott_admin_token';

export function backendUrl(path) {
  const base = process.env.LARAVEL_API_URL;
  if (!base) throw new Error('LARAVEL_API_URL is not configured');
  return `${base.replace(/\/$/, '')}${path}`;
}

export async function backendRequest(path, options = {}) {
  const token = cookies().get(TOKEN_COOKIE)?.value;
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
