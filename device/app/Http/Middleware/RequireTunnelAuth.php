<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * When the request arrives through a Cloudflare tunnel (indicated by the
 * CF-Connecting-IP header), require the user to authenticate with the
 * device admin password before accessing the dashboard / wizard.
 *
 * This does NOT apply to project routes served on their own subdomains.
 */
class RequireTunnelAuth
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $this->isTunnelRequest($request)) {
            return $next($request);
        }

        if ($request->session()->get('tunnel_authenticated')) {
            return $next($request);
        }

        // Allow the tunnel login page itself through
        if ($request->routeIs('tunnel.login')) {
            return $next($request);
        }

        $request->session()->put('tunnel_auth_intended_url', $request->fullUrl());

        return redirect()->route('tunnel.login');
    }

    /**
     * Detect whether the request came through a Cloudflare tunnel.
     *
     * Cloudflare always sets CF-Connecting-IP on proxied requests.
     * In local development this header is absent.
     */
    private function isTunnelRequest(Request $request): bool
    {
        return $request->hasHeader('CF-Connecting-IP');
    }
}
