<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class QuickTunnel extends Model
{
    protected $fillable = [
        'project_id',
        'container_name',
        'container_id',
        'local_port',
        'tunnel_url',
        'status',
        'started_at',
        'stopped_at',
    ];

    protected function casts(): array
    {
        return [
            'local_port' => 'integer',
            'started_at' => 'datetime',
            'stopped_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Project, $this> */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function isRunning(): bool
    {
        return $this->status === 'running';
    }

    public function isStarting(): bool
    {
        return $this->status === 'starting';
    }

    public function isActive(): bool
    {
        return in_array($this->status, ['starting', 'running']);
    }

    public static function forDashboard(): ?self
    {
        return static::whereNull('project_id')
            ->whereIn('status', ['starting', 'running'])
            ->latest()
            ->first();
    }

    public static function forProject(int $projectId): ?self
    {
        return static::where('project_id', $projectId)
            ->whereIn('status', ['starting', 'running'])
            ->latest()
            ->first();
    }
}
