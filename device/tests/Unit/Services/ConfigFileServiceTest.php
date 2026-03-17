<?php

declare(strict_types=1);

use App\Services\ConfigFileService;
use Illuminate\Support\Facades\File;

beforeEach(function (): void {
    $this->service = new ConfigFileService;

    $this->testDir = storage_path('testing/config');
    $this->backupDir = storage_path('testing/backups');

    if (! File::isDirectory($this->testDir)) {
        File::makeDirectory($this->testDir, 0755, true);
    }
    if (! File::isDirectory($this->backupDir)) {
        File::makeDirectory($this->backupDir, 0755, true);
    }

    config()->set('vibecodepc.config_files', [
        'test_config' => [
            'path' => $this->testDir.'/test.json',
            'label' => 'Test Config',
            'description' => 'Test configuration file',
            'editable' => true,
        ],
        'copilot_instructions' => [
            'path' => $this->testDir.'/copilot.md',
            'label' => 'Copilot Instructions',
            'description' => 'Copilot instructions file',
            'editable' => true,
        ],
    ]);

    config()->set('vibecodepc.config_editor', [
        'backup_retention_days' => 30,
        'max_file_size_kb' => 64,
        'backup_directory' => $this->backupDir,
    ]);
});

afterEach(function (): void {
    if (File::isDirectory($this->testDir)) {
        File::deleteDirectory($this->testDir);
    }
    if (File::isDirectory($this->backupDir)) {
        File::deleteDirectory($this->backupDir);
    }
});

describe('ConfigFileService', function (): void {
    test('getContent returns empty string for non-existent file', function (): void {
        $content = $this->service->getContent('test_config');

        expect($content)->toBe('');
    });

    test('getContent returns file content for existing file', function (): void {
        File::put($this->testDir.'/test.json', '{"key": "value"}', true);

        $content = $this->service->getContent('test_config');

        expect($content)->toBe('{"key": "value"}');
    });

    test('getContent throws exception for unreadable file', function (): void {
        File::put($this->testDir.'/test.json', '{"key": "value"}', true);
        chmod($this->testDir.'/test.json', 0000);

        expect(fn () => $this->service->getContent('test_config'))
            ->toThrow(\RuntimeException::class, 'Configuration file is not readable');

        chmod($this->testDir.'/test.json', 0644);
    });

    test('putContent writes content to file', function (): void {
        $this->service->putContent('test_config', '{"key": "value"}');

        expect(File::exists($this->testDir.'/test.json'))->toBeTrue();
        expect(File::get($this->testDir.'/test.json'))->toBe('{"key": "value"}');
    });

    test('putContent creates backup before overwriting', function (): void {
        File::put($this->testDir.'/test.json', '{"original": "data"}', true);

        $this->service->putContent('test_config', '{"key": "value"}');

        $backups = $this->service->listBackups('test_config');
        expect($backups)->toHaveCount(1);
        expect(File::get($backups[0]['path']))->toBe('{"original": "data"}');
    });

    test('putContent validates JSON for non-markdown files', function (): void {
        expect(fn () => $this->service->putContent('test_config', 'not valid json'))
            ->toThrow(\JsonException::class);
    });

    test('putContent skips JSON validation for copilot instructions', function (): void {
        $this->service->putContent('copilot_instructions', '# Instructions\n\nThis is markdown.');

        expect(File::exists($this->testDir.'/copilot.md'))->toBeTrue();
        expect(File::get($this->testDir.'/copilot.md'))->toBe('# Instructions\n\nThis is markdown.');
    });

    test('putContent throws exception for oversized content', function (): void {
        $largeContent = str_repeat('x', 100 * 1024);

        expect(fn () => $this->service->putContent('test_config', $largeContent))
            ->toThrow(\InvalidArgumentException::class, 'exceeds maximum allowed size');
    });

    test('putContent throws exception for unknown key', function (): void {
        expect(fn () => $this->service->putContent('unknown_key', '{}'))
            ->toThrow(\InvalidArgumentException::class, 'Unknown configuration key');
    });

    test('validateJson validates valid JSON', function (): void {
        $result = $this->service->validateJson('{"key": "value"}');

        expect($result)->toBe(['key' => 'value']);
    });

    test('validateJson validates JSON with comments', function (): void {
        $jsonc = "{\n  // This is a comment\n  \"key\": \"value\"\n}";
        $result = $this->service->validateJson($jsonc);

        expect($result)->toBe(['key' => 'value']);
    });

    test('validateJson throws exception for invalid JSON', function (): void {
        expect(fn () => $this->service->validateJson('not valid json'))
            ->toThrow(\JsonException::class);
    });

    test('validateJson validates boost.json structure', function (): void {
        config()->set('vibecodepc.config_files.boost', [
            'path' => $this->testDir.'/boost.json',
            'label' => 'Boost',
            'description' => 'Boost configuration',
            'editable' => true,
        ]);

        $validBoost = [
            'agents' => ['claude_code', 'copilot'],
            'guidelines' => true,
            'skills' => ['livewire-development'],
        ];

        $result = $this->service->validateJson(json_encode($validBoost), 'boost');

        expect($result)->toBe($validBoost);
    });

    test('validateJson throws exception for invalid boost.json agents type', function (): void {
        config()->set('vibecodepc.config_files.boost', [
            'path' => $this->testDir.'/boost.json',
            'label' => 'Boost',
            'description' => 'Boost configuration',
            'editable' => true,
        ]);

        $invalidBoost = ['agents' => 'not an array'];

        expect(fn () => $this->service->validateJson(json_encode($invalidBoost), 'boost'))
            ->toThrow(\InvalidArgumentException::class, 'agents" must be an array');
    });

    test('backup creates backup file', function (): void {
        File::put($this->testDir.'/test.json', '{"key": "value"}', true);

        $backupPath = $this->service->backup('test_config');

        expect(File::exists($backupPath))->toBeTrue();
        expect(File::get($backupPath))->toBe('{"key": "value"}');
    });

    test('backup throws exception for non-existent file', function (): void {
        expect(fn () => $this->service->backup('test_config'))
            ->toThrow(\RuntimeException::class, 'Cannot backup non-existent file');
    });

    test('listBackups returns sorted backups', function (): void {
        File::put($this->testDir.'/test.json', 'v1', true);
        sleep(1);
        $this->service->backup('test_config');

        File::put($this->testDir.'/test.json', 'v2', true);
        sleep(1);
        $this->service->backup('test_config');

        $backups = $this->service->listBackups('test_config');

        expect($backups)->toHaveCount(2);
        expect(File::get($backups[0]['path']))->toBe('v2');
        expect(File::get($backups[1]['path']))->toBe('v1');
    });

    test('listBackups returns empty array when no backups exist', function (): void {
        $backups = $this->service->listBackups('test_config');

        expect($backups)->toBe([]);
    });

    test('restore overwrites file with backup content', function (): void {
        File::put($this->testDir.'/test.json', 'original', true);
        $backupPath = $this->service->backup('test_config');
        File::put($this->testDir.'/test.json', 'changed', true);

        $this->service->restore('test_config', $backupPath);

        expect(File::get($this->testDir.'/test.json'))->toBe('original');
    });

    test('restore throws exception for non-existent backup', function (): void {
        expect(fn () => $this->service->restore('test_config', '/nonexistent/backup.json'))
            ->toThrow(\RuntimeException::class, 'Backup file does not exist');
    });

    test('exists returns true for readable file', function (): void {
        File::put($this->testDir.'/test.json', '{}', true);

        expect($this->service->exists('test_config'))->toBeTrue();
    });

    test('exists returns false for non-existent file', function (): void {
        expect($this->service->exists('test_config'))->toBeFalse();
    });
});
