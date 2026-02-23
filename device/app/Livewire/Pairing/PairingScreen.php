<?php

declare(strict_types=1);

namespace App\Livewire\Pairing;

use App\Models\CloudCredential;
use App\Services\DeviceRegistry\DeviceIdentityService;
use App\Services\NetworkService;
use chillerlan\QRCode\QRCode;
use chillerlan\QRCode\QROptions;
use Livewire\Component;

class PairingScreen extends Component
{
    public string $deviceId = '';

    public string $pairingUrl = '';

    public string $localIp = '';

    public bool $hasInternet = false;

    public function mount(
        DeviceIdentityService $identity,
        NetworkService $network,
    ): void {
        if ($identity->hasIdentity()) {
            $info = $identity->getDeviceInfo();
            $this->deviceId = $info->id;
            $this->pairingUrl = $identity->getPairingUrl();
        }

        $this->localIp = $network->getLocalIp() ?? '127.0.0.1';
        $this->hasInternet = $network->hasInternetConnectivity();
    }

    public function checkPairingStatus(): void
    {
        $credential = CloudCredential::current();

        if ($credential?->isPaired()) {
            $this->redirect('/wizard');
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
