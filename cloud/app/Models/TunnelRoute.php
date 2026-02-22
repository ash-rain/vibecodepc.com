<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TunnelRoute extends Model
{
    use HasFactory;

    protected $fillable = [
        'device_id',
        'subdomain',
        'path',
        'target_port',
        'project_name',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'target_port' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function device(): BelongsTo
    {
        return $this->belongsTo(Device::class);
    }

    /** @param Builder<self> $query */
    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }

    public function getFullUrlAttribute(): string
    {
        $base = "https://{$this->subdomain}.vibecodepc.com";

        return $this->path === '/' ? $base : "{$base}{$this->path}";
    }
}
