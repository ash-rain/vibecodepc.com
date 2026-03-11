<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\CloudCredential;
use App\Models\Project;
use App\Models\QuickTunnel;
use App\Services\DeviceHealthService;
use App\Services\DeviceStateService;
use App\Services\NetworkService;
use App\Services\SystemService;
use App\Services\Tunnel\TunnelService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Process;

class DeviceHealth extends Command
{
    protected $signature = 'device:health
                            {--json : Output metrics in JSON format}
                            {--format=table : Output format (table, json)}';

    protected $description = 'Display comprehensive device health metrics and status';

    public function handle(
        DeviceHealthService $healthService,
        NetworkService $networkService,
        DeviceStateService $stateService,
        SystemService $systemService,
        TunnelService $tunnelService
    ): int {
        $metrics = $this->collectMetrics(
            $healthService,
            $networkService,
            $stateService,
            $systemService,
            $tunnelService
        );

        if ($this->option('json') || $this->option('format') === 'json') {
            $this->outputJson($metrics);
        } else {
            $this->outputTable($metrics);
        }

        return self::SUCCESS;
    }

    /**
     * @return array<string, mixed>
     */
    private function collectMetrics(
        DeviceHealthService $healthService,
        NetworkService $networkService,
        DeviceStateService $stateService,
        SystemService $systemService,
        TunnelService $tunnelService
    ): array {
        $healthMetrics = $healthService->getMetrics();
        $credential = CloudCredential::current();
        $deviceJsonPath = config('vibecodepc.device_json_path');
        $deviceJson = file_exists($deviceJsonPath)
            ? json_decode(file_get_contents($deviceJsonPath), true)
            : [];

        return [
            // Health metrics
            'cpu_percent' => $healthMetrics['cpu_percent'],
            'ram_used_mb' => $healthMetrics['ram_used_mb'],
            'ram_total_mb' => $healthMetrics['ram_total_mb'],
            'ram_used_percent' => $healthMetrics['ram_total_mb'] > 0
                ? round(($healthMetrics['ram_used_mb'] / $healthMetrics['ram_total_mb']) * 100, 1)
                : 0,
            'disk_used_gb' => $healthMetrics['disk_used_gb'],
            'disk_total_gb' => $healthMetrics['disk_total_gb'],
            'disk_used_percent' => $healthMetrics['disk_total_gb'] > 0
                ? round(($healthMetrics['disk_used_gb'] / $healthMetrics['disk_total_gb']) * 100, 1)
                : 0,
            'temperature_c' => $healthMetrics['temperature_c'],

            // Network
            'local_ip' => $networkService->getLocalIp(),
            'has_ethernet' => $networkService->hasEthernet(),
            'has_wifi' => $networkService->hasWifi(),
            'has_internet' => $networkService->hasInternetConnectivity(),

            // Device state
            'mode' => $stateService->getMode(),
            'is_paired' => $credential?->isPaired() ?? false,
            'device_id' => $deviceJson['id'] ?? null,
            'firmware_version' => $deviceJson['firmware_version'] ?? 'unknown',
            'cloud_username' => $credential?->cloud_username ?? null,

            // System
            'timezone' => $systemService->getCurrentTimezone(),
            'uptime' => $this->getUptime(),

            // Application state
            'running_projects' => Project::running()->count(),
            'total_projects' => Project::count(),
            'tunnel_active' => $tunnelService->isRunning(),
            'quick_tunnels_active' => QuickTunnel::whereIn('status', ['starting', 'running'])->count(),

            // Timestamps
            'checked_at' => now()->toIso8601String(),
        ];
    }

    private function getUptime(): ?string
    {
        $result = Process::run('uptime -p 2>/dev/null || uptime');

        if ($result->successful()) {
            $output = trim($result->output());

            // Parse uptime format like "up 2 days, 5 hours, 30 minutes"
            if (str_starts_with($output, 'up ')) {
                return substr($output, 3);
            }

            return $output;
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $metrics
     */
    private function outputTable(array $metrics): void
    {
        $this->newLine();
        $this->info('╔════════════════════════════════════════════════════════════╗');
        $this->info('║              Device Health Status Report                   ║');
        $this->info('╚════════════════════════════════════════════════════════════╝');
        $this->newLine();

        // Device Info Section
        $this->info('─ Device Information ───────────────────────────────────────');
        $this->twoColumnTable([
            ['Device ID', $metrics['device_id'] ?? 'Not configured'],
            ['Firmware', $metrics['firmware_version']],
            ['Mode', ucfirst($metrics['mode'])],
            ['Pairing Status', $metrics['is_paired'] ? '✓ Paired' : '✗ Not paired'],
            ['Cloud User', $metrics['cloud_username'] ?? 'N/A'],
        ]);

        // System Resources Section
        $this->newLine();
        $this->info('─ System Resources ─────────────────────────────────────────');

        $ramPercent = $metrics['ram_used_percent'];
        $ramStatus = $this->getStatusIndicator($ramPercent, 80, 95);

        $diskPercent = $metrics['disk_used_percent'];
        $diskStatus = $this->getStatusIndicator($diskPercent, 80, 95);

        $cpuStatus = $this->getStatusIndicator($metrics['cpu_percent'], 70, 90);

        $this->twoColumnTable([
            ['CPU Usage', sprintf('%s %.1f%%', $cpuStatus, $metrics['cpu_percent'])],
            ['Memory', sprintf('%s %s / %s MB (%.1f%%)', $ramStatus, number_format($metrics['ram_used_mb']), number_format($metrics['ram_total_mb']), $ramPercent)],
            ['Disk', sprintf('%s %.1f / %.1f GB (%.1f%%)', $diskStatus, $metrics['disk_used_gb'], $metrics['disk_total_gb'], $diskPercent)],
            ['Temperature', $metrics['temperature_c'] !== null ? sprintf('%.1f°C', $metrics['temperature_c']) : 'N/A'],
            ['Uptime', $metrics['uptime'] ?? 'N/A'],
        ]);

        // Network Section
        $this->newLine();
        $this->info('─ Network Status ───────────────────────────────────────────');

        $internetStatus = $metrics['has_internet'] ? '✓ Connected' : '✗ Offline';
        $ethStatus = $metrics['has_ethernet'] ? '✓ Connected' : '✗ Disconnected';
        $wifiStatus = $metrics['has_wifi'] ? '✓ Available' : '✗ Unavailable';

        $this->twoColumnTable([
            ['Local IP', $metrics['local_ip']],
            ['Internet', $internetStatus],
            ['Ethernet', $ethStatus],
            ['WiFi', $wifiStatus],
        ]);

        // Application State Section
        $this->newLine();
        $this->info('─ Application State ────────────────────────────────────────');

        $tunnelStatus = $metrics['tunnel_active'] ? '✓ Active' : '✗ Inactive';

        $this->twoColumnTable([
            ['Running Projects', (string) $metrics['running_projects'].' / '.$metrics['total_projects']],
            ['Tunnel Status', $tunnelStatus],
            ['Quick Tunnels', (string) $metrics['quick_tunnels_active']],
            ['Timezone', $metrics['timezone']],
        ]);

        $this->newLine();
        $this->info(sprintf('Report generated: %s', $metrics['checked_at']));
        $this->newLine();
    }

    /**
     * @param  array<int, array{0: string, 1: string}>  $rows
     */
    private function twoColumnTable(array $rows): void
    {
        $maxLabelWidth = 0;
        foreach ($rows as $row) {
            $maxLabelWidth = max($maxLabelWidth, strlen($row[0]));
        }

        foreach ($rows as $row) {
            $label = str_pad($row[0], $maxLabelWidth, ' ', STR_PAD_RIGHT);
            $this->line("  {$label} : {$row[1]}");
        }
    }

    private function getStatusIndicator(float $percent, int $warning, int $critical): string
    {
        if ($percent >= $critical) {
            return '🔴'; // Critical
        }
        if ($percent >= $warning) {
            return '🟡'; // Warning
        }

        return '🟢'; // OK
    }

    /**
     * @param  array<string, mixed>  $metrics
     */
    private function outputJson(array $metrics): void
    {
        $this->line(json_encode($metrics, JSON_PRETTY_PRINT));
    }
}
