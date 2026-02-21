<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
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
    ];

    protected function casts(): array
    {
        return [
            'status' => DeviceStatus::class,
            'paired_at' => 'datetime',
            'pairing_token_encrypted' => 'encrypted',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isClaimed(): bool
    {
        return $this->status === DeviceStatus::Claimed;
    }

    public function isUnclaimed(): bool
    {
        return $this->status === DeviceStatus::Unclaimed;
    }
}
