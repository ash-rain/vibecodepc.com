<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\CloudCredential;
use App\Models\DeviceState;

class DeviceStateService
{
    public const MODE_KEY = 'device_mode';

    public const MODE_PAIRING = 'pairing';
    public const MODE_WIZARD = 'wizard';
    public const MODE_DASHBOARD = 'dashboard';

    public function getMode(): string
    {
        $credential = CloudCredential::current();

        // If not paired, always show pairing screen
        if (! $credential || ! $credential->isPaired()) {
            return self::MODE_PAIRING;
        }

        return DeviceState::getValue(self::MODE_KEY, self::MODE_WIZARD);
    }

    public function setMode(string $mode): void
    {
        DeviceState::setValue(self::MODE_KEY, $mode);
    }

    public function isPairing(): bool
    {
        return $this->getMode() === self::MODE_PAIRING;
    }

    public function isWizard(): bool
    {
        return $this->getMode() === self::MODE_WIZARD;
    }

    public function isDashboard(): bool
    {
        return $this->getMode() === self::MODE_DASHBOARD;
    }
}
