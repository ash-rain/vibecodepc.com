<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\TunnelProvisionRequest;
use App\Http\Requests\Api\TunnelRegisterRequest;
use App\Http\Requests\Api\TunnelRoutesUpdateRequest;
use App\Http\Resources\TunnelRouteResource;
use App\Services\CloudflareTunnelService;
use App\Services\SubdomainService;
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

    public function provision(
        TunnelProvisionRequest $request,
        string $uuid,
        SubdomainService $subdomainService,
        CloudflareTunnelService $cfService,
    ): JsonResponse {
        $device = $request->attributes->get('device');
        $user = $request->user();
        $subdomain = $request->validated('subdomain');

        if (! $subdomainService->isAvailable($subdomain, $user->id)) {
            return response()->json(['error' => 'Subdomain is not available.'], 409);
        }

        $subdomainService->updateSubdomain($user, $subdomain);

        $tunnel = $cfService->createTunnel("device-{$device->uuid}");
        $tunnelId = $tunnel['id'];

        $cfService->configureTunnelIngress($tunnelId, "{$subdomain}.vibecodepc.com");
        $cfService->createDnsRecord($subdomain, $tunnelId);

        $token = $cfService->getTunnelToken($tunnelId);

        $device->update(['tunnel_url' => "https://{$subdomain}.vibecodepc.com"]);

        return response()->json([
            'tunnel_id' => $tunnelId,
            'tunnel_token' => $token,
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
