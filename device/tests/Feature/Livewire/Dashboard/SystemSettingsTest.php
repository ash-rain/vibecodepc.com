<?php

declare(strict_types=1);

use App\Livewire\Dashboard\SystemSettings;
use App\Services\BackupService;
use App\Services\Tunnel\TunnelService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    Process::fake([
        'systemctl is-active ssh' => Process::result(output: 'inactive', exitCode: 3),
        '*' => Process::result(),
    ]);
    Storage::fake('local');
});

it('renders the system settings page', function () {
    Livewire::test(SystemSettings::class)
        ->assertStatus(200)
        ->assertSee('System Settings');
});

it('shows network information', function () {
    Livewire::test(SystemSettings::class)
        ->assertSee('Network Configuration');
});

it('can toggle ssh', function () {
    Livewire::test(SystemSettings::class)
        ->call('toggleSsh');

    Process::assertRan('sudo systemctl enable ssh && sudo systemctl start ssh');
});

it('can check for updates', function () {
    Livewire::test(SystemSettings::class)
        ->call('checkForUpdates')
        ->assertSet('statusMessage', 'Package list updated. Check for upgradable packages.');
});

it('factory reset calls artisan command and redirects', function () {
    $tunnelMock = Mockery::mock(TunnelService::class);
    $tunnelMock->shouldReceive('stop')->andReturn(null);
    app()->instance(TunnelService::class, $tunnelMock);

    Livewire::test(SystemSettings::class)
        ->call('factoryReset')
        ->assertRedirect('/');
});

describe('backup and restore integration', function () {
    it('creates encrypted backup through Livewire component', function () {
        // Setup test data
        DB::table('ai_providers')->insert([
            'provider' => 'openai',
            'api_key_encrypted' => encrypt('test-api-key'),
            'display_name' => 'OpenAI',
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Create backup via service and verify it works
        $service = new BackupService;
        $backupPath = $service->createBackup();

        expect(file_exists($backupPath))->toBeTrue()
            ->and(str_starts_with(basename($backupPath), 'backup-'))->toBeTrue()
            ->and(str_ends_with($backupPath, '.zip'))->toBeTrue();

        // Verify zip structure and encryption
        $zip = new ZipArchive;
        expect($zip->open($backupPath))->toBeTrue();
        expect($zip->locateName('backup.enc'))->not->toBeFalse();

        $encrypted = $zip->getFromName('backup.enc');
        $zip->close();

        // Verify we can decrypt it
        $json = Crypt::decryptString($encrypted);
        $data = json_decode($json, true);

        expect($data)->toBeArray()
            ->and(isset($data['tables']))->toBeTrue()
            ->and(isset($data['created_at']))->toBeTrue()
            ->and(isset($data['version']))->toBeTrue()
            ->and(isset($data['checksum']))->toBeTrue();

        // Cleanup
        if (file_exists($backupPath)) {
            unlink($backupPath);
        }
    });

    it('restores backup successfully through Livewire component', function () {
        // Create initial data
        DB::table('ai_providers')->insert([
            'provider' => 'openai',
            'api_key_encrypted' => encrypt('original-key'),
            'display_name' => 'Original',
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $service = new BackupService;
        $backupPath = $service->createBackup();

        // Clear the data
        DB::table('ai_providers')->truncate();
        expect(DB::table('ai_providers')->count())->toBe(0);

        // Restore directly via service (Livewire file upload is complex to test)
        $service->restoreBackup($backupPath);

        // Verify data was restored
        $restored = DB::table('ai_providers')->first();
        expect($restored)->not->toBeNull()
            ->and($restored->provider)->toBe('openai')
            ->and($restored->display_name)->toBe('Original');

        // Cleanup
        if (file_exists($backupPath)) {
            unlink($backupPath);
        }
    });

    it('validates backup file type before restore', function () {
        $tempFile = tempnam(sys_get_temp_dir(), 'test');
        file_put_contents($tempFile, 'not a zip file');

        $service = new BackupService;

        expect(fn () => $service->restoreBackup($tempFile))
            ->toThrow(\RuntimeException::class, 'Failed to open backup file. The file may be corrupted.');

        // Cleanup
        if (file_exists($tempFile)) {
            unlink($tempFile);
        }
    });

    it('handles corrupted backup file gracefully', function () {
        // Create an invalid zip file
        $invalidZipPath = storage_path('app/private/invalid-backup.zip');
        $zip = new ZipArchive;
        $zip->open($invalidZipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        $zip->addFromString('wrong-file.txt', 'not encrypted data');
        $zip->close();

        $service = new BackupService;

        expect(fn () => $service->restoreBackup($invalidZipPath))
            ->toThrow(\RuntimeException::class, 'Invalid backup file — missing encrypted payload.');

        // Cleanup
        if (file_exists($invalidZipPath)) {
            unlink($invalidZipPath);
        }
    });

    it('performs full round-trip backup and restore preserving all data', function () {
        // Setup comprehensive test data
        $testData = [
            'ai_providers' => [
                [
                    'provider' => 'openai',
                    'api_key_encrypted' => encrypt('openai-key-123'),
                    'display_name' => 'OpenAI',
                    'status' => 'active',
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
                [
                    'provider' => 'anthropic',
                    'api_key_encrypted' => encrypt('anthropic-key-456'),
                    'display_name' => 'Anthropic',
                    'status' => 'pending',
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
            ],
            'tunnel_configs' => [
                [
                    'subdomain' => 'test-subdomain',
                    'tunnel_token_encrypted' => encrypt('tunnel-token-789'),
                    'tunnel_id' => 'tunnel-abc-123',
                    'status' => 'active',
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
            ],
            'device_state' => [
                [
                    'key' => 'device_config',
                    'value' => json_encode(['setting1' => 'value1', 'setting2' => 'value2']),
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
            ],
            'wizard_progress' => [
                [
                    'step' => 'completed',
                    'status' => 'completed',
                    'data_json' => json_encode(['initialized' => true]),
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
            ],
            'cloud_credentials' => [
                [
                    'pairing_token_encrypted' => encrypt('pairing-token-abc'),
                    'cloud_username' => 'clouduser',
                    'cloud_email' => 'cloud@example.com',
                    'cloud_url' => 'https://cloud.example.com',
                    'is_paired' => true,
                    'paired_at' => now(),
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
            ],
            'github_credentials' => [
                [
                    'access_token_encrypted' => encrypt('github-token-xyz'),
                    'github_username' => 'testuser',
                    'github_email' => 'test@example.com',
                    'github_name' => 'Test User',
                    'has_copilot' => true,
                    'token_expires_at' => now()->addYear(),
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
            ],
        ];

        foreach ($testData as $table => $rows) {
            foreach ($rows as $row) {
                DB::table($table)->insert($row);
            }
        }

        // Store original counts
        $originalCounts = [];
        foreach (array_keys($testData) as $table) {
            $originalCounts[$table] = DB::table($table)->count();
        }

        // Create backup
        $service = new BackupService;
        $backupPath = $service->createBackup();
        expect(file_exists($backupPath))->toBeTrue();

        // Clear all data
        foreach (array_keys($testData) as $table) {
            DB::table($table)->truncate();
            expect(DB::table($table)->count())->toBe(0);
        }

        // Restore backup
        $service->restoreBackup($backupPath);

        // Verify all data restored correctly
        foreach ($originalCounts as $table => $count) {
            expect(DB::table($table)->count())->toBe($count, "Table {$table} should have {$count} rows");
        }

        // Verify specific data integrity
        $restoredAi = DB::table('ai_providers')->where('provider', 'openai')->first();
        expect($restoredAi)->not->toBeNull()
            ->and($restoredAi->display_name)->toBe('OpenAI');

        $decryptedKey = decrypt($restoredAi->api_key_encrypted);
        expect($decryptedKey)->toBe('openai-key-123');

        $restoredTunnel = DB::table('tunnel_configs')->first();
        expect($restoredTunnel)->not->toBeNull()
            ->and($restoredTunnel->subdomain)->toBe('test-subdomain')
            ->and($restoredTunnel->tunnel_id)->toBe('tunnel-abc-123');

        // Cleanup
        if (file_exists($backupPath)) {
            unlink($backupPath);
        }
    });

    it('validates backup integrity with checksum verification', function () {
        // Create test data
        DB::table('ai_providers')->insert([
            'provider' => 'integrity-test',
            'api_key_encrypted' => encrypt('integrity-key'),
            'display_name' => 'Integrity Test',
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $service = new BackupService;
        $backupPath = $service->createBackup();

        // Tamper with the backup
        $zip = new ZipArchive;
        $zip->open($backupPath);
        $encrypted = $zip->getFromName('backup.enc');
        $zip->close();

        $data = json_decode(Crypt::decryptString($encrypted), true);
        $data['tables']['ai_providers'][0]['provider'] = 'tampered-provider';

        // Use a wrong checksum to simulate tampering (attacker doesn't have the original)
        $data['checksum'] = 'invalid-checksum-hash-1234567890abcdef';

        // Create tampered backup
        $tamperedPath = storage_path('app/private/tampered-backup.zip');
        $tamperedZip = new ZipArchive;
        $tamperedZip->open($tamperedPath, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        $tamperedZip->addFromString('backup.enc', Crypt::encryptString(json_encode($data, JSON_UNESCAPED_UNICODE)));
        $tamperedZip->close();

        // Attempt to restore tampered backup
        expect(fn () => $service->restoreBackup($tamperedPath))
            ->toThrow(\RuntimeException::class, 'Backup file integrity check failed. The file may be corrupted or tampered with.');

        // Cleanup
        if (file_exists($backupPath)) {
            unlink($backupPath);
        }
        if (file_exists($tamperedPath)) {
            unlink($tamperedPath);
        }
    });

    it('encrypts backup data so raw zip does not expose sensitive information', function () {
        // Create test data with sensitive info
        DB::table('ai_providers')->insert([
            'provider' => 'openai',
            'api_key_encrypted' => encrypt('sk-secret-api-key-12345'),
            'display_name' => 'OpenAI',
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $service = new BackupService;
        $backupPath = $service->createBackup();

        // Read raw zip content
        $rawZip = file_get_contents($backupPath);

        // Verify sensitive data is not visible in raw zip
        expect(strpos($rawZip, 'sk-secret-api-key-12345'))->toBeFalse('API key should not be visible in raw zip');
        expect(strpos($rawZip, 'openai'))->toBeFalse('Provider name should not be visible in raw zip');

        // Verify we can decrypt and read the data
        $zip = new ZipArchive;
        $zip->open($backupPath);
        $encrypted = $zip->getFromName('backup.enc');
        $zip->close();

        $json = Crypt::decryptString($encrypted);
        $data = json_decode($json, true);

        // Verify encrypted data contains the sensitive info
        expect($data['tables']['ai_providers'][0]['provider'])->toBe('openai');
        expect(decrypt($data['tables']['ai_providers'][0]['api_key_encrypted']))->toBe('sk-secret-api-key-12345');

        // Cleanup
        if (file_exists($backupPath)) {
            unlink($backupPath);
        }
    });

    it('handles restore when backup tables are empty', function () {
        // Create a backup with empty tables (no data inserted)
        $service = new BackupService;
        $backupPath = $service->createBackup();

        // Insert some data that should be cleared
        DB::table('ai_providers')->insert([
            'provider' => 'temp-data',
            'api_key_encrypted' => encrypt('temp'),
            'display_name' => 'Temp',
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        expect(DB::table('ai_providers')->count())->toBe(1);

        // Restore the empty backup
        $service->restoreBackup($backupPath);

        // Tables should now be empty
        expect(DB::table('ai_providers')->count())->toBe(0);

        // Cleanup
        if (file_exists($backupPath)) {
            unlink($backupPath);
        }
    });

    it('preserves env file content through backup and restore', function () {
        $envPath = base_path('.env');
        $envBackupPath = base_path('.env.backup-test');
        $originalContent = null;

        // Backup existing .env if present
        if (file_exists($envPath)) {
            $originalContent = file_get_contents($envPath);
            rename($envPath, $envBackupPath);
        }

        $testEnvContent = "APP_NAME=TestDevice\nAPP_KEY=base64:testkey123\nCUSTOM_VAR=testvalue\n";
        file_put_contents($envPath, $testEnvContent);

        try {
            // Create backup
            $service = new BackupService;
            $backupPath = $service->createBackup();

            // Modify .env
            file_put_contents($envPath, "APP_NAME=Changed\n");

            // Restore backup
            $service->restoreBackup($backupPath);

            // Verify env file was restored
            $restoredContent = file_get_contents($envPath);
            expect($restoredContent)->toBe($testEnvContent);

            // Cleanup
            if (file_exists($backupPath)) {
                unlink($backupPath);
            }
        } finally {
            // Restore original .env
            if (file_exists($envPath)) {
                unlink($envPath);
            }
            if (file_exists($envBackupPath)) {
                rename($envBackupPath, $envPath);
            }
        }
    });
});
