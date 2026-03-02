<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TunnelConfig extends Model
{
    use HasFactory;

    protected $fillable = [
        'subdomain',
        'tunnel_token_encrypted',
        'tunnel_id',
        'status',
        'verified_at',
        'skipped_at',
    ];

    protected function casts(): array
    {
        return [
            'tunnel_token_encrypted' => 'encrypted',
            'verified_at' => 'datetime',
            'skipped_at' => 'datetime',
        ];
    }

    public function isSkipped(): bool
    {
        return $this->skipped_at !== null || $this->status === 'skipped';
    }

    public static function current(): ?self
    {
        return static::latest()->first();
    }
}
