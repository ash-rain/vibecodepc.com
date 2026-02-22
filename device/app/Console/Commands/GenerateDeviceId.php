<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Str;
use VibecodePC\Common\DTOs\DeviceInfo;

class GenerateDeviceId extends Command
{
    protected $signature = 'device:generate-id
        {--force : Overwrite existing device.json}
        {--path= : Custom path for device.json (default: storage/device.json)}';

    protected $description = 'Generate a unique device ID and write device.json';

    public function handle(): int
    {
        $path = $this->option('path') ?? config('vibecodepc.device_json_path');

        if (file_exists($path) && ! $this->option('force')) {
            $this->error("Device identity already exists at {$path}. Use --force to overwrite.");

            return self::FAILURE;
        }

        $dir = dirname($path);
        if (! is_dir($dir)) {
            if (! mkdir($dir, 0755, true)) {
                $this->error("Cannot create directory: {$dir}");

                return self::FAILURE;
            }
        }

        $device = new DeviceInfo(
            id: Str::uuid()->toString(),
            hardwareSerial: $this->detectHardwareSerial(),
            manufacturedAt: now()->toIso8601String(),
            firmwareVersion: '1.0.0',
        );

        file_put_contents($path, $device->toJson());

        $this->info("Device ID generated: {$device->id}");
        $this->info("Written to: {$path}");
        $this->newLine();
        $this->line('QR URL: ' . config('vibecodepc.cloud_browser_url') . "/pair/{$device->id}");

        return self::SUCCESS;
    }

    private function detectHardwareSerial(): string
    {
        // On Raspberry Pi, read from /proc/cpuinfo
        if (file_exists('/proc/cpuinfo')) {
            $cpuinfo = file_get_contents('/proc/cpuinfo');
            if (preg_match('/Serial\s*:\s*(\w+)/', $cpuinfo, $matches)) {
                return $matches[1];
            }
        }

        // Fallback for dev/non-Pi environments
        return 'dev-' . Str::random(16);
    }
}
