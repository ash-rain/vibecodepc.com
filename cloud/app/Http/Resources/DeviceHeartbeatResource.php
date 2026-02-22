<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\DeviceHeartbeat */
class DeviceHeartbeatResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'cpu_percent' => $this->cpu_percent,
            'cpu_temp' => $this->cpu_temp,
            'ram_used_mb' => $this->ram_used_mb,
            'ram_total_mb' => $this->ram_total_mb,
            'disk_used_gb' => $this->disk_used_gb,
            'disk_total_gb' => $this->disk_total_gb,
            'running_projects' => $this->running_projects,
            'tunnel_active' => $this->tunnel_active,
            'firmware_version' => $this->firmware_version,
            'os_version' => $this->os_version,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
