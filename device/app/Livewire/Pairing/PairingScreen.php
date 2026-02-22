<?php

declare(strict_types=1);

namespace App\Livewire\Pairing;

use App\Models\CloudCredential;
use App\Services\CloudApiClient;
use App\Services\DeviceRegistry\DeviceIdentityService;
use App\Services\DeviceStateService;
use App\Services\NetworkService;
use chillerlan\QRCode\QRCode;
use chillerlan\QRCode\QROptions;
use Illuminate\Support\Facades\Log;
use Livewire\Component;
use VibecodePC\Common\DTOs\DeviceInfo;

class PairingScreen extends Component
{
    public string $deviceId = '';

    public string $pairingUrl = '';

    public string $localIp = '';

    public bool $hasInternet = false;

    public bool $isPaired = false;

    public function mount(
        DeviceIdentityService $identity,
        NetworkService $network,
        CloudApiClient $cloud,
    ): void {
        if ($identity->hasIdentity()) {
            $info = $identity->getDeviceInfo();
            $this->deviceId = $info->id;
            $this->pairingUrl = $identity->getPairingUrl();

            $this->registerWithCloud($cloud, $info);
        }

        $this->localIp = $network->getLocalIp() ?? '127.0.0.1';
        $this->hasInternet = $network->hasInternetConnectivity();
    }

    public function checkPairingStatus(CloudApiClient $client, DeviceStateService $stateService): void
    {
        if (! $this->deviceId) {
            return;
        }

        try {
            $status = $client->getDeviceStatus($this->deviceId);

            if ($status->pairing) {
                CloudCredential::create([
                    'pairing_token_encrypted' => $status->pairing->token,
                    'cloud_username' => $status->pairing->username,
                    'cloud_email' => $status->pairing->email,
                    'cloud_url' => config('vibecodepc.cloud_url'),
                    'is_paired' => true,
                    'paired_at' => now(),
                ]);

                $stateService->setMode(DeviceStateService::MODE_WIZARD);

                $this->isPaired = true;
                $this->redirect('/');
            }
        } catch (\Throwable $e) {
            Log::debug('Pairing poll failed', ['error' => $e->getMessage()]);
        }
    }

    private function registerWithCloud(CloudApiClient $cloud, DeviceInfo $info): void
    {
        try {
            $cloud->registerDevice($info->toArray());
        } catch (\Exception $e) {
            Log::warning('Failed to register device with cloud', ['error' => $e->getMessage()]);
        }
    }

    private function generateQrCode(string $url): string
    {
        $options = new QROptions([
            'outputType' => QRCode::OUTPUT_MARKUP_SVG,
            'svgUseCssProperties' => false,
            'drawLightModules' => false,
            'svgDefs' => '<style>rect{fill:#f59e0b}</style>',
        ]);

        return (new QRCode($options))->render($url);
    }

    public function render()
    {
        return view('livewire.pairing.pairing-screen', [
            'qrCodeSvg' => $this->pairingUrl ? $this->generateQrCode($this->pairingUrl) : '',
        ])->layout('layouts.device');
    }
}
