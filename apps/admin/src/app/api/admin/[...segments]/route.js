import { backendRequest, rejectCrossOriginMutation } from '../_backend';

async function proxy(request, context) {
  const originError = rejectCrossOriginMutation(request);
  if (originError) return originError;

  try {
    const { segments } = await context.params;
    const path = `/api/admin/${segments.map(encodeURIComponent).join('/')}${new URL(request.url).search}`;
    const options = { method: request.method };
    if (!['GET', 'HEAD'].includes(request.method)) {
      options.body = await request.arrayBuffer();
      options.headers = {
        'Content-Type': request.headers.get('content-type') || 'application/json',
      };
    }
    return await backendRequest(path, options);
  } catch {
    return Response.json({ message: 'Unable to connect to the admin service.' }, { status: 502 });
  }
}

export const GET = proxy;
export const POST = proxy;
export const PUT = proxy;
export const PATCH = proxy;
export const DELETE = proxy;
