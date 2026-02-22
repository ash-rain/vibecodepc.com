<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\Device;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class VerifyDeviceOwnership
{
    public function handle(Request $request, Closure $next): Response
    {
        $uuid = $request->route('uuid');
        $device = Device::where('uuid', $uuid)->first();

        if (! $device) {
            return response()->json(['error' => 'Device not found'], 404);
        }

        if (! $device->isClaimed() || $device->user_id !== $request->user()?->id) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $request->attributes->set('device', $device);

        return $next($request);
    }
}
