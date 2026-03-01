<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use VibecodePC\Common\Enums\DeviceStatus;

class Device extends Model
{
    use HasFactory;

    protected $fillable = [
        'uuid',
        'status',
        'user_id',
        'paired_at',
        'ip_hint',
        'hardware_serial',
        'firmware_version',
        'pairing_token_encrypted',
        'last_heartbeat_at',
        'is_online',
        'os_version',
        'cpu_temp',
        'cpu_percent',
        'ram_used_mb',
        'ram_total_mb',
        'disk_used_gb',
        'disk_total_gb',
        'tunnel_url',
        'quick_tunnels',
        'config_version',
    ];

    protected function casts(): array
    {
        return [
            'status' => DeviceStatus::class,
            'paired_at' => 'datetime',
            'last_heartbeat_at' => 'datetime',
            'pairing_token_encrypted' => 'encrypted',
            'is_online' => 'boolean',
            'cpu_temp' => 'float',
            'cpu_percent' => 'float',
            'ram_used_mb' => 'integer',
            'ram_total_mb' => 'integer',
            'disk_used_gb' => 'float',
            'disk_total_gb' => 'float',
            'quick_tunnels' => 'array',
            'config_version' => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function heartbeats(): HasMany
    {
        return $this->hasMany(DeviceHeartbeat::class);
    }

    public function tunnelRoutes(): HasMany
    {
        return $this->hasMany(TunnelRoute::class);
    }

    public function isClaimed(): bool
    {
        return $this->status === DeviceStatus::Claimed;
    }

    public function isUnclaimed(): bool
    {
        return $this->status === DeviceStatus::Unclaimed;
    }

    /** @param Builder<self> $query */
    public function scopeOnline(Builder $query): void
    {
        $query->where('is_online', true);
    }

    /** @param Builder<self> $query */
    public function scopeClaimed(Builder $query): void
    {
        $query->where('status', DeviceStatus::Claimed);
    }

    public function getRamUsagePercentAttribute(): ?float
    {
        if (! $this->ram_total_mb || $this->ram_total_mb === 0) {
            return null;
        }

        return round(($this->ram_used_mb / $this->ram_total_mb) * 100, 1);
    }

    public function getDiskUsagePercentAttribute(): ?float
    {
        if (! $this->disk_total_gb || $this->disk_total_gb == 0) {
            return null;
        }

        return round(($this->disk_used_gb / $this->disk_total_gb) * 100, 1);
    }
}
