<?php

declare(strict_types=1);

describe('Environment Configuration Tests', function (): void {

    beforeEach(function (): void {
        // Store original environment variables
        $this->originalEnv = [
            'VIBECODEPC_BOOST_JSON_PATH' => getenv('VIBECODEPC_BOOST_JSON_PATH'),
            'OPENCODE_CONFIG_PATH' => getenv('OPENCODE_CONFIG_PATH'),
            'CLAUDE_CONFIG_PATH' => getenv('CLAUDE_CONFIG_PATH'),
            'CONFIG_EDITOR_BACKUP_RETENTION_DAYS' => getenv('CONFIG_EDITOR_BACKUP_RETENTION_DAYS'),
            'CONFIG_EDITOR_MAX_FILE_SIZE_KB' => getenv('CONFIG_EDITOR_MAX_FILE_SIZE_KB'),
            'CONFIG_EDITOR_BACKUP_DIR' => getenv('CONFIG_EDITOR_BACKUP_DIR'),
        ];

        // Store original config values
        $this->originalConfigFiles = config('vibecodepc.config_files');
        $this->originalConfigEditor = config('vibecodepc.config_editor');
    });

    afterEach(function (): void {
        // Restore original environment variables
        foreach ($this->originalEnv as $key => $value) {
            if ($value === false) {
                putenv($key);
            } else {
                putenv("{$key}={$value}");
            }
        }

        // Restore original config
        config()->set('vibecodepc.config_files', $this->originalConfigFiles);
        config()->set('vibecodepc.config_editor', $this->originalConfigEditor);
    });

    describe('VIBECODEPC_BOOST_JSON_PATH', function (): void {
        test('uses default path when environment variable is not set', function (): void {
            putenv('VIBECODEPC_BOOST_JSON_PATH');

            $config = require config_path('vibecodepc.php');

            expect($config['config_files']['boost']['path'])->toBe(base_path('boost.json'));
        });

        test('uses custom path when environment variable is set', function (): void {
            $customPath = '/custom/path/to/boost.json';
            putenv("VIBECODEPC_BOOST_JSON_PATH={$customPath}");

            $config = require config_path('vibecodepc.php');

            expect($config['config_files']['boost']['path'])->toBe($customPath);
        });

        test('handles path with spaces', function (): void {
            $customPath = '/custom/path with spaces/boost.json';
            putenv("VIBECODEPC_BOOST_JSON_PATH={$customPath}");

            $config = require config_path('vibecodepc.php');

            expect($config['config_files']['boost']['path'])->toBe($customPath);
        });

        test('handles empty string as empty value', function (): void {
            putenv('VIBECODEPC_BOOST_JSON_PATH=');

            $config = require config_path('vibecodepc.php');

            // Laravel's env() treats empty string as a valid value, not as "not set"
            expect($config['config_files']['boost']['path'])->toBe('');
        });

        test('handles very long path', function (): void {
            $longPath = '/very/long/path'.str_repeat('/directory', 50).'/boost.json';
            putenv("VIBECODEPC_BOOST_JSON_PATH={$longPath}");

            $config = require config_path('vibecodepc.php');

            expect($config['config_files']['boost']['path'])->toBe($longPath);
        });

        test('handles unicode in path', function (): void {
            $unicodePath = '/custom/путь/日本語/boost.json';
            putenv("VIBECODEPC_BOOST_JSON_PATH={$unicodePath}");

            $config = require config_path('vibecodepc.php');

            expect($config['config_files']['boost']['path'])->toBe($unicodePath);
        });

        test('handles relative path', function (): void {
            $relativePath = 'config/boost.json';
            putenv("VIBECODEPC_BOOST_JSON_PATH={$relativePath}");

            $config = require config_path('vibecodepc.php');

            expect($config['config_files']['boost']['path'])->toBe($relativePath);
        });
    });

    describe('OPENCODE_CONFIG_PATH', function (): void {
        test('uses default path when environment variable is not set', function (): void {
            putenv('OPENCODE_CONFIG_PATH');

            $config = require config_path('vibecodepc.php');
            $homeDir = $_SERVER['HOME'] ?? '/home/vibecodepc';
            $expectedPath = $homeDir.'/.config/opencode/opencode.json';

            expect($config['config_files']['opencode_global']['path'])->toBe($expectedPath);
        });

        test('uses custom path when environment variable is set', function (): void {
            $customPath = '/custom/opencode/config.json';
            putenv("OPENCODE_CONFIG_PATH={$customPath}");

            $config = require config_path('vibecodepc.php');

            expect($config['config_files']['opencode_global']['path'])->toBe($customPath);
        });

        test('handles path with spaces', function (): void {
            $customPath = '/custom path/opencode/config.json';
            putenv("OPENCODE_CONFIG_PATH={$customPath}");

            $config = require config_path('vibecodepc.php');

            expect($config['config_files']['opencode_global']['path'])->toBe($customPath);
        });

        test('handles relative path', function (): void {
            $relativePath = 'config/opencode.json';
            putenv("OPENCODE_CONFIG_PATH={$relativePath}");

            $config = require config_path('vibecodepc.php');

            expect($config['config_files']['opencode_global']['path'])->toBe($relativePath);
        });
    });

    describe('CLAUDE_CONFIG_PATH', function (): void {
        test('uses default path when environment variable is not set', function (): void {
            putenv('CLAUDE_CONFIG_PATH');

            $config = require config_path('vibecodepc.php');
            $homeDir = $_SERVER['HOME'] ?? '/home/vibecodepc';
            $expectedPath = $homeDir.'/.claude/settings.json';

            expect($config['config_files']['claude_global']['path'])->toBe($expectedPath);
        });

        test('uses custom path when environment variable is set', function (): void {
            $customPath = '/custom/claude/settings.json';
            putenv("CLAUDE_CONFIG_PATH={$customPath}");

            $config = require config_path('vibecodepc.php');

            expect($config['config_files']['claude_global']['path'])->toBe($customPath);
        });

        test('handles path with spaces', function (): void {
            $customPath = '/custom path/claude/settings.json';
            putenv("CLAUDE_CONFIG_PATH={$customPath}");

            $config = require config_path('vibecodepc.php');

            expect($config['config_files']['claude_global']['path'])->toBe($customPath);
        });
    });

    describe('CONFIG_EDITOR_BACKUP_RETENTION_DAYS', function (): void {
        test('uses default value when environment variable is not set', function (): void {
            putenv('CONFIG_EDITOR_BACKUP_RETENTION_DAYS');

            $config = require config_path('vibecodepc.php');

            expect($config['config_editor']['backup_retention_days'])->toBe(30);
        });

        test('uses custom value when environment variable is set', function (): void {
            putenv('CONFIG_EDITOR_BACKUP_RETENTION_DAYS=60');

            $config = require config_path('vibecodepc.php');

            expect($config['config_editor']['backup_retention_days'])->toBe(60);
        });

        test('handles zero value', function (): void {
            putenv('CONFIG_EDITOR_BACKUP_RETENTION_DAYS=0');

            $config = require config_path('vibecodepc.php');

            expect($config['config_editor']['backup_retention_days'])->toBe(0);
        });

        test('handles negative value', function (): void {
            putenv('CONFIG_EDITOR_BACKUP_RETENTION_DAYS=-1');

            $config = require config_path('vibecodepc.php');

            expect($config['config_editor']['backup_retention_days'])->toBe(-1);
        });

        test('handles large value', function (): void {
            putenv('CONFIG_EDITOR_BACKUP_RETENTION_DAYS=3650');

            $config = require config_path('vibecodepc.php');

            expect($config['config_editor']['backup_retention_days'])->toBe(3650);
        });

        test('converts string to integer', function (): void {
            putenv('CONFIG_EDITOR_BACKUP_RETENTION_DAYS=45');

            $config = require config_path('vibecodepc.php');

            expect($config['config_editor']['backup_retention_days'])->toBeInt();
        });

        test('handles invalid string returns 0', function (): void {
            putenv('CONFIG_EDITOR_BACKUP_RETENTION_DAYS=invalid');

            $config = require config_path('vibecodepc.php');

            // PHP (int) cast of 'invalid' is 0
            expect($config['config_editor']['backup_retention_days'])->toBe(0);
        });

        test('handles empty string returns 0', function (): void {
            putenv('CONFIG_EDITOR_BACKUP_RETENTION_DAYS=');

            $config = require config_path('vibecodepc.php');

            expect($config['config_editor']['backup_retention_days'])->toBe(0);
        });
    });

    describe('CONFIG_EDITOR_MAX_FILE_SIZE_KB', function (): void {
        test('uses default value when environment variable is not set', function (): void {
            putenv('CONFIG_EDITOR_MAX_FILE_SIZE_KB');

            $config = require config_path('vibecodepc.php');

            expect($config['config_editor']['max_file_size_kb'])->toBe(64);
        });

        test('uses custom value when environment variable is set', function (): void {
            putenv('CONFIG_EDITOR_MAX_FILE_SIZE_KB=128');

            $config = require config_path('vibecodepc.php');

            expect($config['config_editor']['max_file_size_kb'])->toBe(128);
        });

        test('handles zero value', function (): void {
            putenv('CONFIG_EDITOR_MAX_FILE_SIZE_KB=0');

            $config = require config_path('vibecodepc.php');

            expect($config['config_editor']['max_file_size_kb'])->toBe(0);
        });

        test('handles negative value', function (): void {
            putenv('CONFIG_EDITOR_MAX_FILE_SIZE_KB=-10');

            $config = require config_path('vibecodepc.php');

            expect($config['config_editor']['max_file_size_kb'])->toBe(-10);
        });

        test('handles large value', function (): void {
            putenv('CONFIG_EDITOR_MAX_FILE_SIZE_KB=10240');

            $config = require config_path('vibecodepc.php');

            expect($config['config_editor']['max_file_size_kb'])->toBe(10240);
        });

        test('converts string to integer', function (): void {
            putenv('CONFIG_EDITOR_MAX_FILE_SIZE_KB=256');

            $config = require config_path('vibecodepc.php');

            expect($config['config_editor']['max_file_size_kb'])->toBeInt();
        });

        test('handles invalid string returns 0', function (): void {
            putenv('CONFIG_EDITOR_MAX_FILE_SIZE_KB=invalid');

            $config = require config_path('vibecodepc.php');

            // PHP (int) cast of 'invalid' is 0
            expect($config['config_editor']['max_file_size_kb'])->toBe(0);
        });

        test('handles empty string returns 0', function (): void {
            putenv('CONFIG_EDITOR_MAX_FILE_SIZE_KB=');

            $config = require config_path('vibecodepc.php');

            expect($config['config_editor']['max_file_size_kb'])->toBe(0);
        });
    });

    describe('CONFIG_EDITOR_BACKUP_DIR', function (): void {
        test('uses default path when environment variable is not set', function (): void {
            putenv('CONFIG_EDITOR_BACKUP_DIR');

            $config = require config_path('vibecodepc.php');
            $expectedPath = storage_path('app/backups/config');

            expect($config['config_editor']['backup_directory'])->toBe($expectedPath);
        });

        test('uses custom path when environment variable is set', function (): void {
            $customPath = '/custom/backup/directory';
            putenv("CONFIG_EDITOR_BACKUP_DIR={$customPath}");

            $config = require config_path('vibecodepc.php');

            expect($config['config_editor']['backup_directory'])->toBe($customPath);
        });

        test('handles path with spaces', function (): void {
            $customPath = '/custom path/backup directory';
            putenv("CONFIG_EDITOR_BACKUP_DIR={$customPath}");

            $config = require config_path('vibecodepc.php');

            expect($config['config_editor']['backup_directory'])->toBe($customPath);
        });

        test('handles relative path', function (): void {
            $relativePath = 'storage/backups/custom';
            putenv("CONFIG_EDITOR_BACKUP_DIR={$relativePath}");

            $config = require config_path('vibecodepc.php');

            expect($config['config_editor']['backup_directory'])->toBe($relativePath);
        });

        test('handles unicode in path', function (): void {
            $unicodePath = '/custom/backup/バックアップ/路径';
            putenv("CONFIG_EDITOR_BACKUP_DIR={$unicodePath}");

            $config = require config_path('vibecodepc.php');

            expect($config['config_editor']['backup_directory'])->toBe($unicodePath);
        });

        test('handles empty string as empty value', function (): void {
            putenv('CONFIG_EDITOR_BACKUP_DIR=');

            $config = require config_path('vibecodepc.php');

            // Laravel's env() treats empty string as a valid value, not as "not set"
            expect($config['config_editor']['backup_directory'])->toBe('');
        });
    });

    describe('Environment Variable Interactions', function (): void {
        test('multiple environment variables can be set simultaneously', function (): void {
            putenv('VIBECODEPC_BOOST_JSON_PATH=/custom/boost.json');
            putenv('OPENCODE_CONFIG_PATH=/custom/opencode.json');
            putenv('CONFIG_EDITOR_BACKUP_RETENTION_DAYS=90');
            putenv('CONFIG_EDITOR_MAX_FILE_SIZE_KB=256');
            putenv('CONFIG_EDITOR_BACKUP_DIR=/custom/backups');

            $config = require config_path('vibecodepc.php');

            expect($config['config_files']['boost']['path'])->toBe('/custom/boost.json');
            expect($config['config_files']['opencode_global']['path'])->toBe('/custom/opencode.json');
            expect($config['config_editor']['backup_retention_days'])->toBe(90);
            expect($config['config_editor']['max_file_size_kb'])->toBe(256);
            expect($config['config_editor']['backup_directory'])->toBe('/custom/backups');
        });

        test('environment variables override defaults independently', function (): void {
            putenv('VIBECODEPC_BOOST_JSON_PATH=/custom/boost.json');
            // Leave other variables unset to use defaults

            $config = require config_path('vibecodepc.php');

            // Custom value
            expect($config['config_files']['boost']['path'])->toBe('/custom/boost.json');

            // Default values for others
            $homeDir = $_SERVER['HOME'] ?? '/home/vibecodepc';
            expect($config['config_files']['opencode_global']['path'])->toBe($homeDir.'/.config/opencode/opencode.json');
            expect($config['config_editor']['backup_retention_days'])->toBe(30);
        });

        test('unsetting one variable does not affect others', function (): void {
            putenv('VIBECODEPC_BOOST_JSON_PATH=/custom/boost.json');
            putenv('CONFIG_EDITOR_BACKUP_RETENTION_DAYS=90');

            // Unset only one variable
            putenv('VIBECODEPC_BOOST_JSON_PATH');

            $config = require config_path('vibecodepc.php');

            // This should now use default
            expect($config['config_files']['boost']['path'])->toBe(base_path('boost.json'));

            // This should still use custom value
            expect($config['config_editor']['backup_retention_days'])->toBe(90);
        });
    });

    describe('Environment Variable Validation', function (): void {
        test('path environment variables with null bytes are truncated by putenv', function (): void {
            $pathWithNull = "/custom/path\x00/boost.json";
            putenv("VIBECODEPC_BOOST_JSON_PATH={$pathWithNull}");

            $config = require config_path('vibecodepc.php');

            // putenv() treats null byte as string terminator, so path is truncated
            expect($config['config_files']['boost']['path'])->toBe('/custom/path');
        });

        test('numeric environment variables handle floats', function (): void {
            putenv('CONFIG_EDITOR_BACKUP_RETENTION_DAYS=30.5');

            $config = require config_path('vibecodepc.php');

            // Should truncate to integer
            expect($config['config_editor']['backup_retention_days'])->toBe(30);
        });

        test('numeric environment variables handle scientific notation', function (): void {
            putenv('CONFIG_EDITOR_MAX_FILE_SIZE_KB=1e3');

            $config = require config_path('vibecodepc.php');

            // Should convert scientific notation
            expect($config['config_editor']['max_file_size_kb'])->toBe(1000);
        });

        test('handles extremely long environment variable value', function (): void {
            $longPath = '/path'.str_repeat('/segment', 1000).'/boost.json';
            putenv("VIBECODEPC_BOOST_JSON_PATH={$longPath}");

            $config = require config_path('vibecodepc.php');

            expect($config['config_files']['boost']['path'])->toBe($longPath);
        });
    });
});
