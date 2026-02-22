<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Device;
use App\Models\TunnelRequestLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

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
}
