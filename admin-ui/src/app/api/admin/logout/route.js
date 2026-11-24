import { backendRequest, rejectCrossOriginMutation, TOKEN_COOKIE } from '../_backend';
import { cookies } from 'next/headers';

export async function POST(request) {
  const originError = rejectCrossOriginMutation(request);
  if (originError) return originError;

  try { await backendRequest('/api/admin/logout', { method: 'POST' }); } catch { /* Always clear the local session. */ }
  const cookieStore = await cookies();
  cookieStore.set(TOKEN_COOKIE, '', { httpOnly: true, secure: process.env.NODE_ENV === 'production', sameSite: 'lax', path: '/', maxAge: 0 });
  return Response.json({ message: 'Signed out.' });
}
