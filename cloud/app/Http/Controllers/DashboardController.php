<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Device;
use App\Models\TunnelRequestLog;
use App\Services\CloudflareTunnelService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;
use VibecodePC\Common\Enums\DeviceStatus;

class DashboardController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();

        $devices = $user
            ->devices()
            ->withCount(['tunnelRoutes as active_routes_count' => function ($query) {
                $query->where('is_active', true);
            }])
            ->latest('paired_at')
            ->get();

        $currentTier = $user->subscriptionTier();

        $activeSubdomainCount = \App\Models\TunnelRoute::query()
            ->whereIn('device_id', $devices->pluck('id'))
            ->where('is_active', true)
            ->distinct('subdomain')
            ->count('subdomain');

        return view('dashboard.index', [
            'devices' => $devices,
            'onlineCount' => $devices->where('is_online', true)->count(),
            'totalCount' => $devices->count(),
            'currentTier' => $currentTier,
            'activeSubdomainCount' => $activeSubdomainCount,
            'maxSubdomains' => $currentTier->maxSubdomains(),
            'bandwidthGb' => $currentTier->monthlyBandwidthGb(),
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

        $routeIds = $device->tunnelRoutes->pluck('id');
        $trafficStats = TunnelRequestLog::query()
            ->select(
                'tunnel_route_id',
                DB::raw('COUNT(*) as total_requests'),
                DB::raw('ROUND(AVG(response_time_ms)) as avg_response_time'),
            )
            ->whereIn('tunnel_route_id', $routeIds)
            ->groupBy('tunnel_route_id')
            ->get()
            ->keyBy('tunnel_route_id');

        $hourlyExpr = match (DB::getDriverName()) {
            'sqlite' => "strftime('%Y-%m-%d %H:00', created_at)",
            'pgsql' => "to_char(created_at, 'YYYY-MM-DD HH24:00')",
            'mysql', 'mariadb' => "DATE_FORMAT(created_at, '%Y-%m-%d %H:00')",
            'sqlsrv' => "FORMAT(created_at, 'yyyy-MM-dd HH:00')",
            default => "to_char(created_at, 'YYYY-MM-DD HH24:00')",
        };

        $hourlyStats = TunnelRequestLog::query()
            ->select(
                DB::raw("{$hourlyExpr} as hour"),
                DB::raw('COUNT(*) as requests'),
            )
            ->whereIn('tunnel_route_id', $routeIds)
            ->where('created_at', '>=', now()->subHours(24))
            ->groupBy('hour')
            ->orderBy('hour')
            ->get();

        $statusCodeDistribution = TunnelRequestLog::query()
            ->select(
                DB::raw("CASE
                    WHEN status_code >= 200 AND status_code < 300 THEN '2xx'
                    WHEN status_code >= 300 AND status_code < 400 THEN '3xx'
                    WHEN status_code >= 400 AND status_code < 500 THEN '4xx'
                    WHEN status_code >= 500 THEN '5xx'
                    ELSE 'other'
                END as status_group"),
                DB::raw('COUNT(*) as count'),
            )
            ->whereIn('tunnel_route_id', $routeIds)
            ->where('created_at', '>=', now()->subHours(24))
            ->groupBy('status_group')
            ->get()
            ->keyBy('status_group');

        $totalRequests24h = $statusCodeDistribution->sum('count');
        $errorCount24h = ($statusCodeDistribution->get('5xx')?->count ?? 0) + ($statusCodeDistribution->get('4xx')?->count ?? 0);
        $errorRate = $totalRequests24h > 0 ? round(($errorCount24h / $totalRequests24h) * 100, 1) : 0;

        return view('dashboard.devices.show', [
            'device' => $device,
            'recentHeartbeats' => $recentHeartbeats,
            'trafficStats' => $trafficStats,
            'hourlyStats' => $hourlyStats,
            'statusCodeDistribution' => $statusCodeDistribution,
            'totalRequests24h' => $totalRequests24h,
            'errorRate' => $errorRate,
        ]);
    }

    public function destroyDevice(Request $request, Device $device, CloudflareTunnelService $cfService): RedirectResponse
    {
        if ($device->user_id !== $request->user()->id) {
            abort(403);
        }

        $request->validate([
            'confirm_uuid' => ['required', 'string', "in:{$device->uuid}"],
        ], [
            'confirm_uuid.in' => 'The confirmation UUID does not match this device.',
        ]);

        $uuid = $device->uuid;

        // Delete Cloudflare tunnel
        try {
            $tunnel = $cfService->findTunnelByName("device-{$uuid}");

            if ($tunnel) {
                $cfService->deleteTunnel($tunnel['id']);
            }
        } catch (\Throwable $e) {
            Log::warning("Failed to delete CF tunnel for device-{$uuid}", ['error' => $e->getMessage()]);
        }

        // Delete DNS records for each subdomain
        $subdomains = $device->tunnelRoutes()->distinct()->pluck('subdomain');

        foreach ($subdomains as $subdomain) {
            try {
                $fqdn = "{$subdomain}." . config('app.tunnel_domain', 'vibecodepc.com');
                $dnsId = $cfService->findDnsRecord($fqdn);

                if ($dnsId) {
                    $cfService->deleteDnsRecord($dnsId);
                }
            } catch (\Throwable $e) {
                Log::warning("Failed to delete DNS for {$subdomain}", ['error' => $e->getMessage()]);
            }
        }

        // Delete all associated data
        $routeIds = $device->tunnelRoutes()->pluck('id');
        TunnelRequestLog::whereIn('tunnel_route_id', $routeIds)->delete();
        $device->tunnelRoutes()->delete();
        $device->heartbeats()->delete();

        // Unpair: reset to unclaimed so it can be re-paired
        $device->update([
            'user_id' => null,
            'status' => DeviceStatus::Unclaimed,
            'tunnel_url' => null,
            'paired_at' => null,
            'is_online' => false,
        ]);

        Log::info("Device {$uuid} unpaired and all data purged by user {$request->user()->id}");

        return redirect()->route('dashboard')
            ->with('status', "Device has been unpaired and all associated data has been deleted.");
    }

    public function deviceHeartbeats(Request $request, Device $device): JsonResponse
    {
        if ($device->user_id !== $request->user()->id) {
            abort(403);
        }

        $request->validate([
            'period' => 'required|string|in:today,48h,week,month,custom',
            'from' => 'required_if:period,custom|nullable|date',
            'to' => 'required_if:period,custom|nullable|date|after_or_equal:from',
        ]);

        $query = $device->heartbeats()->latest('created_at');

        match ($request->input('period')) {
            'today' => $query->where('created_at', '>=', now()->startOfDay()),
            '48h' => $query->where('created_at', '>=', now()->subHours(48)),
            'week' => $query->where('created_at', '>=', now()->subWeek()),
            'month' => $query->where('created_at', '>=', now()->subMonth()),
            'custom' => $query->whereBetween('created_at', [
                $request->date('from')->startOfDay(),
                $request->date('to')->endOfDay(),
            ]),
        };

        $heartbeats = $query->get()->reverse()->values();

        $timeFormat = in_array($request->input('period'), ['today', '48h'])
            ? 'H:i'
            : 'M j H:i';

        return response()->json([
            'labels' => $heartbeats->map(fn ($hb) => $hb->created_at?->format($timeFormat)),
            'cpu' => $heartbeats->map(fn ($hb) => $hb->cpu_percent),
            'temp' => $heartbeats->map(fn ($hb) => $hb->cpu_temp),
            'ram' => $heartbeats->map(fn ($hb) => $hb->ram_total_mb > 0
                ? round(($hb->ram_used_mb / $hb->ram_total_mb) * 100, 1)
                : null),
            'disk' => $heartbeats->map(fn ($hb) => $hb->disk_total_gb > 0
                ? round(($hb->disk_used_gb / $hb->disk_total_gb) * 100, 1)
                : null),
        ]);
    }
}
