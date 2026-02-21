<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\DeviceRegistry\DeviceIdentityService;
use chillerlan\QRCode\QRCode;
use chillerlan\QRCode\QROptions;
use Illuminate\Console\Command;

class ShowDeviceQr extends Command
{
    protected $signature = 'device:show-qr';

    protected $description = 'Display the device pairing QR code in the terminal';

    public function handle(DeviceIdentityService $identity): int
    {
        if (! $identity->hasIdentity()) {
            $this->error('No device identity found. Run: php artisan device:generate-id');

            return self::FAILURE;
        }

        $url = $identity->getPairingUrl();
        $device = $identity->getDeviceInfo();

        $options = new QROptions([
            'outputType' => QRCode::OUTPUT_STRING_TEXT,
            'ecc' => QRCode::ECC_M,
        ]);

        $qr = (new QRCode($options))->render($url);

        $this->newLine();
        $this->info('=== VibeCodePC Pairing ===');
        $this->newLine();
        $this->line($qr);
        $this->newLine();
        $this->info("Device ID: {$device->id}");
        $this->info("Pair URL:  {$url}");
        $this->newLine();

        return self::SUCCESS;
    }
}
