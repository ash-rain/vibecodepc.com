<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TunnelRequestLog extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'tunnel_route_id',
        'status_code',
        'response_time_ms',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'status_code' => 'integer',
            'response_time_ms' => 'integer',
            'created_at' => 'datetime',
        ];
    }

    public function tunnelRoute(): BelongsTo
    {
        return $this->belongsTo(TunnelRoute::class);
    }
}
