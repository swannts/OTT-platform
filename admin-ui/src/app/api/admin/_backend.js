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
  const body = await response.json().catch(() => ({}));
  return Response.json(body, { status: response.status });
}

export { TOKEN_COOKIE };
