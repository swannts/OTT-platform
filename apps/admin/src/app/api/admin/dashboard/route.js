import { backendRequest } from '../_backend';

export async function GET() {
  try { return await backendRequest('/api/admin/dashboard'); }
  catch { return Response.json({ message: 'Unable to connect to the admin service.' }, { status: 502 }); }
}
