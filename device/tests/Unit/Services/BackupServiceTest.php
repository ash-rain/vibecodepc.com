<?php

use App\Services\BackupService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function () {
    Storage::fake('local');
});

describe('createBackup', function () {
    it('creates a backup file with valid zip structure', function () {
        DB::table('ai_providers')->insert([
            'provider' => 'openai',
            'api_key_encrypted' => encrypt('test-key'),
            'display_name' => 'OpenAI',
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $service = new BackupService;
        $zipPath = $service->createBackup();

        expect($zipPath)->toBeString()
            ->and(file_exists($zipPath))->toBeTrue()
            ->and(str_starts_with(basename($zipPath), 'backup-'))->toBeTrue()
            ->and(str_ends_with($zipPath, '.zip'))->toBeTrue();

        $zip = new ZipArchive;
        expect($zip->open($zipPath))->toBeTrue();
        expect($zip->locateName('backup.enc'))->not->toBeFalse();
        $zip->close();
    });

    it('encrypts backup data correctly', function () {
        DB::table('ai_providers')->insert([
            'provider' => 'anthropic',
            'api_key_encrypted' => encrypt('secret-key'),
            'display_name' => 'Anthropic',
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $service = new BackupService;
        $zipPath = $service->createBackup();

        $zip = new ZipArchive;
        $zip->open($zipPath);
        $encrypted = $zip->getFromName('backup.enc');
        $zip->close();

        expect($encrypted)->toBeString();

        $json = Crypt::decryptString($encrypted);
        $data = json_decode($json, true);

        expect($data)->toBeArray()
            ->and(isset($data['tables']))->toBeTrue()
            ->and(isset($data['created_at']))->toBeTrue();
    });

    it('includes all backup tables in the backup', function () {
        DB::table('ai_providers')->insert([
            'provider' => 'test-provider',
            'api_key_encrypted' => encrypt('key'),
            'status' => 'pending',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('tunnel_configs')->insert([
            'subdomain' => 'test-sub',
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('device_state')->insert([
            'key' => 'test_key',
            'value' => 'test_value',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $service = new BackupService;
        $zipPath = $service->createBackup();

        $zip = new ZipArchive;
        $zip->open($zipPath);
        $encrypted = $zip->getFromName('backup.enc');
        $zip->close();

        $data = json_decode(Crypt::decryptString($encrypted), true);

        expect($data['tables'])->toHaveKeys([
            'ai_providers',
            'tunnel_configs',
            'github_credentials',
            'device_state',
            'wizard_progress',
            'cloud_credentials',
        ]);

        expect($data['tables']['ai_providers'])->toHaveCount(1);
        expect($data['tables']['tunnel_configs'])->toHaveCount(1);
        expect($data['tables']['device_state'])->toHaveCount(1);
    });

    it('includes env file content when present', function () {
        $envContent = "APP_NAME=Test\nAPP_ENV=testing\n";
        $envPath = base_path('.env');
        $envBackupPath = base_path('.env.backup-test');

        if (file_exists($envPath)) {
            rename($envPath, $envBackupPath);
        }

        file_put_contents($envPath, $envContent);

        try {
            $service = new BackupService;
            $zipPath = $service->createBackup();

            $zip = new ZipArchive;
            $zip->open($zipPath);
            $encrypted = $zip->getFromName('backup.enc');
            $zip->close();

            $data = json_decode(Crypt::decryptString($encrypted), true);

            expect($data['env'])->toBe($envContent);
        } finally {
            if (file_exists($envPath)) {
                unlink($envPath);
            }
            if (file_exists($envBackupPath)) {
                rename($envBackupPath, $envPath);
            }
        }
    });

    it('handles missing env file gracefully', function () {
        $envPath = base_path('.env');
        $envBackupPath = base_path('.env.backup-test');

        if (file_exists($envPath)) {
            rename($envPath, $envBackupPath);
        }

        try {
            $service = new BackupService;
            $zipPath = $service->createBackup();

            $zip = new ZipArchive;
            $zip->open($zipPath);
            $encrypted = $zip->getFromName('backup.enc');
            $zip->close();

            $data = json_decode(Crypt::decryptString($encrypted), true);

            $hasNoEnv = ! isset($data['env']);
            $hasNullEnv = isset($data['env']) && $data['env'] === null;
            expect($hasNoEnv || $hasNullEnv)->toBeTrue();
        } finally {
            if (file_exists($envBackupPath)) {
                rename($envBackupPath, $envPath);
            }
        }
    });

    it('includes timestamp in backup data', function () {
        $testTime = now();
        \Carbon\Carbon::setTestNow($testTime);

        try {
            $service = new BackupService;
            $zipPath = $service->createBackup();

            $zip = new ZipArchive;
            $zip->open($zipPath);
            $encrypted = $zip->getFromName('backup.enc');
            $zip->close();

            $data = json_decode(Crypt::decryptString($encrypted), true);
            $backupTime = \Carbon\Carbon::parse($data['created_at']);

            expect($backupTime->toIso8601String())->toBe($testTime->toIso8601String());
        } finally {
            \Carbon\Carbon::setTestNow(null);
        }
    });
});

describe('restoreBackup', function () {
    it('restores data from a valid backup', function () {
        DB::table('ai_providers')->insert([
            'provider' => 'openai',
            'api_key_encrypted' => encrypt('original-key'),
            'display_name' => 'Original',
            'status' => 'active',
            'created_at' => now()->subDay(),
            'updated_at' => now()->subDay(),
        ]);

        $service = new BackupService;
        $zipPath = $service->createBackup();

        DB::table('ai_providers')->truncate();

        $service->restoreBackup($zipPath);

        $restored = DB::table('ai_providers')->first();
        expect($restored)->not->toBeNull()
            ->and($restored->provider)->toBe('openai')
            ->and($restored->display_name)->toBe('Original');
    });

    it('restores all backup tables', function () {
        DB::table('ai_providers')->insert([
            'provider' => 'provider-1',
            'api_key_encrypted' => encrypt('key1'),
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('tunnel_configs')->insert([
            'subdomain' => 'sub-1',
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('device_state')->insert([
            'key' => 'state-key',
            'value' => 'state-value',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $service = new BackupService;
        $zipPath = $service->createBackup();

        DB::table('ai_providers')->truncate();
        DB::table('tunnel_configs')->truncate();
        DB::table('device_state')->truncate();

        $service->restoreBackup($zipPath);

        expect(DB::table('ai_providers')->count())->toBe(1);
        expect(DB::table('tunnel_configs')->count())->toBe(1);
        expect(DB::table('device_state')->count())->toBe(1);
    });

    it('truncates existing data before restoring', function () {
        DB::table('ai_providers')->insert([
            'provider' => 'original',
            'api_key_encrypted' => encrypt('original'),
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $service = new BackupService;
        $zipPath = $service->createBackup();

        DB::table('ai_providers')->insert([
            'provider' => 'extra',
            'api_key_encrypted' => encrypt('extra'),
            'status' => 'pending',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        expect(DB::table('ai_providers')->count())->toBe(2);

        $service->restoreBackup($zipPath);

        expect(DB::table('ai_providers')->count())->toBe(1);
        expect(DB::table('ai_providers')->first()->provider)->toBe('original');
    });

    it('restores env file when present in backup', function () {
        $envContent = "APP_NAME=Restored\nAPP_KEY=restored-key\n";
        $envPath = base_path('.env');
        $envBackupPath = base_path('.env.backup-test');

        if (file_exists($envPath)) {
            rename($envPath, $envBackupPath);
        }

        file_put_contents($envPath, $envContent);

        try {
            $service = new BackupService;
            $zipPath = $service->createBackup();

            file_put_contents($envPath, "APP_NAME=Changed\n");

            $service->restoreBackup($zipPath);

            $restoredContent = file_get_contents($envPath);
            expect($restoredContent)->toBe($envContent);
        } finally {
            if (file_exists($envPath)) {
                unlink($envPath);
            }
            if (file_exists($envBackupPath)) {
                rename($envBackupPath, $envPath);
            }
        }
    });

    it('throws exception for non-existent zip file', function () {
        $service = new BackupService;

        expect(fn () => $service->restoreBackup('/nonexistent/backup.zip'))
            ->toThrow(\RuntimeException::class, 'Backup file does not exist.');
    });

    it('throws exception for zip missing backup.enc', function () {
        $zipPath = storage_path('app/private/invalid-backup.zip');
        $zip = new ZipArchive;
        $zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        $zip->addFromString('other.txt', 'not a backup');
        $zip->close();

        try {
            $service = new BackupService;

            expect(fn () => $service->restoreBackup($zipPath))
                ->toThrow(\RuntimeException::class, 'Invalid backup file — missing encrypted payload.');
        } finally {
            if (file_exists($zipPath)) {
                unlink($zipPath);
            }
        }
    });

    it('throws exception for invalid encrypted data', function () {
        $zipPath = storage_path('app/private/corrupted-backup.zip');
        $zip = new ZipArchive;
        $zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        $zip->addFromString('backup.enc', 'invalid-encrypted-data');
        $zip->close();

        try {
            $service = new BackupService;

            expect(fn () => $service->restoreBackup($zipPath))
                ->toThrow(\Illuminate\Contracts\Encryption\DecryptException::class);
        } finally {
            if (file_exists($zipPath)) {
                unlink($zipPath);
            }
        }
    });

    it('throws exception for invalid backup structure', function () {
        $zipPath = storage_path('app/private/bad-structure.zip');
        $zip = new ZipArchive;
        $zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        $json = json_encode(['not_tables' => 'data']);
        $zip->addFromString('backup.enc', Crypt::encryptString($json));
        $zip->close();

        try {
            $service = new BackupService;

            expect(fn () => $service->restoreBackup($zipPath))
                ->toThrow(\RuntimeException::class, 'Invalid backup data structure.');
        } finally {
            if (file_exists($zipPath)) {
                unlink($zipPath);
            }
        }
    });

    it('throws exception for tampered backup with invalid checksum', function () {
        DB::table('ai_providers')->insert([
            'provider' => 'test',
            'api_key_encrypted' => encrypt('key'),
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $service = new BackupService;
        $zipPath = $service->createBackup();

        $zip = new ZipArchive;
        $zip->open($zipPath);
        $encrypted = $zip->getFromName('backup.enc');
        $zip->close();

        $data = json_decode(Crypt::decryptString($encrypted), true);
        $data['tables']['ai_providers'][0]['provider'] = 'hacked';

        $tamperedZipPath = storage_path('app/private/tampered-backup.zip');
        $tamperedZip = new ZipArchive;
        $tamperedZip->open($tamperedZipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        $tamperedZip->addFromString('backup.enc', Crypt::encryptString(json_encode($data)));
        $tamperedZip->close();

        try {
            expect(fn () => $service->restoreBackup($tamperedZipPath))
                ->toThrow(\RuntimeException::class, 'Backup file integrity check failed');
        } finally {
            if (file_exists($tamperedZipPath)) {
                unlink($tamperedZipPath);
            }
        }
    });

    it('skips tables not in BACKUP_TABLES constant', function () {
        DB::table('ai_providers')->insert([
            'provider' => 'valid',
            'api_key_encrypted' => encrypt('key'),
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $service = new BackupService;
        $zipPath = $service->createBackup();

        $zip = new ZipArchive;
        $zip->open($zipPath);
        $encrypted = $zip->getFromName('backup.enc');
        $zip->close();

        $data = json_decode(Crypt::decryptString($encrypted), true);
        unset($data['checksum']);
        $data['tables']['unknown_table'] = [['id' => 1, 'data' => 'test']];
        $data['tables']['another_invalid'] = [['id' => 2]];
        $data['checksum'] = hash('sha256', json_encode($data, JSON_UNESCAPED_UNICODE));

        $newZipPath = storage_path('app/private/modified-backup.zip');
        $newZip = new ZipArchive;
        $newZip->open($newZipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        $newZip->addFromString('backup.enc', Crypt::encryptString(json_encode($data)));
        $newZip->close();

        try {
            DB::table('ai_providers')->truncate();

            $service->restoreBackup($newZipPath);

            expect(DB::table('ai_providers')->count())->toBe(1);
            expect(DB::table('ai_providers')->first()->provider)->toBe('valid');
        } finally {
            if (file_exists($newZipPath)) {
                unlink($newZipPath);
            }
        }
    });

    it('restores empty tables correctly', function () {
        $service = new BackupService;
        $zipPath = $service->createBackup();

        DB::table('ai_providers')->insert([
            'provider' => 'temp',
            'api_key_encrypted' => encrypt('temp'),
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $service->restoreBackup($zipPath);

        expect(DB::table('ai_providers')->count())->toBe(0);
    });
});

describe('backup zip validation', function () {
    it('creates zip with proper encryption', function () {
        DB::table('device_state')->insert([
            'key' => 'sensitive',
            'value' => 'secret-data',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $service = new BackupService;
        $zipPath = $service->createBackup();

        $zip = new ZipArchive;
        $zip->open($zipPath);
        $encrypted = $zip->getFromName('backup.enc');
        $zip->close();

        $rawZip = file_get_contents($zipPath);
        expect(strpos($rawZip, 'secret-data'))->toBeFalse('Sensitive data should not be visible in raw zip');
        expect(strpos($rawZip, 'sensitive'))->toBeFalse('Keys should not be visible in raw zip');

        $json = Crypt::decryptString($encrypted);
        $data = json_decode($json, true);
        expect($data['tables']['device_state'][0]['value'] ?? null)->toBe('secret-data');
    });

    it('validates backup integrity through full cycle', function () {
        $originalData = [
            ['provider' => 'openai', 'api_key_encrypted' => encrypt('key1'), 'display_name' => 'OpenAI', 'status' => 'active', 'created_at' => now(), 'updated_at' => now()],
            ['provider' => 'anthropic', 'api_key_encrypted' => encrypt('key2'), 'display_name' => 'Anthropic', 'status' => 'pending', 'created_at' => now(), 'updated_at' => now()],
        ];

        foreach ($originalData as $row) {
            DB::table('ai_providers')->insert($row);
        }

        DB::table('tunnel_configs')->insert([
            'subdomain' => 'my-subdomain',
            'tunnel_token_encrypted' => encrypt('token'),
            'tunnel_id' => 'tunnel-123',
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $service = new BackupService;
        $zipPath = $service->createBackup();

        DB::table('ai_providers')->truncate();
        DB::table('tunnel_configs')->truncate();

        $service->restoreBackup($zipPath);

        $restoredAi = DB::table('ai_providers')->get()->toArray();
        $restoredTunnel = DB::table('tunnel_configs')->first();

        expect(count($restoredAi))->toBe(2);
        expect($restoredAi[0]->provider)->toBe('openai');
        expect($restoredAi[1]->provider)->toBe('anthropic');
        expect($restoredTunnel->subdomain)->toBe('my-subdomain');
        expect($restoredTunnel->tunnel_id)->toBe('tunnel-123');
    });
});

describe('edge cases - corrupted backup files', function () {
    it('throws exception when zip file is not a valid zip archive', function () {
        $zipPath = storage_path('app/private/not-a-zip.zip');
        file_put_contents($zipPath, 'this is not a valid zip file content');

        try {
            $service = new BackupService;

            expect(fn () => $service->restoreBackup($zipPath))
                ->toThrow(\RuntimeException::class, 'Failed to open backup file');
        } finally {
            if (file_exists($zipPath)) {
                unlink($zipPath);
            }
        }
    });

    it('throws exception when backup.enc contains malformed JSON', function () {
        $zipPath = storage_path('app/private/malformed-json.zip');
        $zip = new ZipArchive;
        $zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        $zip->addFromString('backup.enc', Crypt::encryptString('not-valid-json'));
        $zip->close();

        try {
            $service = new BackupService;

            expect(fn () => $service->restoreBackup($zipPath))
                ->toThrow(\JsonException::class);
        } finally {
            if (file_exists($zipPath)) {
                unlink($zipPath);
            }
        }
    });

    it('throws exception when tables data is not an array', function () {
        $zipPath = storage_path('app/private/tables-not-array.zip');
        $zip = new ZipArchive;
        $zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        $json = json_encode(['tables' => 'not-an-array']);
        $zip->addFromString('backup.enc', Crypt::encryptString($json));
        $zip->close();

        try {
            $service = new BackupService;

            // PHP throws TypeError when trying to iterate over a non-array
            // Laravel wraps this in ErrorException
            expect(fn () => $service->restoreBackup($zipPath))
                ->toThrow(\ErrorException::class);
        } finally {
            if (file_exists($zipPath)) {
                unlink($zipPath);
            }
        }
    });

    it('throws exception when checksum is present but data is truncated', function () {
        $zipPath = storage_path('app/private/truncated-data.zip');
        $zip = new ZipArchive;
        $zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE);

        $data = ['tables' => [], 'created_at' => now()->toIso8601String(), 'version' => 1];
        $data['checksum'] = hash('sha256', json_encode($data, JSON_UNESCAPED_UNICODE));

        // Modify tables after checksum calculation to corrupt it
        $data['tables'] = ['corrupted' => 'data'];

        $zip->addFromString('backup.enc', Crypt::encryptString(json_encode($data)));
        $zip->close();

        try {
            $service = new BackupService;

            expect(fn () => $service->restoreBackup($zipPath))
                ->toThrow(\RuntimeException::class, 'Backup file integrity check failed');
        } finally {
            if (file_exists($zipPath)) {
                unlink($zipPath);
            }
        }
    });
});

describe('edge cases - disk full scenarios', function () {
    it('throws exception when backup file cannot be written', function () {
        // Skip if running as root (root can write to read-only directories)
        if (function_exists('posix_getuid') && posix_getuid() === 0) {
            $this->markTestSkipped('Skipping disk full test when running as root');
        }

        // Test if we can actually prevent writes by making storage/private read-only
        $privateDir = storage_path('app/private');
        $originalPerms = fileperms($privateDir);

        // Make directory read-only
        chmod($privateDir, 0555);

        // Test if we can actually prevent writes (some environments ignore permissions)
        $testFile = $privateDir.'/write-test-'.uniqid().'.txt';
        $writePrevented = @file_put_contents($testFile, 'test') === false;

        // Restore permissions before deciding whether to skip
        chmod($privateDir, $originalPerms);
        if (file_exists($testFile)) {
            unlink($testFile);
        }

        if (! $writePrevented) {
            $this->markTestSkipped('Environment does not support permission-based write prevention');
        }

        // Now actually run the test
        chmod($privateDir, 0555);

        try {
            $service = new BackupService;

            // This should fail because the directory is read-only
            expect(fn () => $service->createBackup())
                ->toThrow(\Exception::class);
        } finally {
            // Restore permissions for cleanup
            chmod($privateDir, $originalPerms);
        }
    });

    it('handles restore when env file cannot be written', function () {
        DB::table('ai_providers')->insert([
            'provider' => 'test',
            'api_key_encrypted' => encrypt('key'),
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $service = new BackupService;
        $zipPath = $service->createBackup();

        // Make the base path read-only for .env
        $basePath = base_path();
        chmod($basePath, 0555);

        try {
            // Should not throw - env file restoration failure is non-critical
            $service->restoreBackup($zipPath);

            // Database should still be restored
            expect(DB::table('ai_providers')->count())->toBe(1);
        } finally {
            chmod($basePath, 0755);
        }
    });

    it('throws exception when backup file is not readable', function () {
        $zipPath = storage_path('app/private/unreadable-backup.zip');
        $zip = new ZipArchive;
        $zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        $zip->addFromString('backup.enc', Crypt::encryptString(json_encode(['tables' => []])));
        $zip->close();

        // Make file unreadable
        chmod($zipPath, 0000);

        try {
            $service = new BackupService;

            expect(fn () => $service->restoreBackup($zipPath))
                ->toThrow(\RuntimeException::class, 'Backup file is not readable');
        } finally {
            chmod($zipPath, 0644);
            if (file_exists($zipPath)) {
                unlink($zipPath);
            }
        }
    });
});

describe('edge cases - large file handling', function () {
    it('handles large dataset backup and restore', function () {
        $largeData = [];
        for ($i = 0; $i < 1000; $i++) {
            $largeData[] = [
                'provider' => "provider-{$i}",
                'api_key_encrypted' => encrypt("key-{$i}"),
                'display_name' => "Provider {$i} with a very long name that contains lots of data",
                'status' => $i % 2 === 0 ? 'active' : 'pending',
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        DB::table('ai_providers')->insert($largeData);

        $service = new BackupService;
        $zipPath = $service->createBackup();

        DB::table('ai_providers')->truncate();

        $service->restoreBackup($zipPath);

        expect(DB::table('ai_providers')->count())->toBe(1000);
        expect(DB::table('ai_providers')->where('provider', 'provider-0')->exists())->toBeTrue();
        expect(DB::table('ai_providers')->where('provider', 'provider-999')->exists())->toBeTrue();
    });

    it('handles large env file in backup', function () {
        $envContent = str_repeat('LARGE_ENV_VAR=value_', 5000);
        $envPath = base_path('.env');
        $envBackupPath = base_path('.env.backup-test');

        if (file_exists($envPath)) {
            rename($envPath, $envBackupPath);
        }

        file_put_contents($envPath, $envContent);

        try {
            $service = new BackupService;
            $zipPath = $service->createBackup();

            file_put_contents($envPath, 'different-content');

            $service->restoreBackup($zipPath);

            $restoredContent = file_get_contents($envPath);
            expect($restoredContent)->toBe($envContent);
        } finally {
            if (file_exists($envPath)) {
                unlink($envPath);
            }
            if (file_exists($envBackupPath)) {
                rename($envBackupPath, $envPath);
            }
        }
    });

    it('handles records with large field values', function () {
        $largeValue = str_repeat('x', 10000);
        DB::table('device_state')->insert([
            'key' => 'large_key',
            'value' => $largeValue,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $service = new BackupService;
        $zipPath = $service->createBackup();

        DB::table('device_state')->truncate();

        $service->restoreBackup($zipPath);

        $restored = DB::table('device_state')->where('key', 'large_key')->first();
        expect($restored)->not->toBeNull()
            ->and($restored->value)->toBe($largeValue);
    });

    it('handles backup with many tables containing data', function () {
        // Clear wizard_progress table of seed data to ensure clean test state
        DB::table('wizard_progress')->truncate();

        // Insert data into all backup tables
        DB::table('ai_providers')->insert([
            'provider' => 'ai-test',
            'api_key_encrypted' => encrypt('key'),
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('tunnel_configs')->insert([
            'subdomain' => 'test-sub',
            'tunnel_token_encrypted' => encrypt('token'),
            'tunnel_id' => 'tunnel-123',
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('github_credentials')->insert([
            'github_username' => 'testuser',
            'access_token_encrypted' => encrypt('github-token'),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('device_state')->insert([
            ['key' => 'key1', 'value' => 'value1', 'created_at' => now(), 'updated_at' => now()],
            ['key' => 'key2', 'value' => 'value2', 'created_at' => now(), 'updated_at' => now()],
        ]);

        DB::table('wizard_progress')->insert([
            'step' => 'backup-test-'.uniqid(),
            'status' => 'completed',
            'data_json' => json_encode(['completed_steps' => [1, 2, 3]]),
            'completed_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('cloud_credentials')->insert([
            'pairing_token_encrypted' => encrypt('token'),
            'cloud_username' => 'testuser',
            'cloud_email' => 'test@example.com',
            'cloud_url' => 'https://cloud.example.com',
            'is_paired' => true,
            'paired_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $service = new BackupService;
        $zipPath = $service->createBackup();

        // Truncate all tables
        DB::table('ai_providers')->truncate();
        DB::table('tunnel_configs')->truncate();
        DB::table('github_credentials')->truncate();
        DB::table('device_state')->truncate();
        DB::table('wizard_progress')->truncate();
        DB::table('cloud_credentials')->truncate();

        $service->restoreBackup($zipPath);

        expect(DB::table('ai_providers')->count())->toBe(1);
        expect(DB::table('tunnel_configs')->count())->toBe(1);
        expect(DB::table('github_credentials')->count())->toBe(1);
        expect(DB::table('device_state')->count())->toBe(2);
        expect(DB::table('wizard_progress')->count())->toBe(1);
        expect(DB::table('cloud_credentials')->count())->toBe(1);
    });
});
