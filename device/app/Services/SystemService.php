<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\Process;

class SystemService
{
    public function setAdminPassword(string $password): bool
    {
        $result = Process::run(
            sprintf('echo "vibecodepc:%s" | sudo chpasswd', escapeshellarg($password)),
        );

        return $result->successful();
    }

    public function setTimezone(string $timezone): bool
    {
        $result = Process::run(
            sprintf('sudo timedatectl set-timezone %s', escapeshellarg($timezone)),
        );

        return $result->successful();
    }

    /** @return array<int, string> */
    public function getAvailableTimezones(): array
    {
        $result = Process::run('timedatectl list-timezones');

        if (! $result->successful()) {
            return timezone_identifiers_list();
        }

        return array_filter(explode("\n", trim($result->output())));
    }

    public function getCurrentTimezone(): string
    {
        $result = Process::run('timedatectl show --property=Timezone --value');

        if ($result->successful() && trim($result->output())) {
            return trim($result->output());
        }

        return date_default_timezone_get();
    }
}
