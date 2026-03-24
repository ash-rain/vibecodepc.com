<?php

use App\Models\CloudCredential;
use App\Models\TunnelConfig;
use App\Services\DevicePairingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;

uses(RefreshDatabase::class);

function createPairingService(): DevicePairingService
{
    return new DevicePairingService;
}

function createPairedCredential(): CloudCredential
{
    return CloudCredential::create([
        'pairing_token_encrypted' => '1|abc123',
        'cloud_username' => 'testuser',
        'cloud_email' => 'test@example.com',
        'cloud_url' => 'https://vibecodepc.com',
        'is_paired' => true,
        'paired_at' => now(),
    ]);
}

function createVerifiedTunnel(): TunnelConfig
{
    return TunnelConfig::create([
        'subdomain' => 'test-device',
        'tunnel_token_encrypted' => 'encrypted-token',
        'tunnel_id' => 'tunnel-123',
        'status' => 'active',
        'verified_at' => now(),
    ]);
}

// === isPairingRequired ===

describe('isPairingRequired', function () {
    it('returns true when config is true', function () {
        config(['vibecodepc.pairing.required' => true]);

        expect(createPairingService()->isPairingRequired())->toBeTrue();
    });

    it('returns false when config is false', function () {
        config(['vibecodepc.pairing.required' => false]);

        expect(createPairingService()->isPairingRequired())->toBeFalse();
    });

    it('returns false by default', function () {
        // Default in config/vibecodepc.php is false
        expect(createPairingService()->isPairingRequired())->toBeFalse();
    });
});

// === isPaired ===

describe('isPaired', function () {
    it('returns true when cloud credential is paired', function () {
        createPairedCredential();

        expect(createPairingService()->isPaired())->toBeTrue();
    });

    it('returns false when cloud credential exists but is not paired', function () {
        CloudCredential::create([
            'pairing_token_encrypted' => '1|abc123',
            'cloud_username' => 'testuser',
            'cloud_email' => 'test@example.com',
            'cloud_url' => 'https://vibecodepc.com',
            'is_paired' => false,
            'paired_at' => null,
        ]);

        expect(createPairingService()->isPaired())->toBeFalse();
    });

    it('returns false when no cloud credential exists', function () {
        expect(createPairingService()->isPaired())->toBeFalse();
    });
});

// === isTunnelVerified ===

describe('isTunnelVerified', function () {
    it('returns true when tunnel config has verified_at', function () {
        createVerifiedTunnel();

        expect(createPairingService()->isTunnelVerified())->toBeTrue();
    });

    it('returns false when tunnel config has no verified_at', function () {
        TunnelConfig::create([
            'subdomain' => 'test-device',
            'tunnel_token_encrypted' => 'encrypted-token',
            'tunnel_id' => 'tunnel-123',
            'status' => 'pending',
            'verified_at' => null,
        ]);

        expect(createPairingService()->isTunnelVerified())->toBeFalse();
    });

    it('returns false when no tunnel config exists', function () {
        expect(createPairingService()->isTunnelVerified())->toBeFalse();
    });
});

// === shouldAllowAction ===

describe('shouldAllowAction', function () {
    it('allows any action when device is paired', function () {
        createPairedCredential();
        $service = createPairingService();

        expect($service->shouldAllowAction('edit_secrets'))->toBeTrue();
        expect($service->shouldAllowAction('config_save'))->toBeTrue();
        expect($service->shouldAllowAction('manage_tunnel_tokens'))->toBeTrue();
    });

    it('blocks all actions when unpaired and pairing is required', function () {
        config(['vibecodepc.pairing.required' => true]);
        $service = createPairingService();

        expect($service->shouldAllowAction('config_save'))->toBeFalse();
        expect($service->shouldAllowAction('edit_secrets'))->toBeFalse();
    });

    it('allows normal actions when unpaired and pairing is optional', function () {
        config(['vibecodepc.pairing.required' => false]);
        $service = createPairingService();

        expect($service->shouldAllowAction('config_save'))->toBeTrue();
        expect($service->shouldAllowAction('view_dashboard'))->toBeTrue();
    });

    it('blocks high-risk actions when unpaired and pairing is optional', function () {
        config(['vibecodepc.pairing.required' => false]);
        $service = createPairingService();

        expect($service->shouldAllowAction('edit_secrets'))->toBeFalse();
        expect($service->shouldAllowAction('manage_tunnel_tokens'))->toBeFalse();
        expect($service->shouldAllowAction('manage_cloud_credentials'))->toBeFalse();
    });
});

// === isReadOnly ===

describe('isReadOnly', function () {
    it('returns false when pairing is not required', function () {
        config(['vibecodepc.pairing.required' => false]);

        expect(createPairingService()->isReadOnly())->toBeFalse();
    });

    it('returns false when pairing is not required even if unpaired', function () {
        config(['vibecodepc.pairing.required' => false]);

        expect(createPairingService()->isReadOnly())->toBeFalse();
    });

    it('returns true when pairing is required and device is not paired', function () {
        config(['vibecodepc.pairing.required' => true]);

        expect(createPairingService()->isReadOnly())->toBeTrue();
    });

    it('returns true when pairing is required and tunnel is not verified', function () {
        config(['vibecodepc.pairing.required' => true]);
        createPairedCredential();

        expect(createPairingService()->isReadOnly())->toBeTrue();
    });

    it('returns false when pairing is required and device is paired with verified tunnel', function () {
        config(['vibecodepc.pairing.required' => true]);
        createPairedCredential();
        createVerifiedTunnel();

        expect(createPairingService()->isReadOnly())->toBeFalse();
    });
});

// === getReadOnlyReason ===

describe('getReadOnlyReason', function () {
    it('returns null when not read-only', function () {
        config(['vibecodepc.pairing.required' => false]);

        expect(createPairingService()->getReadOnlyReason())->toBeNull();
    });

    it('returns message about both pairing and tunnel when neither is set', function () {
        config(['vibecodepc.pairing.required' => true]);

        $reason = createPairingService()->getReadOnlyReason();

        expect($reason)->toContain('not paired')
            ->and($reason)->toContain('tunnel');
    });

    it('returns message about pairing when only pairing is missing', function () {
        config(['vibecodepc.pairing.required' => true]);
        createVerifiedTunnel();

        $reason = createPairingService()->getReadOnlyReason();

        expect($reason)->toContain('not paired')
            ->and($reason)->not->toContain('tunnel');
    });

    it('returns message about tunnel when only tunnel is missing', function () {
        config(['vibecodepc.pairing.required' => true]);
        createPairedCredential();

        $reason = createPairingService()->getReadOnlyReason();

        expect($reason)->toContain('tunnel')
            ->and($reason)->not->toContain('not paired');
    });
});

// === logUnpairedAction ===

describe('logUnpairedAction', function () {
    it('logs action with correct context', function () {
        Log::shouldReceive('info')
            ->once()
            ->withArgs(function (string $message, array $context) {
                return $message === 'pairing.optional.allowed_action'
                    && $context['action'] === 'config_save'
                    && $context['is_paired'] === false
                    && $context['config_key'] === 'boost';
            });

        createPairingService()->logUnpairedAction('config_save', null, ['config_key' => 'boost']);
    });

    it('includes project_id when project is provided', function () {
        Log::shouldReceive('info')
            ->once()
            ->withArgs(function (string $message, array $context) {
                return $context['project_id'] === 42;
            });

        $project = new \App\Models\Project;
        $project->id = 42;

        createPairingService()->logUnpairedAction('config_save', $project);
    });

    it('sets project_id to null when no project', function () {
        Log::shouldReceive('info')
            ->once()
            ->withArgs(function (string $message, array $context) {
                return $context['project_id'] === null;
            });

        createPairingService()->logUnpairedAction('config_save');
    });
});
