<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\TunnelRoutingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use VibecodePC\Common\Enums\DeviceStatus;

class DeviceDeregisterController extends Controller
{
    public function __construct(private TunnelRoutingService $routingService) {}

    public function __invoke(Request $request, string $uuid): JsonResponse
    {
        $device = $request->attributes->get('device');

        $this->routingService->deactivateDeviceRoutes($device);

        $device->update([
            'status' => DeviceStatus::Deactivated,
            'is_online' => false,
        ]);

        return response()->json([
            'message' => 'Device deregistered successfully.',
            'device_id' => $device->uuid,
            'status' => DeviceStatus::Deactivated->value,
        ]);
    }
}
