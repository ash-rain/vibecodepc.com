<?php

use App\Models\CloudCredential;
use App\Models\DeviceState;
use App\Services\DevicePairingService;
use App\Services\DeviceStateService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function createDeviceStateService(): DeviceStateService
{
    return new DeviceStateService(new DevicePairingService);
}

function createCloudCredential(bool $isPaired = true): CloudCredential
{
    return CloudCredential::create([
        'pairing_token_encrypted' => '1|abc123',
        'cloud_username' => 'testuser',
        'cloud_email' => 'test@example.com',
        'cloud_url' => 'https://vibecodepc.com',
        'is_paired' => $isPaired,
        'paired_at' => $isPaired ? now() : null,
    ]);
}

// === Pairing Required Mode (VIBECODEPC_PAIRING_REQUIRED=true) ===

describe('when pairing is required', function () {
    beforeEach(function () {
        config(['vibecodepc.pairing.required' => true]);
    });

    it('getMode returns pairing when no credentials exist', function () {
        expect(createDeviceStateService()->getMode())->toBe(DeviceStateService::MODE_PAIRING);
    });

    it('getMode returns pairing when credential exists but is not paired', function () {
        createCloudCredential(isPaired: false);

        expect(createDeviceStateService()->getMode())->toBe(DeviceStateService::MODE_PAIRING);
    });

    it('getMode returns wizard as default when paired but no mode is stored', function () {
        createCloudCredential();

        expect(createDeviceStateService()->getMode())->toBe(DeviceStateService::MODE_WIZARD);
    });

    it('getMode returns stored mode when paired', function () {
        createCloudCredential();
        DeviceState::setValue(DeviceStateService::MODE_KEY, DeviceStateService::MODE_DASHBOARD);

        expect(createDeviceStateService()->getMode())->toBe(DeviceStateService::MODE_DASHBOARD);
    });

    it('getMode returns pairing when stored mode is wizard but no credentials exist', function () {
        DeviceState::setValue(DeviceStateService::MODE_KEY, DeviceStateService::MODE_WIZARD);

        expect(createDeviceStateService()->getMode())->toBe(DeviceStateService::MODE_PAIRING);
    });

    it('isPairing returns true when not paired', function () {
        $service = createDeviceStateService();

        expect($service->isPairing())->toBeTrue()
            ->and($service->isWizard())->toBeFalse()
            ->and($service->isDashboard())->toBeFalse();
    });

    it('getMode returns pairing when credentials deleted after mode was set', function () {
        $credential = createCloudCredential();
        DeviceState::setValue(DeviceStateService::MODE_KEY, DeviceStateService::MODE_DASHBOARD);
        $credential->delete();

        expect(createDeviceStateService()->getMode())->toBe(DeviceStateService::MODE_PAIRING);
    });

    it('handles credential unpairing after mode was set', function () {
        $credential = createCloudCredential();
        DeviceState::setValue(DeviceStateService::MODE_KEY, DeviceStateService::MODE_DASHBOARD);
        $credential->update(['is_paired' => false, 'paired_at' => null]);

        $service = createDeviceStateService();

        expect($service->getMode())->toBe(DeviceStateService::MODE_PAIRING)
            ->and($service->isPairing())->toBeTrue();
    });
});

// === Pairing Optional Mode (VIBECODEPC_PAIRING_REQUIRED=false, default) ===

describe('when pairing is optional', function () {
    beforeEach(function () {
        config(['vibecodepc.pairing.required' => false]);
    });

    it('getMode skips pairing screen when no credentials exist', function () {
        expect(createDeviceStateService()->getMode())->toBe(DeviceStateService::MODE_WIZARD);
    });

    it('getMode skips pairing screen when credential exists but is not paired', function () {
        createCloudCredential(isPaired: false);

        expect(createDeviceStateService()->getMode())->toBe(DeviceStateService::MODE_WIZARD);
    });

    it('getMode returns stored mode when not paired', function () {
        DeviceState::setValue(DeviceStateService::MODE_KEY, DeviceStateService::MODE_DASHBOARD);

        expect(createDeviceStateService()->getMode())->toBe(DeviceStateService::MODE_DASHBOARD);
    });

    it('getMode returns wizard when no mode stored and not paired', function () {
        expect(createDeviceStateService()->getMode())->toBe(DeviceStateService::MODE_WIZARD);
    });

    it('getMode returns stored mode when paired', function () {
        createCloudCredential();
        DeviceState::setValue(DeviceStateService::MODE_KEY, DeviceStateService::MODE_DASHBOARD);

        expect(createDeviceStateService()->getMode())->toBe(DeviceStateService::MODE_DASHBOARD);
    });

    it('isPairing always returns false', function () {
        expect(createDeviceStateService()->isPairing())->toBeFalse();
    });

    it('handles credential deletion without blocking', function () {
        $credential = createCloudCredential();
        DeviceState::setValue(DeviceStateService::MODE_KEY, DeviceStateService::MODE_DASHBOARD);
        $credential->delete();

        expect(createDeviceStateService()->getMode())->toBe(DeviceStateService::MODE_DASHBOARD);
    });
});

// === Shared behavior (independent of pairing config) ===

it('setMode updates the device mode', function () {
    $service = createDeviceStateService();

    $service->setMode(DeviceStateService::MODE_WIZARD);
    expect(DeviceState::getValue(DeviceStateService::MODE_KEY))->toBe(DeviceStateService::MODE_WIZARD);

    $service->setMode(DeviceStateService::MODE_DASHBOARD);
    expect(DeviceState::getValue(DeviceStateService::MODE_KEY))->toBe(DeviceStateService::MODE_DASHBOARD);
});

it('isWizard returns true when paired and in wizard mode', function () {
    createCloudCredential();
    DeviceState::setValue(DeviceStateService::MODE_KEY, DeviceStateService::MODE_WIZARD);

    $service = createDeviceStateService();

    expect($service->isWizard())->toBeTrue()
        ->and($service->isPairing())->toBeFalse()
        ->and($service->isDashboard())->toBeFalse();
});

it('isDashboard returns true when paired and in dashboard mode', function () {
    createCloudCredential();
    DeviceState::setValue(DeviceStateService::MODE_KEY, DeviceStateService::MODE_DASHBOARD);

    $service = createDeviceStateService();

    expect($service->isDashboard())->toBeTrue()
        ->and($service->isPairing())->toBeFalse()
        ->and($service->isWizard())->toBeFalse();
});

it('setMode accepts any string value including invalid modes', function () {
    $service = createDeviceStateService();

    $service->setMode('invalid_mode');

    expect(DeviceState::getValue(DeviceStateService::MODE_KEY))->toBe('invalid_mode');
});

it('getMode returns invalid mode value when paired and stored mode is unrecognized', function () {
    createCloudCredential();
    DeviceState::setValue(DeviceStateService::MODE_KEY, 'unrecognized_mode');

    expect(createDeviceStateService()->getMode())->toBe('unrecognized_mode');
});

it('getMode reflects immediate state change without caching issues', function () {
    createCloudCredential();

    $service = createDeviceStateService();

    DeviceState::setValue(DeviceStateService::MODE_KEY, DeviceStateService::MODE_WIZARD);
    expect($service->getMode())->toBe(DeviceStateService::MODE_WIZARD);

    DeviceState::setValue(DeviceStateService::MODE_KEY, DeviceStateService::MODE_DASHBOARD);
    expect($service->getMode())->toBe(DeviceStateService::MODE_DASHBOARD);
});

it('concurrent mode updates result in last-write-wins behavior', function () {
    createCloudCredential();

    $service1 = createDeviceStateService();
    $service2 = createDeviceStateService();

    $service1->setMode(DeviceStateService::MODE_WIZARD);
    $service2->setMode(DeviceStateService::MODE_DASHBOARD);

    expect(DeviceState::getValue(DeviceStateService::MODE_KEY))->toBe(DeviceStateService::MODE_DASHBOARD);
});

it('mode persists across multiple service instances', function () {
    createCloudCredential();

    $service1 = createDeviceStateService();
    $service1->setMode(DeviceStateService::MODE_DASHBOARD);

    $service2 = createDeviceStateService();

    expect($service2->getMode())->toBe(DeviceStateService::MODE_DASHBOARD);
});

it('handles rapid successive mode changes', function () {
    createCloudCredential();

    $service = createDeviceStateService();

    foreach ([DeviceStateService::MODE_WIZARD, DeviceStateService::MODE_DASHBOARD, DeviceStateService::MODE_WIZARD] as $mode) {
        $service->setMode($mode);
    }

    expect($service->getMode())->toBe(DeviceStateService::MODE_WIZARD);
});

it('handles mode with special characters and unicode', function () {
    createCloudCredential();

    $service = createDeviceStateService();
    $specialMode = 'mode_with_special_chars_!@#$%^&*()_+-=[]{}|;\':",./<>?';

    $service->setMode($specialMode);

    expect($service->getMode())->toBe($specialMode);
});

it('handles unicode mode values', function () {
    createCloudCredential();

    $service = createDeviceStateService();
    $service->setMode('モード_Режим_模式');

    expect($service->getMode())->toBe('モード_Режим_模式');
});

it('handles very long mode values', function () {
    createCloudCredential();

    $service = createDeviceStateService();
    $longMode = str_repeat('a', 10000);

    $service->setMode($longMode);

    expect($service->getMode())->toBe($longMode);
});

it('uses latest credential to determine pairing status', function () {
    config(['vibecodepc.pairing.required' => true]);

    // Only unpaired credential exists
    CloudCredential::create([
        'pairing_token_encrypted' => '1|abc123',
        'cloud_username' => 'testuser',
        'cloud_email' => 'test@example.com',
        'cloud_url' => 'https://vibecodepc.com',
        'is_paired' => false,
        'paired_at' => null,
    ]);

    expect(createDeviceStateService()->getMode())->toBe(DeviceStateService::MODE_PAIRING);
});
