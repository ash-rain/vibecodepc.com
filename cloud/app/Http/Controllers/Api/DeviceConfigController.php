<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DeviceConfigController extends Controller
{
    public function show(Request $request, string $uuid): JsonResponse
    {
        $device = $request->attributes->get('device');

        return response()->json([
            'device_id' => $device->uuid,
            'config' => [
                'subdomain' => $device->user?->username,
                'tunnel_url' => $device->tunnel_url,
                'firmware_version' => $device->firmware_version,
                'heartbeat_interval_seconds' => 60,
            ],
        ]);
    }
}
