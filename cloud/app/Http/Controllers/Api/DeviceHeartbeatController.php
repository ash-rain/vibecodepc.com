<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\HeartbeatRequest;
use App\Http\Resources\DeviceHeartbeatResource;
use App\Services\DeviceTelemetryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DeviceHeartbeatController extends Controller
{
    public function __construct(
        private readonly DeviceTelemetryService $telemetry,
    ) {}

    public function store(HeartbeatRequest $request, string $uuid): JsonResponse
    {
        $device = $request->attributes->get('device');

        $heartbeat = $this->telemetry->processHeartbeat(
            $device,
            $request->validated(),
        );

        return response()->json([
            'heartbeat' => new DeviceHeartbeatResource($heartbeat),
            'config_version' => $device->config_version ?? 0,
            'message' => 'Heartbeat recorded',
        ], 201);
    }

    public function index(Request $request, string $uuid): JsonResponse
    {
        $device = $request->attributes->get('device');

        $heartbeats = $device->heartbeats()
            ->latest('created_at')
            ->limit(100)
            ->get();

        return response()->json([
            'heartbeats' => DeviceHeartbeatResource::collection($heartbeats),
        ]);
    }
}
