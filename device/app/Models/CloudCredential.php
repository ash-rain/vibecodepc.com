<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CloudCredential extends Model
{
    protected $fillable = [
        'pairing_token_encrypted',
        'cloud_username',
        'cloud_email',
        'cloud_url',
        'is_paired',
        'paired_at',
    ];

    protected function casts(): array
    {
        return [
            'pairing_token_encrypted' => 'encrypted',
            'is_paired' => 'boolean',
            'paired_at' => 'datetime',
        ];
    }

    public static function current(): ?self
    {
        return static::latest()->first();
    }

    public function isPaired(): bool
    {
        return $this->is_paired;
    }

    public function getToken(): string
    {
        return $this->pairing_token_encrypted;
    }
}
