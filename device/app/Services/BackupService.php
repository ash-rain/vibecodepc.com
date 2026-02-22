<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use ZipArchive;

class BackupService
{
    private const BACKUP_TABLES = [
        'ai_provider_configs',
        'tunnel_configs',
        'github_credentials',
        'device_state',
        'wizard_progress',
        'cloud_credentials',
    ];

    public function createBackup(): string
    {
        $data = ['tables' => [], 'created_at' => now()->toIso8601String()];

        foreach (self::BACKUP_TABLES as $table) {
            $data['tables'][$table] = DB::table($table)->get()->toArray();
        }

        $envPath = base_path('.env');

        if (file_exists($envPath)) {
            $data['env'] = file_get_contents($envPath);
        }

        $json = json_encode($data, JSON_PRETTY_PRINT);
        $encrypted = Crypt::encryptString($json);

        $zipPath = storage_path('app/private/backup-'.now()->format('Y-m-d-His').'.zip');

        $zip = new ZipArchive;
        $zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        $zip->addFromString('backup.enc', $encrypted);
        $zip->close();

        return $zipPath;
    }

    public function restoreBackup(string $zipPath): void
    {
        $zip = new ZipArchive;

        if ($zip->open($zipPath) !== true) {
            throw new \RuntimeException('Failed to open backup file.');
        }

        $encrypted = $zip->getFromName('backup.enc');
        $zip->close();

        if ($encrypted === false) {
            throw new \RuntimeException('Invalid backup file — missing encrypted payload.');
        }

        $json = Crypt::decryptString($encrypted);
        $data = json_decode($json, true);

        if (! is_array($data) || ! isset($data['tables'])) {
            throw new \RuntimeException('Invalid backup data structure.');
        }

        DB::transaction(function () use ($data): void {
            foreach ($data['tables'] as $table => $rows) {
                if (! in_array($table, self::BACKUP_TABLES, true)) {
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
