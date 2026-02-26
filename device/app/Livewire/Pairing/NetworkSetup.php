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

    public function mount(NetworkService $network): void
    {
        $this->hasEthernet = $network->hasEthernet();
        $this->hasWifi = $network->hasWifi();
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

    public function render()
    {
        return view('livewire.pairing.network-setup');
    }
}
