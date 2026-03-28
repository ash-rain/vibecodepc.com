<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\CloudCredential;
use App\Models\Project;
use App\Models\TunnelConfig;
use Illuminate\Support\Facades\Log;

class DevicePairingService
{
    /**
     * High-risk actions that always require pairing, even when pairing is optional.
     */
    private const HIGH_RISK_ACTIONS = [
        'edit_secrets',
        'manage_tunnel_tokens',
        'manage_cloud_credentials',
    ];

    /**
     * Check whether pairing is required by global configuration.
     */
    public function isPairingRequired(): bool
    {
        return (bool) config('vibecodepc.pairing.required', false);
    }

    /**
     * Check whether the device is currently paired (has a verified cloud credential).
     */
    public function isPaired(): bool
    {
        return CloudCredential::current()?->isPaired() ?? false;
    }

    /**
     * Check whether the tunnel has been verified (has a verified_at timestamp).
     */
    public function isTunnelVerified(): bool
    {
        return TunnelConfig::current()?->verified_at !== null;
    }

    /**
     * Determine if an action should be allowed for the current device state.
     *
     * When pairing is required: the device must be paired.
     * When pairing is optional: most actions are allowed, but high-risk actions
     * still require pairing.
     */
    public function shouldAllowAction(string $action, ?Project $project = null): bool
    {
        if ($this->isPaired()) {
            return true;
        }

        if ($this->isPairingRequired()) {
            return false;
        }

        // Pairing is optional — block only high-risk actions for unpaired devices
        if (in_array($action, self::HIGH_RISK_ACTIONS, true)) {
            return false;
        }

        return true;
    }

    /**
     * Determine if the dashboard should be in read-only mode.
     *
     * Read-only when:
     * - Pairing is required AND device is not paired
     * - Pairing is required AND tunnel is not verified
     */
    public function isReadOnly(): bool
    {
        if (! $this->isPairingRequired()) {
            return false;
        }

        return ! $this->isPaired() || ! $this->isTunnelVerified();
    }

    /**
     * Get a human-readable reason why the dashboard is in read-only mode.
     */
    public function getReadOnlyReason(): ?string
    {
        if (! $this->isReadOnly()) {
            return null;
        }

        if (! $this->isPaired() && ! $this->isTunnelVerified()) {
            return 'Editing is disabled because the device is not paired and the tunnel is not verified.';
        }

        if (! $this->isPaired()) {
            return 'Editing is disabled because the device is not paired.';
        }

        return 'Editing is disabled because the tunnel is not verified.';
    }

    /**
     * Log an action performed by an unpaired device.
     */
    public function logUnpairedAction(string $action, ?Project $project = null, array $context = []): void
    {
        Log::info('pairing.optional.allowed_action', array_merge([
            'action' => $action,
            'is_paired' => $this->isPaired(),
            'pairing_required' => $this->isPairingRequired(),
            'project_id' => $project?->id,
        ], $context));
    }
}
