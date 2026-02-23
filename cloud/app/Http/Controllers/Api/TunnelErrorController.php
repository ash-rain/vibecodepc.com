<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\ReprovisionTunnelJob;
use App\Models\TunnelRoute;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class TunnelErrorController extends Controller
{
    /**
     * Report a Cloudflare tunnel error observed by the browser or a CF Worker.
     *
     * This is a public, unauthenticated endpoint — rate-limited and validated
     * so it cannot be abused to trigger arbitrary re-provisioning.
     *
     * POST /api/tunnel-error
     * { "subdomain": "username", "error_code": 1033 }
     */
    public function __invoke(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'subdomain' => ['required', 'string', 'max:30', 'regex:/^[a-z0-9][a-z0-9-]*[a-z0-9]$/'],
            'error_code' => ['nullable', 'integer'],
        ]);

        $subdomain = $validated['subdomain'];
        $errorCode = $validated['error_code'] ?? null;

        // Only act on known CF tunnel error codes
        $tunnelErrorCodes = [1033, 1016, 1015];
        if ($errorCode !== null && ! in_array($errorCode, $tunnelErrorCodes, true)) {
            return response()->json(['status' => 'ignored', 'reason' => 'unrecognized error code']);
        }

        $route = TunnelRoute::query()
            ->active()
            ->where('subdomain', $subdomain)
            ->first();

        if (! $route?->device) {
            return response()->json(['status' => 'ignored', 'reason' => 'unknown subdomain']);
        }

        $device = $route->device;
        $flag = "tunnel-reprovisioning:{$device->id}";

        if (Cache::has($flag)) {
            return response()->json(['status' => 'already_recovering']);
        }

        // Deduplicate rapid-fire reports: ignore if we already received a
        // report for this device in the last 30 seconds.
        $reportKey = "tunnel-error-reported:{$device->id}";
        if (Cache::has($reportKey)) {
            return response()->json(['status' => 'already_reported']);
        }

        Cache::put($reportKey, true, 30);
        Cache::put($flag, true, 300);

        ReprovisionTunnelJob::dispatch($device->id);

        Log::warning('External tunnel error report received, re-provisioning dispatched', [
            'device_uuid' => $device->uuid,
            'subdomain' => $subdomain,
            'error_code' => $errorCode,
            'reporter_ip' => $request->ip(),
        ]);

        return response()->json(['status' => 'recovering']);
    }
}
