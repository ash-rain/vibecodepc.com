<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use ZipArchive;

class BackupService
{
    private function getBackupTables(): array
    {
        return config('vibecodepc.backup.tables', [
            'ai_providers',
            'tunnel_configs',
            'github_credentials',
            'device_state',
            'wizard_progress',
            'cloud_credentials',
        ]);
    }

    public function createBackup(): string
    {
        $data = ['tables' => [], 'created_at' => now()->toIso8601String(), 'version' => 1];

        foreach ($this->getBackupTables() as $table) {
            $data['tables'][$table] = DB::table($table)->get()->toArray();
        }

        $envPath = base_path('.env');

        if (file_exists($envPath)) {
            $data['env'] = file_get_contents($envPath);
        }

        $jsonWithoutChecksum = json_encode($data, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
        $data['checksum'] = hash('sha256', $jsonWithoutChecksum);
        $jsonWithChecksum = json_encode($data, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);

        $encrypted = Crypt::encryptString($jsonWithChecksum);

        $zipPath = storage_path('app/private/backup-'.now()->format('Y-m-d-His').'.zip');

        $zip = new ZipArchive;
        $zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        $zip->addFromString('backup.enc', $encrypted);
        $zip->close();

        return $zipPath;
    }

    public function restoreBackup(string $zipPath): void
    {
        if (! file_exists($zipPath)) {
            throw new \RuntimeException('Backup file does not exist.');
        }

        if (! is_readable($zipPath)) {
            throw new \RuntimeException('Backup file is not readable.');
        }

        $zip = new ZipArchive;

        if ($zip->open($zipPath) !== true) {
            throw new \RuntimeException('Failed to open backup file. The file may be corrupted.');
        }

        $encrypted = $zip->getFromName('backup.enc');
        $zip->close();

        if ($encrypted === false) {
            throw new \RuntimeException('Invalid backup file — missing encrypted payload.');
        }

        $json = Crypt::decryptString($encrypted);
        $data = json_decode($json, true, 512, JSON_THROW_ON_ERROR);

        if (! is_array($data) || ! isset($data['tables'])) {
            throw new \RuntimeException('Invalid backup data structure.');
        }

        if (isset($data['checksum'])) {
            $storedChecksum = $data['checksum'];
            unset($data['checksum']);
            $computedChecksum = hash('sha256', json_encode($data, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE));

            if (! hash_equals($storedChecksum, $computedChecksum)) {
                throw new \RuntimeException('Backup file integrity check failed. The file may be corrupted or tampered with.');
            }
        }

        DB::transaction(function () use ($data): void {
            foreach ($data['tables'] as $table => $rows) {
                if (! in_array($table, $this->getBackupTables(), true)) {
                    continue;
                }

                DB::table($table)->truncate();

                foreach ($rows as $row) {
                    DB::table($table)->insert((array) $row);
                }
            }
        });

        if (! empty($data['env'])) {
            file_put_contents(base_path('.env'), $data['env']);
        }
    }
}
