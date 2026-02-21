<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Exceptions\DeviceAlreadyClaimedException;
use App\Exceptions\DeviceNotFoundException;
use App\Services\DeviceRegistryService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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

        return redirect()->route('pairing.success', $uuid);
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
}
