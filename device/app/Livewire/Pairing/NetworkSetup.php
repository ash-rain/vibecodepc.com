<?php

declare(strict_types=1);

namespace App\Livewire\Pairing;

use App\Services\NetworkService;
use Livewire\Component;

class NetworkSetup extends Component
{
    public string $ssid = '';

    public string $password = '';

    public bool $connecting = false;

    public ?string $error = null;

    public ?string $success = null;

    public bool $hasEthernet = false;

    public bool $hasWifi = false;

    public ?string $localIp = null;

    public function mount(NetworkService $network): void
    {
        $this->hasEthernet = $network->hasEthernet();
        $this->hasWifi = $network->hasWifi();
        $this->localIp = $network->getLocalIp();
    }

    public function connect(): void
    {
        $this->validate([
            'ssid' => 'required|string|max:255',
            'password' => 'required|string|min:8|max:255',
        ]);

        $this->connecting = true;
        $this->error = null;
        $this->success = null;

        // Use nmcli to connect to WiFi (standard on Raspberry Pi OS)
        $ssid = escapeshellarg($this->ssid);
        $pass = escapeshellarg($this->password);
        $output = [];
        $exitCode = 0;

        exec("sudo nmcli dev wifi connect {$ssid} password {$pass} 2>&1", $output, $exitCode);

        $this->connecting = false;

        if ($exitCode === 0) {
            $this->success = 'Connected to WiFi successfully!';
            $this->password = '';
        } else {
            $this->error = 'Failed to connect: '.implode(' ', $output);
        }
    }

    public function refreshIp(NetworkService $network): void
    {
        $this->localIp = $network->getLocalIp();
    }

    public function validateIp(string $ip): bool
    {
        return filter_var($ip, FILTER_VALIDATE_IP) !== false;
    }

    public function isPrivateIp(?string $ip): bool
    {
        if ($ip === null) {
            return false;
        }

        // Use FILTER_FLAG_NO_PRIV_RANGE - if it fails this filter, it's in private range
        return filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE) === false
            && filter_var($ip, FILTER_VALIDATE_IP) !== false;
    }

    public function isLoopbackIp(?string $ip): bool
    {
        if ($ip === null) {
            return false;
        }

        // Check for IPv4 loopback (127.x.x.x) and IPv6 loopback (::1)
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            $parts = explode('.', $ip);

            return $parts[0] === '127';
        }

        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
            // Normalize IPv6 address and check for loopback
            $expanded = inet_ntop(inet_pton($ip));

            return $expanded === '::1' || $ip === '::1' || $ip === '0:0:0:0:0:0:0:1';
        }

        return false;
    }

    public function render()
    {
        return view('livewire.pairing.network-setup');
    }
}
