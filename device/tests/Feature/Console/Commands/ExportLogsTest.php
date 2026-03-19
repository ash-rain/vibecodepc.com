<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function () {
    Storage::fake('logs');

    // Create test log directory
    $logDir = storage_path('logs');
    if (! is_dir($logDir)) {
        mkdir($logDir, 0755, true);
    }
});

afterEach(function () {
    // Clean up exported files
    $files = glob(storage_path('logs/export-*'));
    foreach ($files as $file) {
        unlink($file);
    }
});

function createTestLogFile(string $content): void
{
    $logPath = storage_path('logs/laravel.log');
    file_put_contents($logPath, $content);
}

function cleanupTestLogFile(): void
{
    $logPath = storage_path('logs/laravel.log');
    if (file_exists($logPath)) {
        unlink($logPath);
    }
}

it('exports recent logs to compressed file by default', function () {
    $logContent = <<<'LOG'
[2026-03-11 10:00:00] production.INFO: Application started
[2026-03-11 10:01:00] production.WARNING: Something warning
[2026-03-11 10:02:00] production.ERROR: Something failed
LOG;

    createTestLogFile($logContent);

    $this->artisan('device:export-logs')
        ->assertSuccessful()
        ->expectsOutputToContain('Log Export Complete')
        ->expectsOutputToContain('Exported to:')
        ->expectsOutputToContain('Format: txt');

    // Verify export file was created
    $exportFiles = glob(storage_path('logs/export-*.zip'));
    expect($exportFiles)->toHaveCount(1);

    cleanupTestLogFile();
});

it('exports logs in JSON format', function () {
    $logContent = <<<'LOG'
[2026-03-11 10:00:00] production.INFO: Application started
[2026-03-11 10:01:00] production.WARNING: Something warning
LOG;

    createTestLogFile($logContent);

    $this->artisan('device:export-logs', ['--format' => 'json'])
        ->assertSuccessful()
        ->expectsOutputToContain('Log Export Complete')
        ->expectsOutputToContain('Format: json');

    cleanupTestLogFile();
});

it('exports uncompressed text file with --no-compress option', function () {
    $logContent = <<<'LOG'
[2026-03-11 10:00:00] production.INFO: Application started
[2026-03-11 10:01:00] production.WARNING: Something warning
LOG;

    createTestLogFile($logContent);

    $this->artisan('device:export-logs', ['--no-compress' => true])
        ->assertSuccessful();

    // Verify .log file was created instead of .zip
    $exportFiles = glob(storage_path('logs/export-*.log'));
    expect($exportFiles)->toHaveCount(1);

    // Verify content
    $content = file_get_contents($exportFiles[0]);
    expect($content)->toContain('Application started')
        ->toContain('Something warning');

    cleanupTestLogFile();
});

it('limits output to specified number of lines', function () {
    $logContent = '';
    for ($i = 0; $i < 100; $i++) {
        $logContent .= '[2026-03-11 10:'.sprintf('%02d', $i).":00] production.INFO: Log entry {$i}\n";
    }

    createTestLogFile($logContent);

    $this->artisan('device:export-logs', ['--lines' => 10])
        ->assertSuccessful()
        ->expectsOutputToContain('Total entries: 10');

    cleanupTestLogFile();
});

it('filters logs by level', function () {
    $logContent = <<<'LOG'
[2026-03-11 10:00:00] production.DEBUG: Debug message
[2026-03-11 10:01:00] production.INFO: Info message
[2026-03-11 10:02:00] production.WARNING: Warning message
[2026-03-11 10:03:00] production.ERROR: Error message
[2026-03-11 10:04:00] production.CRITICAL: Critical message
LOG;

    createTestLogFile($logContent);

    $this->artisan('device:export-logs', ['--level' => 'error'])
        ->assertSuccessful()
        ->expectsOutputToContain('Level filter: error');

    // Verify only error logs were exported
    $exportFiles = glob(storage_path('logs/export-*.zip'));
    $zip = new ZipArchive;
    $zip->open($exportFiles[0]);
    $content = $zip->getFromName('logs.log');
    $zip->close();

    expect($content)->toContain('Error message')
        ->not->toContain('Debug message')
        ->not->toContain('Info message')
        ->not->toContain('Warning message');

    cleanupTestLogFile();
});

it('filters logs by search text', function () {
    $logContent = <<<'LOG'
[2026-03-11 10:00:00] production.INFO: Database connection established
[2026-03-11 10:01:00] production.INFO: Cache cleared successfully
[2026-03-11 10:02:00] production.INFO: Database query executed
[2026-03-11 10:03:00] production.INFO: Email sent to user
LOG;

    createTestLogFile($logContent);

    $this->artisan('device:export-logs', ['--search' => 'Database'])
        ->assertSuccessful()
        ->expectsOutputToContain('Search filter: Database');

    // Verify only database-related logs were exported
    $exportFiles = glob(storage_path('logs/export-*.zip'));
    $zip = new ZipArchive;
    $zip->open($exportFiles[0]);
    $content = $zip->getFromName('logs.log');
    $zip->close();

    expect($content)->toContain('Database connection')
        ->toContain('Database query')
        ->not->toContain('Cache cleared')
        ->not->toContain('Email sent');

    cleanupTestLogFile();
});

it('filters logs by time range with since option', function () {
    $logContent = <<<'LOG'
[2026-03-11 08:00:00] production.INFO: Old log entry
[2026-03-11 09:00:00] production.INFO: Older log entry
[2026-03-11 10:00:00] production.INFO: Recent log entry
[2026-03-11 11:00:00] production.INFO: Newer log entry
LOG;

    createTestLogFile($logContent);

    $this->artisan('device:export-logs', ['--since' => '2026-03-11 09:30:00'])
        ->assertSuccessful()
        ->expectsOutputToContain('Time range:');

    cleanupTestLogFile();
});

it('accepts relative time for since option', function () {
    $recentTime = now()->subMinutes(30)->format('Y-m-d H:i:s');
    $logContent = "[{$recentTime}] production.INFO: Recent log entry\n";

    createTestLogFile($logContent);

    $this->artisan('device:export-logs', ['--since' => '-1 hour'])
        ->assertSuccessful()
        ->expectsOutputToContain('Time range:');

    cleanupTestLogFile();
});

it('exports to custom output path', function () {
    $logContent = <<<'LOG'
[2026-03-11 10:00:00] production.INFO: Test log entry
LOG;

    createTestLogFile($logContent);

    $customPath = storage_path('logs/custom-export.zip');

    $this->artisan('device:export-logs', ['--output' => $customPath])
        ->assertSuccessful()
        ->expectsOutputToContain('Exported to: '.$customPath);

    expect(file_exists($customPath))->toBeTrue();

    // Cleanup
    if (file_exists($customPath)) {
        unlink($customPath);
    }

    cleanupTestLogFile();
});

it('shows warning when log file does not exist', function () {
    // Ensure log file doesn't exist
    cleanupTestLogFile();

    $this->artisan('device:export-logs')
        ->assertSuccessful()
        ->expectsOutputToContain('No log file found');
});

it('shows error for invalid log level', function () {
    $logContent = '[2026-03-11 10:00:00] production.INFO: Test';
    createTestLogFile($logContent);

    $this->artisan('device:export-logs', ['--level' => 'invalid'])
        ->assertFailed()
        ->expectsOutputToContain('Invalid log level');

    cleanupTestLogFile();
});

it('shows error for invalid format', function () {
    $logContent = '[2026-03-11 10:00:00] production.INFO: Test';
    createTestLogFile($logContent);

    $this->artisan('device:export-logs', ['--format' => 'xml'])
        ->assertFailed()
        ->expectsOutputToContain('Invalid format');

    cleanupTestLogFile();
});

it('handles empty log file gracefully', function () {
    createTestLogFile('');

    $this->artisan('device:export-logs')
        ->assertSuccessful()
        ->expectsOutputToContain('No logs found matching');

    cleanupTestLogFile();
});

it('handles logs with stack traces', function () {
    $logContent = <<<'LOG'
[2026-03-11 10:00:00] production.ERROR: Exception message
Stack trace:
#0 /path/to/file.php(123): SomeClass->someMethod()
#1 /path/to/file2.php(456): AnotherClass->anotherMethod()
LOG;

    createTestLogFile($logContent);

    $this->artisan('device:export-logs')
        ->assertSuccessful()
        ->expectsOutputToContain('Log Export Complete');

    cleanupTestLogFile();
});

it('handles multi-line log entries', function () {
    $logContent = <<<'LOG'
[2026-03-11 10:00:00] production.INFO: Starting process
Context data:
- user_id: 123
- action: update
[2026-03-11 10:01:00] production.INFO: Process completed
LOG;

    createTestLogFile($logContent);

    $this->artisan('device:export-logs')
        ->assertSuccessful();

    cleanupTestLogFile();
});

it('exports compressed JSON file with --format json', function () {
    $logContent = <<<'LOG'
[2026-03-11 10:00:00] production.INFO: Application started
[2026-03-11 10:01:00] production.WARNING: Something warning
LOG;

    createTestLogFile($logContent);

    $this->artisan('device:export-logs', ['--format' => 'json'])
        ->assertSuccessful();

    // Verify JSON export file was created
    $exportFiles = glob(storage_path('logs/export-*.zip'));
    expect($exportFiles)->toHaveCount(1);

    // Verify JSON content
    $zip = new ZipArchive;
    $zip->open($exportFiles[0]);
    $content = $zip->getFromName('logs.json');
    $zip->close();

    $json = json_decode($content, true);
    expect($json)->toBeArray()
        ->toHaveCount(2);

    cleanupTestLogFile();
});

it('exports uncompressed JSON with --format json and --no-compress', function () {
    $logContent = <<<'LOG'
[2026-03-11 10:00:00] production.INFO: Application started
LOG;

    createTestLogFile($logContent);

    $this->artisan('device:export-logs', [
        '--format' => 'json',
        '--no-compress' => true,
    ])
        ->assertSuccessful();

    // Verify .json file was created instead of .zip
    $exportFiles = glob(storage_path('logs/export-*.json'));
    expect($exportFiles)->toHaveCount(1);

    cleanupTestLogFile();
});

it('combines multiple filters', function () {
    $logContent = <<<'LOG'
[2026-03-11 08:00:00] production.ERROR: Old error
[2026-03-11 10:00:00] production.ERROR: Database connection failed
[2026-03-11 10:01:00] production.WARNING: Database slow query
[2026-03-11 10:02:00] production.ERROR: Cache miss
LOG;

    createTestLogFile($logContent);

    $this->artisan('device:export-logs', [
        '--level' => 'error',
        '--search' => 'Database',
        '--since' => '2026-03-11 09:00:00',
    ])
        ->assertSuccessful();

    // Verify only matching logs were exported
    $exportFiles = glob(storage_path('logs/export-*.zip'));
    $zip = new ZipArchive;
    $zip->open($exportFiles[0]);
    $content = $zip->getFromName('logs.log');
    $zip->close();

    expect($content)->toContain('Database connection failed')
        ->not->toContain('Old error')
        ->not->toContain('Database slow')
        ->not->toContain('Cache miss');

    cleanupTestLogFile();
});

it('shows file size in output', function () {
    $logContent = <<<'LOG'
[2026-03-11 10:00:00] production.INFO: Test log entry
LOG;

    createTestLogFile($logContent);

    $this->artisan('device:export-logs')
        ->assertSuccessful()
        ->expectsOutputToContain('File size:');

    cleanupTestLogFile();
});

it('ignores invalid since date with warning', function () {
    $logContent = <<<'LOG'
[2026-03-11 10:00:00] production.INFO: Test log entry
LOG;

    createTestLogFile($logContent);

    $this->artisan('device:export-logs', ['--since' => 'invalid-date'])
        ->assertSuccessful()
        ->expectsOutputToContain('Could not parse');

    cleanupTestLogFile();
});

it('exports logs with different environments', function () {
    $logContent = <<<'LOG'
[2026-03-11 10:00:00] production.INFO: Production log
[2026-03-11 10:01:00] testing.INFO: Testing log
[2026-03-11 10:02:00] local.INFO: Local log
LOG;

    createTestLogFile($logContent);

    $this->artisan('device:export-logs')
        ->assertSuccessful();

    cleanupTestLogFile();
});

it('handles large log files efficiently', function () {
    // Create a log file with many entries
    $logContent = '';
    for ($i = 0; $i < 1000; $i++) {
        $logContent .= '[2026-03-11 '.sprintf('%02d', $i % 24).":00:00] production.INFO: Log entry {$i}\n";
    }

    createTestLogFile($logContent);

    $this->artisan('device:export-logs', ['--lines' => 100])
        ->assertSuccessful()
        ->expectsOutputToContain('Total entries: 100');

    cleanupTestLogFile();
});

it('creates output directory if it does not exist', function () {
    $logContent = '[2026-03-11 10:00:00] production.INFO: Test';
    createTestLogFile($logContent);

    $customDir = storage_path('logs/exports/deep/nested');
    $customPath = $customDir.'/export.zip';

    // Ensure directory doesn't exist
    if (is_dir(dirname($customDir))) {
        rmdir(dirname($customDir));
    }

    $this->artisan('device:export-logs', ['--output' => $customPath])
        ->assertSuccessful();

    expect(is_dir($customDir))->toBeTrue();
    expect(file_exists($customPath))->toBeTrue();

    // Cleanup
    if (file_exists($customPath)) {
        unlink($customPath);
    }
    @rmdir($customDir);
    @rmdir(dirname($customDir));
    @rmdir(dirname(dirname($customDir)));

    cleanupTestLogFile();
});

it('handles read permission errors gracefully', function () {
    $logPath = storage_path('logs/laravel.log');
    file_put_contents($logPath, '[2026-03-11 10:00:00] production.INFO: Test');
    chmod($logPath, 0000);

    $this->artisan('device:export-logs')
        ->assertFailed()
        ->expectsOutputToContain('Log file is not readable');

    chmod($logPath, 0644);
    cleanupTestLogFile();
});
