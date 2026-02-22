<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DeviceHeartbeat extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'device_id',
        'cpu_percent',
        'cpu_temp',
        'ram_used_mb',
        'ram_total_mb',
        'disk_used_gb',
        'disk_total_gb',
        'running_projects',
        'tunnel_active',
        'firmware_version',
        'os_version',
    ];

    protected function casts(): array
    {
        return [
            'cpu_percent' => 'float',
            'cpu_temp' => 'float',
            'ram_used_mb' => 'integer',
            'ram_total_mb' => 'integer',
            'disk_used_gb' => 'float',
            'disk_total_gb' => 'float',
            'running_projects' => 'integer',
            'tunnel_active' => 'boolean',
            'created_at' => 'datetime',
        ];
    }

    public function device(): BelongsTo
    {
        return $this->belongsTo(Device::class);
    }
}
