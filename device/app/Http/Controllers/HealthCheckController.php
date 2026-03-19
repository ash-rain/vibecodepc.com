<?php

namespace App\Http\Controllers;

use App\Services\DeviceHealthService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class HealthCheckController extends Controller
{
    public function __construct(
        private DeviceHealthService $healthService,
    ) {}

    public function __invoke(): JsonResponse
    {
        $dbHealthy = $this->checkDatabase();

        $metrics = $this->healthService->getMetrics();

        $healthy = $dbHealthy;

        return response()->json([
            'status' => $healthy ? 'healthy' : 'unhealthy',
            'timestamp' => now()->toIso8601String(),
            'checks' => [
                'database' => $dbHealthy ? 'ok' : 'failed',
            ],
            'metrics' => [
                'cpu_percent' => $metrics['cpu_percent'],
                'ram_used_mb' => $metrics['ram_used_mb'],
                'ram_total_mb' => $metrics['ram_total_mb'],
                'ram_percent' => $metrics['ram_total_mb'] > 0
                    ? round(($metrics['ram_used_mb'] / $metrics['ram_total_mb']) * 100, 1)
                    : 0,
                'disk_used_gb' => $metrics['disk_used_gb'],
                'disk_total_gb' => $metrics['disk_total_gb'],
                'disk_percent' => $metrics['disk_total_gb'] > 0
                    ? round(($metrics['disk_used_gb'] / $metrics['disk_total_gb']) * 100, 1)
                    : 0,
                'temperature_c' => $metrics['temperature_c'],
            ],
        ], $healthy ? 200 : 503);
    }

    private function checkDatabase(): bool
    {
        try {
            DB::connection()->getPdo();

            return true;
        } catch (\Throwable) {
            return false;
        }
    }
}
