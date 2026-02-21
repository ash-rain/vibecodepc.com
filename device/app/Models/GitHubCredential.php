<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class GitHubCredential extends Model
{
    use HasFactory;

    protected $table = 'github_credentials';

    protected $fillable = [
        'access_token_encrypted',
        'github_username',
        'github_email',
        'github_name',
        'has_copilot',
        'token_expires_at',
    ];

    protected function casts(): array
    {
        return [
            'access_token_encrypted' => 'encrypted',
            'has_copilot' => 'boolean',
            'token_expires_at' => 'datetime',
        ];
    }

    public function getToken(): string
    {
        return $this->access_token_encrypted;
    }

    public function hasCopilot(): bool
    {
        return $this->has_copilot;
    }

    public static function current(): ?self
    {
        return static::latest()->first();
    }
}
