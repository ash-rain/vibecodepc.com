<?php

declare(strict_types=1);

namespace App\Http\Traits;

use Illuminate\Http\Request;

trait DetectsTunnel
{
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
