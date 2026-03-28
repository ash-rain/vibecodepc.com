<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ConfigAuditLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'config_key',
        'action',
        'user_id',
        'project_id',
        'file_path',
        'old_content_hash',
        'new_content_hash',
        'backup_path',
        'change_summary',
        'ip_address',
        'user_agent',
    ];

    protected function casts(): array
    {
        return [
            'old_content_hash' => 'array',
            'new_content_hash' => 'array',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<Project, $this> */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }
}
