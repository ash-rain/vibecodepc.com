<?php

declare(strict_types=1);

namespace App\Services;

class NetworkService
{
    public function getLocalIp(): ?string
    {
        // Try to get the primary network interface IP
        $output = @shell_exec('hostname -I 2>/dev/null');

        if ($output) {
            $ips = explode(' ', trim($output));

            return $ips[0] ?? null;
        }

        // Fallback for macOS/dev environments
        $output = @shell_exec('ipconfig getifaddr en0 2>/dev/null');

        if ($output) {
            return trim($output);
        }

        return '127.0.0.1';
    }

    public function hasEthernet(): bool
    {
        // Check if eth0 interface exists and is up
        $output = @shell_exec('ip link show eth0 2>/dev/null');

        return $output !== null && str_contains($output, 'state UP');
    }

    public function hasWifi(): bool
    {
        // Check if wlan0 interface exists
        $output = @shell_exec('ip link show wlan0 2>/dev/null');

        return $output !== null && str_contains($output, 'wlan0');
    }

    public function hasInternetConnectivity(): bool
    {
        $connected = @fsockopen('1.1.1.1', 443, $errno, $errstr, 3);

        if ($connected) {
            fclose($connected);

            return true;
        }

        return false;
    }
}
