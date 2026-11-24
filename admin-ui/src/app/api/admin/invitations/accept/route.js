import { backendUrl, rejectCrossOriginMutation } from '../../_backend';

export async function POST(request) {
  const originError = rejectCrossOriginMutation(request);
  if (originError) return originError;

  try {
    const response = await fetch(backendUrl('/api/admin/invitations/accept'), {
      method: 'POST',
      headers: { Accept: 'application/json', 'Content-Type': 'application/json' },
      body: await request.text(),
      cache: 'no-store',
    });
    const body = await response.json().catch(() => ({}));
    return Response.json(body, { status: response.status });
  } catch {
    return Response.json({ message: 'Unable to connect to the admin service.' }, { status: 502 });
  }
}
