<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;

class ExportLogs extends Command
{
    protected $signature = 'device:export-logs
        {--lines=500 : Number of lines to export (default: 500)}
        {--since= : Export logs since this time (e.g., "1 hour ago", "2026-03-01", "-24 hours")}
        {--level= : Filter by log level (debug, info, notice, warning, error, critical, alert, emergency)}
        {--search= : Search for specific text in log messages}
        {--output= : Output file path (default: storage/logs/export-YYYY-MM-DD-HHmmss.zip)}
        {--format=txt : Output format (txt, json)}
        {--no-compress : Do not compress output, save as plain text}';

    protected $description = 'Export recent logs for debugging';

    public function handle(Filesystem $filesystem): int
    {
        $lines = (int) $this->option('lines');
        $since = $this->option('since');
        $level = $this->option('level');
        $search = $this->option('search');
        $outputPath = $this->option('output');
        $format = strtolower((string) $this->option('format'));
        $noCompress = $this->option('no-compress');

        // Validate format
        if (! in_array($format, ['txt', 'json'], true)) {
            $this->error("Invalid format '{$format}'. Allowed: txt, json");

            return self::FAILURE;
        }

        // Validate level if provided
        if ($level !== null && ! $this->isValidLogLevel($level)) {
            $this->error("Invalid log level '{$level}'. Allowed: debug, info, notice, warning, error, critical, alert, emergency");

            return self::FAILURE;
        }

        // Get log file path
        $logPath = storage_path('logs/laravel.log');

        if (! $filesystem->exists($logPath)) {
            $this->warn('No log file found at: '.$logPath);

            return self::SUCCESS;
        }

        // Check if log file is readable
        if (! is_readable($logPath)) {
            $this->error('Log file is not readable: '.$logPath);

            return self::FAILURE;
        }

        // Parse since timestamp
        $sinceTime = $this->parseSince($since);

        // Generate output filename
        if ($outputPath === null) {
            $timestamp = now()->format('Y-m-d-His');
            if ($noCompress) {
                $ext = $format === 'json' ? '.json' : '.log';
                $outputPath = storage_path("logs/export-{$timestamp}{$ext}");
            } else {
                $outputPath = storage_path("logs/export-{$timestamp}.zip");
            }
        }

        // Ensure output directory exists
        $outputDir = dirname($outputPath);
        if (! $filesystem->isDirectory($outputDir)) {
            $filesystem->makeDirectory($outputDir, 0755, true);
        }

        $this->info('Reading log file...');

        // Read and filter logs
        $logs = $this->readLogs($logPath, $lines, $sinceTime, $level, $search);

        if (empty($logs)) {
            $this->warn('No logs found matching the specified criteria.');

            return self::SUCCESS;
        }

        $this->info(sprintf('Found %d matching log entries', count($logs)));

        // Export logs
        if ($format === 'json') {
            $result = $this->exportJson($logs, $outputPath, $noCompress, $filesystem);
        } else {
            $result = $this->exportText($logs, $outputPath, $noCompress, $filesystem);
        }

        if (! $result) {
            $this->error('Failed to export logs to: '.$outputPath);

            return self::FAILURE;
        }

        // Output summary
        $this->newLine();
        $this->info('╔════════════════════════════════════════════════════════════╗');
        $this->info('║ Log Export Complete                                        ║');
        $this->info('╚════════════════════════════════════════════════════════════╝');
        $this->newLine();
        $this->info("Exported to: {$outputPath}");
        $this->info("Format: {$format}");
        $this->info('Total entries: '.count($logs));

        if ($since !== null && $sinceTime !== null) {
            $this->info('Time range: since '.$sinceTime->toDateTimeString());
        }

        if ($level !== null) {
            $this->info('Level filter: '.$level);
        }

        if ($search !== null) {
            $this->info('Search filter: '.$search);
        }

        $this->newLine();

        // Show file size
        $fileSize = $filesystem->size($outputPath);
        $this->info('File size: '.$this->formatBytes($fileSize));

        return self::SUCCESS;
    }

    /**
     * Read and filter logs from the log file.
     *
     * @return array<int, array<string, mixed>>
     */
    private function readLogs(
        string $logPath,
        int $maxLines,
        ?Carbon $sinceTime,
        ?string $level,
        ?string $search
    ): array {
        $logs = [];
        $handle = fopen($logPath, 'r');

        if ($handle === false) {
            return [];
        }

        // First pass: collect all entries
        $entries = [];
        $currentEntry = null;

        while (($line = fgets($handle)) !== false) {
            // Check if line starts a new log entry
            if (preg_match('/^\[(\d{4}-\d{2}-\d{2}\s\d{2}:\d{2}:\d{2})\]\s+(\w+)\.([\w-]+):\s*(.+)/', $line, $matches)) {
                // Save previous entry if exists
                if ($currentEntry !== null) {
                    $entries[] = $currentEntry;
                }

                // Start new entry
                $currentEntry = [
                    'timestamp' => $matches[1],
                    'environment' => $matches[2],
                    'level' => $matches[3],
                    'message' => $matches[4],
                ];
            } elseif ($currentEntry !== null) {
                // Continuation of multi-line entry (stack trace, etc.)
                $currentEntry['message'] .= "\n".$line;
            }
        }

        // Save final entry
        if ($currentEntry !== null) {
            $entries[] = $currentEntry;
        }

        fclose($handle);

        // Process from end (most recent) with limit
        $count = count($entries);
        for ($i = $count - 1; $i >= 0 && count($logs) < $maxLines; $i--) {
            $entry = $entries[$i];
            if ($this->matchesFilters($entry, $sinceTime, $level, $search)) {
                $logs[] = $entry;
            }
        }

        return array_reverse($logs);
    }

    /**
     * Check if a log entry matches the specified filters.
     *
     * @param  array<string, mixed>  $entry
     */
    private function matchesFilters(
        array $entry,
        ?Carbon $sinceTime,
        ?string $level,
        ?string $search
    ): bool {
        // Filter by time
        if ($sinceTime !== null) {
            $entryTime = Carbon::parse($entry['timestamp']);
            if ($entryTime->lessThan($sinceTime)) {
                return false;
            }
        }

        // Filter by level
        if ($level !== null && strtolower($entry['level']) !== strtolower($level)) {
            return false;
        }

        // Filter by search text
        if ($search !== null) {
            $message = $entry['message'].($entry['context'] ?? '');
            if (stripos($message, $search) === false) {
                return false;
            }
        }

        return true;
    }

    /**
     * Export logs in JSON format.
     *
     * @param  array<int, array<string, mixed>>  $logs
     */
    private function exportJson(
        array $logs,
        string $outputPath,
        bool $noCompress,
        Filesystem $filesystem
    ): bool {
        $json = json_encode($logs, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

        if ($noCompress) {
            // Save as plain JSON file
            if (str_ends_with($outputPath, '.zip')) {
                $outputPath = substr($outputPath, 0, -4).'.json';
            }

            return $filesystem->put($outputPath, $json) !== false;
        }

        // Save as compressed ZIP
        $tempFile = sys_get_temp_dir().'/export-logs-'.uniqid().'.json';
        $filesystem->put($tempFile, $json);

        $zip = new \ZipArchive;
        $result = $zip->open($outputPath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE);

        if ($result === true) {
            $zip->addFile($tempFile, 'logs.json');
            $zip->close();
            $filesystem->delete($tempFile);

            return true;
        }

        $filesystem->delete($tempFile);

        return false;
    }

    /**
     * Export logs in text format.
     *
     * @param  array<int, array<string, mixed>>  $logs
     */
    private function exportText(
        array $logs,
        string $outputPath,
        bool $noCompress,
        Filesystem $filesystem
    ): bool {
        $content = 'Log Export - Generated '.now()->toIso8601String()."\n";
        $content .= str_repeat('=', 80)."\n\n";

        foreach ($logs as $log) {
            $content .= "[{$log['timestamp']}] {$log['environment']}.{$log['level']}: {$log['message']}\n";
        }

        if ($noCompress) {
            // Save as plain text file
            if (str_ends_with($outputPath, '.zip')) {
                $outputPath = substr($outputPath, 0, -4).'.log';
            }

            return $filesystem->put($outputPath, $content) !== false;
        }

        // Save as compressed ZIP
        $tempFile = sys_get_temp_dir().'/export-logs-'.uniqid().'.log';
        $filesystem->put($tempFile, $content);

        $zip = new \ZipArchive;
        $result = $zip->open($outputPath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE);

        if ($result === true) {
            $zip->addFile($tempFile, 'logs.log');
            $zip->close();
            $filesystem->delete($tempFile);

            return true;
        }

        $filesystem->delete($tempFile);

        return false;
    }

    /**
     * Parse the --since option into a Carbon datetime.
     */
    private function parseSince(?string $since): ?Carbon
    {
        if ($since === null) {
            return null;
        }

        try {
            // Try parsing as relative time (e.g., "1 hour ago", "-24 hours")
            if (str_starts_with($since, '-') || str_contains($since, 'ago')) {
                return Carbon::parse($since);
            }

            // Try parsing as date string
            return Carbon::parse($since);
        } catch (\Exception) {
            $this->warn("Could not parse '{$since}' as a valid date/time. Ignoring filter.");

            return null;
        }
    }

    /**
     * Check if the log level is valid.
     */
    private function isValidLogLevel(string $level): bool
    {
        $validLevels = ['debug', 'info', 'notice', 'warning', 'error', 'critical', 'alert', 'emergency'];

        return in_array(strtolower($level), $validLevels, true);
    }

    /**
     * Format bytes into human-readable string.
     */
    private function formatBytes(int $bytes, int $precision = 2): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];

        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);

        $bytes /= 1024 ** $pow;

        return round($bytes, $precision).' '.$units[$pow];
    }
}
