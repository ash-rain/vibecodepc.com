<?php

declare(strict_types=1);

namespace App\Services\GitHub;

final readonly class DeviceFlowResult
{
    public function __construct(
        public string $deviceCode,
        public string $userCode,
        public string $verificationUri,
        public int $expiresIn,
        public int $interval,
    ) {}

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        return new self(
            deviceCode: $data['device_code'],
            userCode: $data['user_code'],
            verificationUri: $data['verification_uri'],
            expiresIn: $data['expires_in'],
            interval: $data['interval'] ?? 5,
        );
    }
}
