<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\DeviceAlreadyClaimedException;
use App\Exceptions\DeviceNotFoundException;
use App\Models\Device;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use VibecodePC\Common\DTOs\DeviceInfo;
use VibecodePC\Common\DTOs\DeviceStatusResult;
use VibecodePC\Common\DTOs\PairingResult;
use VibecodePC\Common\Enums\DeviceStatus;

class DeviceRegistryService
{
    public function findByUuid(string $uuid): Device
    {
        $device = Device::where('uuid', $uuid)->first();

        if (! $device) {
            throw DeviceNotFoundException::withUuid($uuid);
        }

        return $device;
    }

    public function getDeviceStatus(string $uuid): DeviceStatusResult
    {
        $device = $this->findByUuid($uuid);

        $pairing = null;

        // If a pairing token is pending delivery, include it and clear it (one-time read)
        if ($device->pairing_token_encrypted) {
            $user = $device->user;

            $pairing = new PairingResult(
                deviceId: $device->uuid,
                token: $device->pairing_token_encrypted,
                username: $user->username ?? '',
                email: $user->email,
                ipHint: $device->ip_hint,
            );

            // Clear the token after delivery
            $device->update(['pairing_token_encrypted' => null]);
        }

        return new DeviceStatusResult(
            deviceId: $device->uuid,
            status: $device->status,
            pairing: $pairing,
        );
    }

    public function claimDevice(string $uuid, User $user, ?string $ipHint = null): PairingResult
    {
        return DB::transaction(function () use ($uuid, $user, $ipHint) {
            $device = Device::where('uuid', $uuid)->lockForUpdate()->first();

            if (! $device) {
                throw DeviceNotFoundException::withUuid($uuid);
            }

            if ($device->isClaimed()) {
                throw DeviceAlreadyClaimedException::withUuid($uuid);
            }

            // Create a Sanctum token for the device to use
            $token = $user->createToken(
                name: "device:{$uuid}",
                abilities: ['device:pair'],
            );

            $device->update([
                'status' => DeviceStatus::Claimed,
                'user_id' => $user->id,
                'paired_at' => now(),
                'ip_hint' => $ipHint,
                'pairing_token_encrypted' => $token->plainTextToken,
            ]);

            return new PairingResult(
                deviceId: $device->uuid,
                token: $token->plainTextToken,
                username: $user->username ?? '',
                email: $user->email,
                ipHint: $ipHint,
            );
        });
    }

    public function registerDevice(DeviceInfo $deviceInfo): Device
    {
        return Device::updateOrCreate(
            ['uuid' => $deviceInfo->id],
            [
                'hardware_serial' => $deviceInfo->hardwareSerial,
                'firmware_version' => $deviceInfo->firmwareVersion,
                'status' => DeviceStatus::Unclaimed,
            ],
        );
    }
}
