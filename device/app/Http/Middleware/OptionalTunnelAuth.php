<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\TunnelConfig;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class OptionalTunnelAuth
{
    public function handle(Request $request, Closure $next): Response
    {
        $tunnelConfig = TunnelConfig::current();

        if (! $tunnelConfig || empty($tunnelConfig->tunnel_token_encrypted)) {
            return $next($request);
        }

        if (! $this->isTunnelRequest($request)) {
            return $next($request);
        }

        if ($request->session()->get('tunnel_authenticated')) {
            return $next($request);
        }

        if ($request->routeIs('tunnel.login')) {
            return $next($request);
        }

        $request->session()->put('tunnel_auth_intended_url', $request->fullUrl());

        return redirect()->route('tunnel.login');
    }

    private function isTunnelRequest(Request $request): bool
    {
        return $request->hasHeader('CF-Connecting-IP');
    }
}
