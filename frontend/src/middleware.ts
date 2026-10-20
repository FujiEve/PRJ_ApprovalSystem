import { NextResponse, type NextRequest } from 'next/server';

// /api/* をバックエンドへプロキシし、サーバー側だけが持つ共有シークレットを付与する。
export function middleware(request: NextRequest) {
  const backendUrl = process.env.BACKEND_API_URL || 'http://localhost:8080';
  const destination = new URL(
    `${request.nextUrl.pathname}${request.nextUrl.search}`,
    backendUrl,
  );

  const headers = new Headers(request.headers);
  const secret = process.env.API_PROXY_SECRET;
  if (secret) {
    headers.set('X-Proxy-Secret', secret);
  } else {
    headers.delete('X-Proxy-Secret');
  }

  return NextResponse.rewrite(destination, { request: { headers } });
}

export const config = {
  matcher: '/api/:path*',
};
