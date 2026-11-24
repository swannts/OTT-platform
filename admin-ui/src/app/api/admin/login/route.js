import { backendUrl, rejectCrossOriginMutation, TOKEN_COOKIE } from '../_backend';
import { cookies } from 'next/headers';

export async function POST(request) {
  const originError = rejectCrossOriginMutation(request);
  if (originError) return originError;

  try {
    const { email, password } = await request.json();
    const response = await fetch(backendUrl('/api/admin/login'), {
      method: 'POST',
      headers: { Accept: 'application/json', 'Content-Type': 'application/json' },
      body: JSON.stringify({ email, password }),
      cache: 'no-store',
    });
    const body = await response.json().catch(() => ({}));
    if (!response.ok) return Response.json(body, { status: response.status });
    cookies().set(TOKEN_COOKIE, body.token, {
      httpOnly: true,
      secure: process.env.NODE_ENV === 'production',
      sameSite: 'lax',
      path: '/',
      maxAge: 60 * 60 * 8,
    });
    return Response.json({ user: body.user });
  } catch {
    return Response.json({ message: 'Unable to connect to the admin service.' }, { status: 502 });
  }
}
