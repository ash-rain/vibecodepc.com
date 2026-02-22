<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Exceptions\DeviceAlreadyClaimedException;
use App\Exceptions\DeviceNotFoundException;
use App\Models\Device;
use App\Services\CloudflareTunnelService;
use App\Services\DeviceRegistryService;
use App\Services\SubdomainService;
use App\Services\TunnelRoutingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;

class DevicePairingController extends Controller
{
    public function __construct(
        private readonly DeviceRegistryService $registry,
    ) {}

    public function show(Request $request, string $uuid): View|RedirectResponse
    {
        try {
            $device = $this->registry->findByUuid($uuid);
        } catch (DeviceNotFoundException) {
            abort(404);
        }

        if ($device->isClaimed()) {
            // If the current user owns this device, show "owned" view
            if ($request->user() && $device->user_id === $request->user()->id) {
                return view('pairing.owned', ['device' => $device]);
            }

            return view('pairing.already-claimed', ['device' => $device]);
        }

        // If not logged in, redirect to login with intended URL
        if (! $request->user()) {
            return redirect()->guest(route('login'));
        }

        return view('pairing.claim', [
            'device' => $device,
            'user' => $request->user(),
        ]);
    }

    public function claim(Request $request, string $uuid): RedirectResponse
    {
        if (! $request->user()) {
            return redirect()->guest(route('login'));
        }

        try {
            $this->registry->claimDevice(
                uuid: $uuid,
                user: $request->user(),
                ipHint: $request->ip(),
            );
        } catch (DeviceNotFoundException) {
            abort(404);
        } catch (DeviceAlreadyClaimedException) {
            return redirect()->route('pairing.show', $uuid)
                ->with('error', 'This device has already been claimed.');
        }

        return redirect()->route('pairing.setup', $uuid);
    }

    public function setup(Request $request, string $uuid): View|RedirectResponse
    {
        if (! $request->user()) {
            return redirect()->guest(route('login'));
        }

        $device = $this->findOwnedDevice($request, $uuid);

        return view('pairing.setup', [
            'device' => $device,
            'user' => $request->user(),
            'subdomain' => $request->user()->username,
            'tunnelUrl' => $device->tunnel_url,
        ]);
    }

    public function provisionAndSetup(
        Request $request,
        string $uuid,
        SubdomainService $subdomainService,
        CloudflareTunnelService $cfService,
        TunnelRoutingService $routingService,
    ): View|RedirectResponse {
        if (! $request->user()) {
            return redirect()->guest(route('login'));
        }

        $device = $this->findOwnedDevice($request, $uuid);
        $user = $request->user();

        $request->merge(['subdomain' => strtolower(trim($request->input('subdomain', '')))]);

        $request->validate([
            'subdomain' => ['required', 'string', 'min:3', 'max:30', 'regex:/^[a-z0-9][a-z0-9-]*[a-z0-9]$/'],
        ], [
            'subdomain.regex' => 'Only lowercase letters, numbers, and hyphens allowed. Must start and end with a letter or number.',
        ]);

        $subdomain = $request->input('subdomain');

        if (! $subdomainService->isAvailable($subdomain, $user->id)) {
            return redirect()->route('pairing.setup', $uuid)
                ->with('error', 'That subdomain is not available. Please choose a different one.')
                ->withInput();
        }

        try {
            $subdomainService->updateSubdomain($user, $subdomain);

            $deviceAppPort = (int) config('cloudflare.device_app_port', 8001);
            $tunnel = $cfService->createTunnel("device-{$device->uuid}");
            $cfService->configureTunnelIngress($tunnel['id'], "{$subdomain}.vibecodepc.com", $deviceAppPort);
            $cfService->createDnsRecord($subdomain, $tunnel['id']);

            $device->update(['tunnel_url' => "https://{$subdomain}.vibecodepc.com"]);

            $routingService->updateRoutes($device, $subdomain, [
                ['path' => '/', 'target_port' => $deviceAppPort],
            ]);

            Log::info("Tunnel provisioned during pairing setup", [
                'device_uuid' => $uuid,
                'subdomain' => $subdomain,
                'tunnel_id' => $tunnel['id'],
            ]);
        } catch (\Throwable $e) {
            Log::error('Tunnel provisioning failed during setup', [
                'device_uuid' => $uuid,
                'subdomain' => $subdomain,
                'error' => $e->getMessage(),
            ]);

            return redirect()->route('pairing.setup', $uuid)
                ->with('error', 'Failed to provision tunnel. Please try again.')
                ->withInput();
        }

        return view('pairing.setup', [
            'device' => $device,
            'user' => $user,
            'subdomain' => $subdomain,
            'tunnelUrl' => "https://{$subdomain}.vibecodepc.com",
        ]);
    }

    public function checkTunnelStatus(Request $request, string $uuid): JsonResponse
    {
        if (! $request->user()) {
            abort(401);
        }

        $device = $this->findOwnedDevice($request, $uuid);

        if (! $device->tunnel_url) {
            return response()->json(['ready' => false]);
        }

        try {
            $response = Http::timeout(8)->withoutVerifying()->get($device->tunnel_url);

            // Cloudflare returns 522/523/524 when the tunnel connector isn't established
            return response()->json([
                'ready' => ! in_array($response->status(), [522, 523, 524]),
                'tunnel_url' => $device->tunnel_url,
            ]);
        } catch (\Throwable) {
            return response()->json(['ready' => false]);
        }
    }

    public function success(Request $request, string $uuid): View
    {
        try {
            $device = $this->registry->findByUuid($uuid);
        } catch (DeviceNotFoundException) {
            abort(404);
        }

        // Only the owner should see the success page
        if (! $request->user() || $device->user_id !== $request->user()->id) {
            abort(403);
        }

        return view('pairing.success', [
            'device' => $device,
            'user' => $request->user(),
        ]);
    }

    private function findOwnedDevice(Request $request, string $uuid): Device
    {
        try {
            $device = $this->registry->findByUuid($uuid);
        } catch (DeviceNotFoundException) {
            abort(404);
        }

        if ($device->user_id !== $request->user()->id) {
            abort(403);
        }

        return $device;
    }
}
