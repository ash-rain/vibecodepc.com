<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class DeviceConfigController extends Controller
{
    public function show(Request $request, string $uuid): JsonResponse
    {
        $device = $request->attributes->get('device');

        $config = [
            'subdomain' => $device->user?->username,
            'tunnel_url' => $device->tunnel_url,
            'firmware_version' => $device->firmware_version,
            'heartbeat_interval_seconds' => 60,
            'config_version' => $device->config_version ?? 1,
        ];

        // When the cloud re-provisions a tunnel with a new ID, the device
        // needs a fresh token to reconnect. Deliver it once via the config
        // endpoint and clear the cache entry after pickup.
        $tokenKey = "tunnel-new-token:{$device->id}";
        $encryptedToken = Cache::get($tokenKey);

        if ($encryptedToken) {
            $config['tunnel_token'] = decrypt($encryptedToken);
            Cache::forget($tokenKey);
        }

        return response()->json([
            'device_id' => $device->uuid,
            'config' => $config,
        ]);
    }
}
