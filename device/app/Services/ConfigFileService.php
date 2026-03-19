<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Project;
use App\Services\Traits\RetryableTrait;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;

class ConfigFileService
{
    use RetryableTrait;

    private const MAX_FILE_SIZE_BYTES = 65536;

    /**
     * Forbidden key patterns that should not appear in config files.
     * These patterns match common API key, secret, and credential field names.
     *
     * @var array<int, string>
     */
    private const FORBIDDEN_KEY_PATTERNS = [
        '/^api[_-]?key$/i',
        '/^api[_-]?secret$/i',
        '/^api[_-]?token$/i',
        '/^auth[_-]?token$/i',
        '/^access[_-]?token$/i',
        '/^bearer[_-]?token$/i',
        '/^private[_-]?key$/i',
        '/^secret[_-]?key$/i',
        '/^client[_-]?secret$/i',
        '/^password$/i',
        '/^secret$/i',
        '/^token$/i',
    ];

    /**
     * Get the scope of a configuration key.
     *
     * @param  string  $key  The configuration key
     * @return string 'global' or 'project'
     */
    public function getScope(string $key): string
    {
        $config = $this->getConfig($key);

        return $config['scope'] ?? 'global';
    }

    /**
     * Check if a configuration key is project-scoped.
     *
     * @param  string  $key  The configuration key
     * @return bool True if project-scoped
     */
    public function isProjectScoped(string $key): bool
    {
        return $this->getScope($key) === 'project';
    }

    /**
     * Get the actual file path for a configuration.
     *
     * @param  string  $key  The configuration key
     * @param  Project|null  $project  Project context for project-scoped configs
     * @return string The resolved file path
     *
     * @throws \InvalidArgumentException If project is required but not provided
     */
    public function resolvePath(string $key, ?Project $project = null): string
    {
        $config = $this->getConfig($key);

        if ($this->isProjectScoped($key)) {
            if ($project === null) {
                throw new \InvalidArgumentException("Project is required for project-scoped config: {$key}");
            }

            if (isset($config['path_template'])) {
                return str_replace('{project_path}', $project->path, $config['path_template']);
            }

            // Fallback for boost.json which is always project-scoped but uses a fixed path
            return $config['path'];
        }

        return $config['path'];
    }

    /**
     * Get the content of a configuration file.
     *
     * @param  string  $key  The configuration key from vibecodepc.config_files
     * @param  Project|null  $project  Project context for project-scoped configs
     * @return string The file content, or empty string if file doesn't exist
     *
     * @throws \RuntimeException If the file cannot be read
     */
    public function getContent(string $key, ?Project $project = null): string
    {
        $path = $this->resolvePath($key, $project);

        if (! File::exists($path)) {
            return '';
        }

        if (! File::isFile($path)) {
            throw new \RuntimeException("Configuration file is not readable: {$path}");
        }

        if (! File::isReadable($path)) {
            throw new \RuntimeException("Configuration file is not readable: {$path}");
        }

        $content = File::get($path);

        return $content !== false ? $content : '';
    }

    /**
     * Get a content hash for conflict detection.
     *
     * @param  string  $content  The content to hash
     * @return string The SHA-256 hash
     */
    public function getContentHash(string $content): string
    {
        return hash('sha256', $content);
    }

    /**
     * Write content to a configuration file with backup.
     *
     * @param  string  $key  The configuration key from vibecodepc.config_files
     * @param  string  $newContent  The content to write
     * @param  Project|null  $project  Project context for project-scoped configs
     * @param  string|null  $expectedHash  Expected hash of current content for optimistic locking (null to skip)
     *
     * @throws \InvalidArgumentException If validation fails
     * @throws \RuntimeException If the file cannot be written
     * @throws \RuntimeException If the file has been modified since expectedHash (conflict detected)
     */
    public function putContent(string $key, string $newContent, ?Project $project = null, ?string $expectedHash = null): void
    {
        $path = $this->resolvePath($key, $project);

        $this->validateFileSize($newContent);

        if ($key !== 'copilot_instructions') {
            $this->validateJson($newContent, $key);
        }

        $directory = dirname($path);
        if (! File::isDirectory($directory)) {
            File::makeDirectory($directory, 0755, true);
        }

        $oldContent = File::exists($path) ? File::get($path) : null;
        $currentHash = $oldContent !== null ? $this->getContentHash($oldContent) : null;

        // Check for conflicts if expected hash is provided
        if ($expectedHash !== null && $currentHash !== $expectedHash) {
            throw new \RuntimeException('Configuration file has been modified by another user. Please reload and try again.');
        }

        $backupPath = null;
        if (File::exists($path)) {
            $backupPath = $this->backup($key, $project);
        }

        $success = retry($this->maxRetries, function () use ($path, $newContent): bool {
            return File::put($path, $newContent, true) !== false;
        }, $this->baseDelayMs);

        if (! $success) {
            throw new \RuntimeException("Failed to write configuration file: {$path}");
        }

        Log::info('Configuration file updated', ['key' => $key, 'path' => $path, 'project_id' => $project?->id]);

        // Log audit entry
        $auditLogService = app(ConfigAuditLogService::class);
        $action = $oldContent === null ? 'save' : 'save';
        $auditLogService->log($key, $action, $path, $oldContent, $newContent, $backupPath, $project);
    }

    /**
     * Validate JSON content.
     *
     * @param  string  $content  The content to validate
     * @param  string|null  $key  The configuration key (for boost.json validation)
     * @return array<string, mixed> The decoded JSON
     *
     * @throws \InvalidArgumentException If the JSON is invalid
     */
    public function validateJson(string $content, ?string $key = null): array
    {
        $content = $this->stripJsoncComments($content);

        $decoded = json_decode($content, true, 512, JSON_THROW_ON_ERROR);

        if (! is_array($decoded)) {
            throw new \InvalidArgumentException('JSON content must be an object');
        }

        if ($key === 'boost') {
            $this->validateBoostJson($decoded);
        }

        $this->validateNoForbiddenKeys($decoded);

        return $decoded;
    }

    /**
     * Create a backup of a configuration file.
     *
     * @param  string  $key  The configuration key from vibecodepc.config_files
     * @param  Project|null  $project  Project context for project-scoped configs
     * @return string The path to the backup file
     *
     * @throws \RuntimeException If backup cannot be created
     */
    public function backup(string $key, ?Project $project = null): string
    {
        $path = $this->resolvePath($key, $project);

        if (! File::exists($path)) {
            throw new \RuntimeException("Cannot backup non-existent file: {$path}");
        }

        $backupDir = config('vibecodepc.config_editor.backup_directory');
        // Use microsecond precision to avoid timestamp collisions
        $timestamp = now()->format('Y-m-d-His-u');
        $projectSuffix = $project ? "-project-{$project->id}" : '';
        $backupPath = "{$backupDir}/{$key}{$projectSuffix}-{$timestamp}.json";

        if (! File::isDirectory($backupDir)) {
            File::makeDirectory($backupDir, 0755, true);
        }

        try {
            $content = File::get($path);
        } catch (\Exception $e) {
            throw new \RuntimeException("Failed to read file for backup: {$path}");
        }

        if ($content === false) {
            throw new \RuntimeException("Failed to read file for backup: {$path}");
        }

        $success = File::put($backupPath, $content, true);

        if (! $success) {
            throw new \RuntimeException("Failed to create backup: {$backupPath}");
        }

        $this->cleanupOldBackups($key, $project);

        Log::info('Configuration file backed up', ['key' => $key, 'backup_path' => $backupPath, 'project_id' => $project?->id]);

        return $backupPath;
    }

    /**
     * Get list of available backups for a configuration file.
     *
     * @param  string  $key  The configuration key
     * @param  Project|null  $project  Project context for project-scoped configs
     * @return array<int, array<string, mixed>> List of backups with metadata
     */
    public function listBackups(string $key, ?Project $project = null): array
    {
        $backupDir = config('vibecodepc.config_editor.backup_directory');

        if (! File::isDirectory($backupDir)) {
            return [];
        }

        $projectSuffix = $project ? "-project-{$project->id}" : '';
        $pattern = $backupDir.'/'.$key.$projectSuffix.'-*.json';
        $files = File::glob($pattern);

        $backups = [];
        foreach ($files as $file) {
            $backups[] = [
                'path' => $file,
                'filename' => basename($file),
                'created_at' => File::lastModified($file),
                'size' => File::size($file),
            ];
        }

        usort($backups, fn (array $a, array $b): int => $b['created_at'] <=> $a['created_at']);

        return $backups;
    }

    /**
     * Restore a configuration file from a backup.
     *
     * @param  string  $key  The configuration key
     * @param  string  $backupPath  The path to the backup file
     * @param  Project|null  $project  Project context for project-scoped configs
     *
     * @throws \RuntimeException If restore fails
     */
    public function restore(string $key, string $backupPath, ?Project $project = null): void
    {
        if (! File::exists($backupPath)) {
            throw new \RuntimeException("Backup file does not exist: {$backupPath}");
        }

        $path = $this->resolvePath($key, $project);
        $oldContent = File::exists($path) ? File::get($path) : null;

        $content = File::get($backupPath);
        if ($content === false) {
            throw new \RuntimeException("Failed to read backup file: {$backupPath}");
        }

        $directory = dirname($path);
        if (! File::isDirectory($directory)) {
            File::makeDirectory($directory, 0755, true);
        }

        $success = File::put($path, $content, true);
        if (! $success) {
            throw new \RuntimeException("Failed to restore configuration file: {$path}");
        }

        Log::info('Configuration file restored from backup', ['key' => $key, 'backup_path' => $backupPath, 'project_id' => $project?->id]);

        // Log audit entry
        $auditLogService = app(ConfigAuditLogService::class);
        $auditLogService->log($key, 'restore', $path, $oldContent, $content, $backupPath, $project);
    }

    /**
     * Check if a file exists and is readable.
     *
     * @param  string  $key  The configuration key
     * @param  Project|null  $project  Project context for project-scoped configs
     * @return bool True if file exists and is readable
     */
    public function exists(string $key, ?Project $project = null): bool
    {
        $path = $this->resolvePath($key, $project);

        return File::exists($path) && File::isReadable($path);
    }

    /**
     * Delete a configuration file.
     *
     * @param  string  $key  The configuration key
     * @param  Project|null  $project  Project context for project-scoped configs
     *
     * @throws \RuntimeException If deletion fails
     */
    public function delete(string $key, ?Project $project = null): void
    {
        $path = $this->resolvePath($key, $project);

        if (File::exists($path)) {
            $oldContent = File::get($path);

            if (! File::delete($path)) {
                throw new \RuntimeException("Failed to delete configuration file: {$path}");
            }

            Log::info('Configuration file deleted', ['key' => $key, 'path' => $path, 'project_id' => $project?->id]);

            // Log audit entry
            $auditLogService = app(ConfigAuditLogService::class);
            $auditLogService->log($key, 'delete', $path, $oldContent, null, null, $project);
        }
    }

    /**
     * Get configuration metadata for a key.
     *
     * @param  string  $key  The configuration key
     * @return array<string, mixed> The configuration entry
     *
     * @throws \InvalidArgumentException If the key is not configured
     */
    private function getConfig(string $key): array
    {
        $files = config('vibecodepc.config_files', []);

        if (! isset($files[$key])) {
            throw new \InvalidArgumentException("Unknown configuration key: {$key}");
        }

        return $files[$key];
    }

    /**
     * Validate file size is within limits.
     *
     * @param  string  $content  The content to validate
     *
     * @throws \InvalidArgumentException If file exceeds size limit
     */
    private function validateFileSize(string $content): void
    {
        $maxSizeKb = config('vibecodepc.config_editor.max_file_size_kb', 64);
        $maxSizeBytes = $maxSizeKb * 1024;
        $size = strlen($content);

        if ($size > $maxSizeBytes) {
            throw new \InvalidArgumentException(
                "File size ({$size} bytes) exceeds maximum allowed size ({$maxSizeBytes} bytes)"
            );
        }
    }

    /**
     * Validate boost.json structure.
     *
     * @param  array<string, mixed>  $data  The decoded JSON data
     *
     * @throws \InvalidArgumentException If validation fails
     */
    private function validateBoostJson(array $data): void
    {
        if (isset($data['agents']) && ! is_array($data['agents'])) {
            throw new \InvalidArgumentException('boost.json: "agents" must be an array');
        }

        if (isset($data['skills']) && ! is_array($data['skills'])) {
            throw new \InvalidArgumentException('boost.json: "skills" must be an array');
        }

        $allowedKeys = ['agents', 'guidelines', 'herd_mcp', 'mcp', 'nightwatch_mcp', 'sail', 'skills'];
        $unknownKeys = array_diff(array_keys($data), $allowedKeys);

        if (! empty($unknownKeys)) {
            Log::warning('boost.json contains unknown keys', ['unknown_keys' => $unknownKeys]);
        }
    }

    /**
     * Validate that no forbidden keys are present in the configuration.
     *
     * @param  array<string, mixed>  $data  The decoded JSON data
     *
     * @throws \InvalidArgumentException If forbidden keys are detected
     */
    private function validateNoForbiddenKeys(array $data): void
    {
        $this->checkForbiddenKeysRecursive($data);
    }

    /**
     * Recursively check for forbidden keys in nested arrays.
     *
     * @param  array<string, mixed>  $data  The data to check
     * @param  string  $path  The current key path (for error reporting)
     *
     * @throws \InvalidArgumentException If forbidden keys are detected
     */
    private function checkForbiddenKeysRecursive(array $data, string $path = ''): void
    {
        foreach ($data as $key => $value) {
            $key = (string) $key;
            $currentPath = $path ? "{$path}.{$key}" : $key;

            foreach (self::FORBIDDEN_KEY_PATTERNS as $pattern) {
                if (preg_match($pattern, $key)) {
                    throw new \InvalidArgumentException(
                        "Forbidden key detected: '{$key}' at path '{$currentPath}'. ".
                        'Configuration files should not contain API keys, secrets, or credentials.'
                    );
                }
            }

            if (is_array($value)) {
                $this->checkForbiddenKeysRecursive($value, $currentPath);
            }
        }
    }

    /**
     * Strip JSONC comments from content before validation.
     * Properly handles comments inside strings and escape sequences.
     *
     * @param  string  $content  The JSONC content
     * @return string The content with comments removed
     */
    private function stripJsoncComments(string $content): string
    {
        $result = '';
        $length = strlen($content);
        $i = 0;
        $inString = false;
        $inMultilineComment = false;
        $escapeNext = false;

        while ($i < $length) {
            $char = $content[$i];

            if ($escapeNext) {
                // Previous character was an escape, include this character
                $result .= $char;
                $escapeNext = false;
                $i++;

                continue;
            }

            if ($char === '\\') {
                // Escape character - include it and mark next char as escaped
                $result .= $char;
                $escapeNext = true;
                $i++;

                continue;
            }

            if (! $inMultilineComment) {
                if (! $inString) {
                    // Not in string, not in comment - check for comment start
                    if ($i < $length - 1 && $char === '/' && $content[$i + 1] === '/') {
                        // Single-line comment - skip to end of line
                        // Find next newline or end of content
                        while ($i < $length && $content[$i] !== "\n") {
                            $i++;
                        }
                        // Keep the newline if present
                        if ($i < $length && $content[$i] === "\n") {
                            $result .= "\n";
                            $i++;
                        }

                        continue;
                    }

                    if ($i < $length - 1 && $char === '/' && $content[$i + 1] === '*') {
                        // Multi-line comment start
                        $inMultilineComment = true;
                        $i += 2;

                        continue;
                    }
                }

                if ($char === '"') {
                    $inString = ! $inString;
                }

                $result .= $char;
                $i++;
            } else {
                // In multi-line comment, look for end
                if ($i < $length - 1 && $char === '*' && $content[$i + 1] === '/') {
                    $inMultilineComment = false;
                    $i += 2;

                    continue;
                }
                $i++;
            }
        }

        return $result;
    }

    /**
     * Remove old backups based on retention policy.
     *
     * @param  string  $key  The configuration key
     * @param  Project|null  $project  Project context for project-scoped configs
     */
    private function cleanupOldBackups(string $key, ?Project $project = null): void
    {
        $retentionDays = config('vibecodepc.config_editor.backup_retention_days', 30);
        $backups = $this->listBackups($key, $project);
        $cutoff = now()->subDays($retentionDays)->timestamp;

        foreach ($backups as $backup) {
            if ($backup['created_at'] < $cutoff) {
                File::delete($backup['path']);
                Log::info('Old backup deleted', ['path' => $backup['path']]);
            }
        }
    }

    /**
     * Get the schema URL for a given config key.
     *
     * @param  string  $key  The configuration key
     * @return string|null The schema URL or null if no schema exists
     */
    public function getSchemaUrl(string $key): ?string
    {
        $schemaMapping = [
            'boost' => 'boost',
            'opencode_global' => 'opencode',
            'opencode_project' => 'opencode',
            'claude_global' => 'claude',
            'claude_project' => 'claude',
        ];

        if (! isset($schemaMapping[$key])) {
            return null;
        }

        $schemaName = $schemaMapping[$key];
        $schemaPath = storage_path("schemas/{$schemaName}.json");

        if (! File::exists($schemaPath)) {
            return null;
        }

        return route('schemas.json', ['name' => $schemaName]);
    }

    /**
     * Validate JSON content against schema if available.
     *
     * @param  array<string, mixed>  $data  The decoded JSON data
     * @param  string  $key  The configuration key
     * @return array<string> Array of validation errors, empty if valid
     */
    public function validateJsonSchema(array $data, string $key): array
    {
        $schemaUrl = $this->getSchemaUrl($key);

        if ($schemaUrl === null) {
            return [];
        }

        $schemaPath = storage_path('schemas/'.basename($schemaUrl, '.json').'.json');

        if (! File::exists($schemaPath)) {
            return [];
        }

        $schemaContent = File::get($schemaPath);
        if ($schemaContent === false) {
            return [];
        }

        try {
            $schema = json_decode($schemaContent, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            Log::warning('Failed to parse schema file', ['key' => $key, 'error' => $e->getMessage()]);

            return [];
        }

        // Ensure schema is an array/object
        if (! is_array($schema)) {
            return [];
        }

        $errors = [];
        $this->validateSchemaRecursive($data, $schema, '', $errors);

        return $errors;
    }

    /**
     * Recursively validate data against schema.
     *
     * @param  mixed  $data  The data to validate
     * @param  array<string, mixed>  $schema  The schema definition
     * @param  string  $path  Current path in the data structure
     * @param  array<string>  $errors  Array to collect errors
     */
    private function validateSchemaRecursive(mixed $data, array $schema, string $path, array &$errors): void
    {
        // Check type
        if (isset($schema['type'])) {
            $type = $schema['type'];
            $dataType = gettype($data);

            if ($dataType === 'array' && array_is_list($data)) {
                $dataType = 'array';
            } elseif ($dataType === 'array') {
                $dataType = 'object';
            } elseif ($dataType === 'double') {
                $dataType = 'number';
            }

            if ($type === 'array' && $dataType !== 'array') {
                $errors[] = "{$path}: must be an array";

                return;
            }

            if ($type === 'object' && $dataType !== 'object') {
                $errors[] = "{$path}: must be an object";

                return;
            }

            if ($type === 'string' && $dataType !== 'string') {
                $errors[] = "{$path}: must be a string";

                return;
            }

            if ($type === 'number' && ! is_numeric($data)) {
                $errors[] = "{$path}: must be a number";

                return;
            }

            if ($type === 'boolean' && $dataType !== 'boolean') {
                $errors[] = "{$path}: must be a boolean";

                return;
            }
        }

        // Validate object properties
        if (isset($schema['properties']) && is_array($data)) {
            foreach ($schema['properties'] as $propName => $propSchema) {
                if (isset($data[$propName])) {
                    $propPath = $path ? "{$path}.{$propName}" : $propName;
                    $this->validateSchemaRecursive($data[$propName], $propSchema, $propPath, $errors);
                }
            }

            // Check for additional properties
            if (isset($schema['additionalProperties']) && $schema['additionalProperties'] === false) {
                $allowedKeys = array_keys($schema['properties'] ?? []);
                $actualKeys = array_keys($data);
                $extraKeys = array_diff($actualKeys, $allowedKeys);

                foreach ($extraKeys as $extraKey) {
                    $propPath = $path ? "{$path}.{$extraKey}" : $extraKey;
                    $errors[] = "{$propPath}: additional property not allowed";
                }
            }
        }

        // Validate array items
        if (isset($schema['items']) && is_array($data) && array_is_list($data)) {
            foreach ($data as $index => $item) {
                $itemPath = "{$path}[{$index}]";
                $this->validateSchemaRecursive($item, $schema['items'], $itemPath, $errors);
            }
        }

        // Check enum values
        if (isset($schema['enum']) && is_array($schema['enum'])) {
            if (! in_array($data, $schema['enum'], true)) {
                $errors[] = "{$path}: must be one of: ".implode(', ', $schema['enum']);
            }
        }

        // Check minimum/maximum for numbers
        if (is_numeric($data)) {
            if (isset($schema['minimum']) && $data < $schema['minimum']) {
                $errors[] = "{$path}: must be >= {$schema['minimum']}";
            }
            if (isset($schema['maximum']) && $data > $schema['maximum']) {
                $errors[] = "{$path}: must be <= {$schema['maximum']}";
            }
        }
    }
}
