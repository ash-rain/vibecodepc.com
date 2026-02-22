<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Device;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(Request $request): View
    {
        $devices = $request->user()
            ->devices()
            ->withCount(['tunnelRoutes as active_routes_count' => function ($query) {
                $query->where('is_active', true);
            }])
            ->latest('paired_at')
            ->get();

        return view('dashboard.index', [
            'devices' => $devices,
            'onlineCount' => $devices->where('is_online', true)->count(),
            'totalCount' => $devices->count(),
        ]);
    }

    public function showDevice(Request $request, Device $device): View
    {
        if ($device->user_id !== $request->user()->id) {
            abort(403);
        }

        $device->load(['tunnelRoutes' => function ($query) {
            $query->where('is_active', true);
        }]);

        $recentHeartbeats = $device->heartbeats()
            ->latest('created_at')
            ->limit(60)
            ->get();

        return view('dashboard.devices.show', [
            'device' => $device,
            'recentHeartbeats' => $recentHeartbeats,
        ]);
    }
}
