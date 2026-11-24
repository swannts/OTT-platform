import { backendRequest } from '../_backend';
import { cookies } from 'next/headers';
import { TOKEN_COOKIE } from '../_backend';

export async function GET() {
  try {
    const response = await backendRequest('/api/admin/me');
    if (response.status === 401 || response.status === 403) {
      const cookieStore = await cookies();
      cookieStore.delete(TOKEN_COOKIE);
    }
    return response;
  }
  catch { return Response.json({ message: 'Unable to connect to the admin service.' }, { status: 502 }); }
}
