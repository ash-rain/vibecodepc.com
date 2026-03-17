<?php

declare(strict_types=1);

namespace App\Services;

use App\Services\Traits\RetryableTrait;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;

class ConfigFileService
{
    use RetryableTrait;

    private const MAX_FILE_SIZE_BYTES = 65536;

    /**
     * Get the content of a configuration file.
     *
     * @param  string  $key  The configuration key from vibecodepc.config_files
     * @return string The file content, or empty string if file doesn't exist
     *
     * @throws \RuntimeException If the file cannot be read
     */
    public function getContent(string $key): string
    {
        $config = $this->getConfig($key);
        $path = $config['path'];

        if (! File::exists($path)) {
            return '';
        }

        if (! File::isReadable($path)) {
            throw new \RuntimeException("Configuration file is not readable: {$path}");
        }

        $content = File::get($path);

        return $content !== false ? $content : '';
    }

    /**
     * Write content to a configuration file with backup.
     *
     * @param  string  $key  The configuration key from vibecodepc.config_files
     * @param  string  $newContent  The content to write
     *
     * @throws \InvalidArgumentException If validation fails
     * @throws \RuntimeException If the file cannot be written
     */
    public function putContent(string $key, string $newContent): void
    {
        $config = $this->getConfig($key);
        $path = $config['path'];

        $this->validateFileSize($newContent);

        if ($key !== 'copilot_instructions') {
            $this->validateJson($newContent, $key);
        }

        $directory = dirname($path);
        if (! File::isDirectory($directory)) {
            File::makeDirectory($directory, 0755, true);
        }

        if (File::exists($path)) {
            $this->backup($key);
        }

        $success = retry($this->maxRetries, function () use ($path, $newContent): bool {
            return File::put($path, $newContent, true) !== false;
        }, $this->baseDelayMs);

        if (! $success) {
            throw new \RuntimeException("Failed to write configuration file: {$path}");
        }

        Log::info('Configuration file updated', ['key' => $key, 'path' => $path]);
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

        return $decoded;
    }

    /**
     * Create a backup of a configuration file.
     *
     * @param  string  $key  The configuration key from vibecodepc.config_files
     * @return string The path to the backup file
     *
     * @throws \RuntimeException If backup cannot be created
     */
    public function backup(string $key): string
    {
        $config = $this->getConfig($key);
        $path = $config['path'];

        if (! File::exists($path)) {
            throw new \RuntimeException("Cannot backup non-existent file: {$path}");
        }

        $backupDir = config('vibecodepc.config_editor.backup_directory');
        $timestamp = now()->format('Y-m-d-His');
        $backupPath = "{$backupDir}/{$key}-{$timestamp}.json";

        if (! File::isDirectory($backupDir)) {
            File::makeDirectory($backupDir, 0755, true);
        }

        $content = File::get($path);
        if ($content === false) {
            throw new \RuntimeException("Failed to read file for backup: {$path}");
        }

        $success = File::put($backupPath, $content, true);

        if (! $success) {
            throw new \RuntimeException("Failed to create backup: {$backupPath}");
        }

        $this->cleanupOldBackups($key);

        Log::info('Configuration file backed up', ['key' => $key, 'backup_path' => $backupPath]);

        return $backupPath;
    }

    /**
     * Get list of available backups for a configuration file.
     *
     * @param  string  $key  The configuration key
     * @return array<int, array<string, mixed>> List of backups with metadata
     */
    public function listBackups(string $key): array
    {
        $backupDir = config('vibecodepc.config_editor.backup_directory');

        if (! File::isDirectory($backupDir)) {
            return [];
        }

        $pattern = $backupDir.'/'.$key.'-*.json';
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
     *
     * @throws \RuntimeException If restore fails
     */
    public function restore(string $key, string $backupPath): void
    {
        if (! File::exists($backupPath)) {
            throw new \RuntimeException("Backup file does not exist: {$backupPath}");
        }

        $config = $this->getConfig($key);
        $path = $config['path'];

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

        Log::info('Configuration file restored from backup', ['key' => $key, 'backup_path' => $backupPath]);
    }

    /**
     * Check if a file exists and is readable.
     *
     * @param  string  $key  The configuration key
     * @return bool True if file exists and is readable
     */
    public function exists(string $key): bool
    {
        $config = $this->getConfig($key);

        return File::exists($config['path']) && File::isReadable($config['path']);
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
     * Strip JSONC comments from content before validation.
     *
     * @param  string  $content  The JSONC content
     * @return string The content with comments removed
     */
    private function stripJsoncComments(string $content): string
    {
        $lines = explode("\n", $content);
        $result = [];

        $inMultilineComment = false;
        foreach ($lines as $line) {
            $processedLine = '';
            $length = strlen($line);
            $i = 0;

            while ($i < $length) {
                if (! $inMultilineComment) {
                    if ($i < $length - 1 && $line[$i] === '/' && $line[$i + 1] === '/') {
                        break;
                    }

                    if ($i < $length - 1 && $line[$i] === '/' && $line[$i + 1] === '*') {
                        $inMultilineComment = true;
                        $i += 2;

                        continue;
                    }

                    $processedLine .= $line[$i];
                    $i++;
                } else {
                    if ($i < $length - 1 && $line[$i] === '*' && $line[$i + 1] === '/') {
                        $inMultilineComment = false;
                        $i += 2;

                        continue;
                    }
                    $i++;
                }
            }

            if (! $inMultilineComment || trim($processedLine) !== '') {
                $result[] = $processedLine;
            }
        }

        return implode("\n", $result);
    }

    /**
     * Remove old backups based on retention policy.
     *
     * @param  string  $key  The configuration key
     */
    private function cleanupOldBackups(string $key): void
    {
        $retentionDays = config('vibecodepc.config_editor.backup_retention_days', 30);
        $backups = $this->listBackups($key);
        $cutoff = now()->subDays($retentionDays)->timestamp;

        foreach ($backups as $backup) {
            if ($backup['created_at'] < $cutoff) {
                File::delete($backup['path']);
                Log::info('Old backup deleted', ['path' => $backup['path']]);
            }
        }
    }
}
