<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\ConfigAuditLog;
use App\Models\Project;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Request;

class ConfigAuditLogService
{
    /**
     * Log a configuration file action.
     *
     * @param  string  $configKey  The configuration key
     * @param  string  $action  The action performed (save, restore, reset, delete)
     * @param  string  $filePath  The path to the configuration file
     * @param  string|null  $oldContent  The previous content (for hashing)
     * @param  string|null  $newContent  The new content (for hashing)
     * @param  string|null  $backupPath  Path to backup file if created
     * @param  Project|null  $project  Project context for project-scoped configs
     */
    public function log(
        string $configKey,
        string $action,
        string $filePath,
        ?string $oldContent = null,
        ?string $newContent = null,
        ?string $backupPath = null,
        ?Project $project = null
    ): ConfigAuditLog {
        $oldHash = $oldContent !== null ? hash('sha256', $oldContent) : null;
        $newHash = $newContent !== null ? hash('sha256', $newContent) : null;

        $changeSummary = $this->generateChangeSummary($configKey, $action, $oldContent, $newContent);

        return ConfigAuditLog::create([
            'config_key' => $configKey,
            'action' => $action,
            'user_id' => Auth::id(),
            'project_id' => $project?->id,
            'file_path' => $filePath,
            'old_content_hash' => $oldHash ? ['sha256' => $oldHash] : null,
            'new_content_hash' => $newHash ? ['sha256' => $newHash] : null,
            'backup_path' => $backupPath,
            'change_summary' => $changeSummary,
            'ip_address' => Request::ip(),
            'user_agent' => Request::userAgent(),
        ]);
    }

    /**
     * Generate a human-readable summary of changes.
     *
     * @param  string  $configKey  The configuration key
     * @param  string  $action  The action performed
     * @param  string|null  $oldContent  The previous content
     * @param  string|null  $newContent  The new content
     */
    private function generateChangeSummary(string $configKey, string $action, ?string $oldContent, ?string $newContent): ?string
    {
        if ($action === 'delete') {
            return "Deleted {$configKey} configuration file";
        }

        if ($action === 'reset') {
            return "Reset {$configKey} to default values";
        }

        if ($action === 'restore') {
            return "Restored {$configKey} from backup";
        }

        if ($oldContent === null || $newContent === null) {
            return "Created new {$configKey} configuration";
        }

        // For updates, try to extract meaningful changes
        try {
            $oldData = json_decode($oldContent, true);
            $newData = json_decode($newContent, true);

            if (is_array($oldData) && is_array($newData)) {
                $changes = $this->detectChanges($oldData, $newData);

                if (! empty($changes)) {
                    return implode('; ', array_slice($changes, 0, 5));
                }
            }
        } catch (\Exception $e) {
            // Fall back to generic message
        }

        return "Updated {$configKey} configuration";
    }

    /**
     * Detect changes between two arrays.
     *
     * @param  array<string, mixed>  $oldData  The old data
     * @param  array<string, mixed>  $newData  The new data
     * @return array<int, string> List of change descriptions
     */
    private function detectChanges(array $oldData, array $newData): array
    {
        $changes = [];
        $allKeys = array_unique(array_merge(array_keys($oldData), array_keys($newData)));

        foreach ($allKeys as $key) {
            if (! array_key_exists($key, $oldData)) {
                $changes[] = "Added '{$key}'";
            } elseif (! array_key_exists($key, $newData)) {
                $changes[] = "Removed '{$key}'";
            } elseif ($oldData[$key] !== $newData[$key]) {
                if (is_array($oldData[$key]) && is_array($newData[$key])) {
                    $nestedChanges = $this->detectChanges($oldData[$key], $newData[$key]);
                    foreach ($nestedChanges as $nestedChange) {
                        $changes[] = "'{$key}'.{$nestedChange}";
                    }
                } else {
                    $changes[] = "Modified '{$key}'";
                }
            }
        }

        return $changes;
    }

    /**
     * Get recent audit logs for a configuration key.
     *
     * @param  string  $configKey  The configuration key
     * @param  int  $limit  Maximum number of logs to return
     * @return array<int, ConfigAuditLog>
     */
    public function getRecentLogs(string $configKey, int $limit = 10): array
    {
        return ConfigAuditLog::where('config_key', $configKey)
            ->with(['user', 'project'])
            ->orderByDesc('created_at')
            ->limit($limit)
            ->get()
            ->all();
    }

    /**
     * Get all audit logs with optional filtering.
     *
     * @param  array<string, mixed>  $filters  Optional filters (config_key, action, user_id, project_id)
     * @param  int  $limit  Maximum number of logs to return
     * @return array<int, ConfigAuditLog>
     */
    public function getLogs(array $filters = [], int $limit = 50): array
    {
        $query = ConfigAuditLog::query();

        if (isset($filters['config_key'])) {
            $query->where('config_key', $filters['config_key']);
        }

        if (isset($filters['action'])) {
            $query->where('action', $filters['action']);
        }

        if (isset($filters['user_id'])) {
            $query->where('user_id', $filters['user_id']);
        }

        if (isset($filters['project_id'])) {
            $query->where('project_id', $filters['project_id']);
        }

        return $query->with(['user', 'project'])
            ->orderByDesc('created_at')
            ->limit($limit)
            ->get()
            ->all();
    }
}
