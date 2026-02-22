<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Device;
use App\Models\TunnelRequestLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class DeviceStatsController extends Controller
{
    public function __invoke(Device $device): JsonResponse
    {
        $routeIds = $device->tunnelRoutes()->where('is_active', true)->pluck('id');

        $perRoute = TunnelRequestLog::query()
            ->select(
                'tunnel_route_id',
                DB::raw('COUNT(*) as total_requests'),
                DB::raw('ROUND(AVG(response_time_ms)) as avg_response_time'),
            )
            ->whereIn('tunnel_route_id', $routeIds)
            ->where('created_at', '>=', now()->subHours(24))
            ->groupBy('tunnel_route_id')
            ->get();

        $routeNames = $device->tunnelRoutes()
            ->whereIn('id', $routeIds)
            ->pluck('project_name', 'id');

        $stats = $perRoute->map(fn ($row) => [
            'project' => $routeNames->get($row->tunnel_route_id, 'unknown'),
            'requests' => (int) $row->total_requests,
            'avg_response_time_ms' => (int) $row->avg_response_time,
        ]);

        return response()->json([
            'period' => '24h',
            'routes' => $stats->values(),
        ]);
    }
}
