<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use VibecodePC\Common\Enums\AiProvider;

class AiProviderConfig extends Model
{
    use HasFactory;

    protected $table = 'ai_providers';

    protected $fillable = [
        'provider',
        'api_key_encrypted',
        'display_name',
        'base_url',
        'status',
        'validated_at',
    ];

    protected function casts(): array
    {
        return [
            'provider' => AiProvider::class,
            'api_key_encrypted' => 'encrypted',
            'validated_at' => 'datetime',
        ];
    }

    public function isValidated(): bool
    {
        return $this->validated_at !== null;
    }

    public function getDecryptedKey(): string
    {
        return $this->api_key_encrypted;
    }
}
