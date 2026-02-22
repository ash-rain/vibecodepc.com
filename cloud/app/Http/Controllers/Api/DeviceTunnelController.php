<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\TunnelRegisterRequest;
use App\Http\Requests\Api\TunnelRoutesUpdateRequest;
use App\Http\Resources\TunnelRouteResource;
use App\Services\TunnelRoutingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DeviceTunnelController extends Controller
{
    public function __construct(
        private readonly TunnelRoutingService $routing,
    ) {}

    public function register(TunnelRegisterRequest $request, string $uuid): JsonResponse
    {
        $device = $request->attributes->get('device');

        $this->routing->registerTunnel($device, $request->validated('tunnel_url'));

        return response()->json([
            'message' => 'Tunnel registered',
            'tunnel_url' => $device->fresh()->tunnel_url,
        ]);
    }

    public function updateRoutes(TunnelRoutesUpdateRequest $request, string $uuid): JsonResponse
    {
        $device = $request->attributes->get('device');
        $validated = $request->validated();

        $routes = $this->routing->updateRoutes(
            $device,
            $validated['subdomain'],
            $validated['routes'],
        );

        return response()->json([
            'message' => 'Routes updated',
            'routes' => TunnelRouteResource::collection($routes),
        ]);
    }

    public function routes(Request $request, string $uuid): JsonResponse
    {
        $device = $request->attributes->get('device');

        $routes = $device->tunnelRoutes()->active()->get();

        return response()->json([
            'routes' => TunnelRouteResource::collection($routes),
        ]);
    }
}
