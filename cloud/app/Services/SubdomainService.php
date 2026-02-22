<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\User;

class SubdomainService
{
    /** @var list<string> */
    private const array RESERVED_WORDS = [
        'admin', 'api', 'app', 'blog', 'cdn', 'cloud', 'dashboard',
        'dev', 'docs', 'ftp', 'git', 'help', 'login', 'mail',
        'ns1', 'ns2', 'panel', 'pop', 'root', 'smtp', 'ssh', 'ssl',
        'staging', 'status', 'support', 'test', 'vpn', 'www',
    ];

    public function isReserved(string $subdomain): bool
    {
        return in_array(strtolower($subdomain), self::RESERVED_WORDS, true);
    }

    public function isAvailable(string $subdomain, ?int $excludeUserId = null): bool
    {
        if ($this->isReserved($subdomain)) {
            return false;
        }

        $query = User::query()->where('username', $subdomain);

        if ($excludeUserId !== null) {
            $query->where('id', '!=', $excludeUserId);
        }

        return ! $query->exists();
    }

    public function updateSubdomain(User $user, string $newSubdomain): void
    {
        $oldUsername = $user->username;
        $user->update(['username' => $newSubdomain]);

        // Cascade subdomain change to existing tunnel routes
        if ($oldUsername !== $newSubdomain) {
            $user->load('devices.tunnelRoutes');

            foreach ($user->devices as $device) {
                $device->tunnelRoutes()
                    ->where('subdomain', $oldUsername)
                    ->update(['subdomain' => $newSubdomain]);

                $device->increment('config_version');
            }
        }
    }

    /**
     * @return list<string>
     */
    public function getReservedWords(): array
    {
        return self::RESERVED_WORDS;
    }
}
