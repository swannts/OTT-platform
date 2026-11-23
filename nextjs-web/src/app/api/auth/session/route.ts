import type { NextRequest } from 'next/server';
import { NextResponse } from 'next/server';

export async function DELETE(request: NextRequest): Promise<NextResponse> {
  const origin = request.headers.get('origin');
  if (origin && origin !== request.nextUrl.origin) {
    return NextResponse.json({ message: 'Origin is not allowed.' }, { status: 403 });
  }

  const response = NextResponse.json({ ok: true });
  response.cookies.set('noor_session', '', {
    httpOnly: true,
    secure: request.nextUrl.protocol === 'https:',
    sameSite: 'lax',
    path: '/',
    maxAge: 0,
  });
  return response;
}
