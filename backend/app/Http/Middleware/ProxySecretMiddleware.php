<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * フロント(Next.js)のサーバー側プロキシだけが知る共有シークレットを検証し、
 * 直接APIを叩かれることを防ぐ。API_PROXY_SECRET 未設定時は検証しない(ローカル開発用)。
 */
class ProxySecretMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        $secret = (string) env('API_PROXY_SECRET', '');

        if ($secret === '' || $request->is('api/health')) {
            return $next($request);
        }

        if (! hash_equals($secret, (string) $request->header('X-Proxy-Secret', ''))) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        return $next($request);
    }
}
