<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use VibecodePC\Common\Enums\ProjectFramework;
use VibecodePC\Common\Enums\ProjectStatus;

class Project extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'framework',
        'status',
        'path',
        'port',
        'container_id',
        'tunnel_subdomain_path',
        'tunnel_enabled',
        'env_vars',
        'last_started_at',
        'last_stopped_at',
    ];

    protected function casts(): array
    {
        return [
            'framework' => ProjectFramework::class,
            'status' => ProjectStatus::class,
            'env_vars' => 'encrypted:array',
            'tunnel_enabled' => 'boolean',
            'last_started_at' => 'datetime',
            'last_stopped_at' => 'datetime',
        ];
    }

    /** @return HasMany<ProjectLog, $this> */
    public function logs(): HasMany
    {
        return $this->hasMany(ProjectLog::class);
    }

    public function isRunning(): bool
    {
        return $this->status === ProjectStatus::Running;
    }

    public function isStopped(): bool
    {
        return $this->status === ProjectStatus::Stopped;
    }

    public function getPublicUrl(): ?string
    {
        if (! $this->tunnel_enabled || ! $this->tunnel_subdomain_path) {
            return null;
        }

        $tunnelConfig = TunnelConfig::current();

        if (! $tunnelConfig) {
            return null;
        }

        return "https://{$tunnelConfig->subdomain}.vibecodepc.com/{$this->tunnel_subdomain_path}";
    }

    /** @param Builder<Project> $query */
    public function scopeRunning(Builder $query): void
    {
        $query->where('status', ProjectStatus::Running);
    }

    /** @param Builder<Project> $query */
    public function scopeStopped(Builder $query): void
    {
        $query->where('status', ProjectStatus::Stopped);
    }
}
