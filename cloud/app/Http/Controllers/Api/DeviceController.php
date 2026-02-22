<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Exceptions\DeviceAlreadyClaimedException;
use App\Exceptions\DeviceNotFoundException;
use App\Http\Controllers\Controller;
use App\Http\Requests\RegisterDeviceRequest;
use App\Services\DeviceRegistryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use VibecodePC\Common\DTOs\DeviceInfo;

class DeviceController extends Controller
{
    public function __construct(
        private readonly DeviceRegistryService $registry,
    ) {}

    public function register(RegisterDeviceRequest $request): JsonResponse
    {
        $deviceInfo = new DeviceInfo(
            id: $request->validated('id'),
            hardwareSerial: $request->validated('hardware_serial'),
            manufacturedAt: now()->toIso8601String(),
            firmwareVersion: $request->validated('firmware_version'),
        );

        $device = $this->registry->registerDevice($deviceInfo);

        return response()->json([
            'uuid' => $device->uuid,
            'status' => $device->status->value,
        ], 201);
    }

    public function status(string $uuid): JsonResponse
    {
        try {
            $result = $this->registry->getDeviceStatus($uuid);

            return response()->json($result->toArray());
        } catch (DeviceNotFoundException) {
            return response()->json(['error' => 'Device not found'], 404);
        }
    }

    public function claim(Request $request, string $uuid): JsonResponse
    {
        try {
            $result = $this->registry->claimDevice(
                uuid: $uuid,
                user: $request->user(),
                ipHint: $request->ip(),
            );

            return response()->json($result->toArray());
        } catch (DeviceNotFoundException) {
            return response()->json(['error' => 'Device not found'], 404);
        } catch (DeviceAlreadyClaimedException) {
            return response()->json(['error' => 'Device already claimed'], 409);
        }
    }
}
