<?php

declare(strict_types=1);

namespace VibecodePC\Common\DTOs;

final readonly class PairingResult
{
    public function __construct(
        public string $deviceId,
        public string $token,
        public string $username,
        public string $email,
        public ?string $ipHint = null,
    ) {}

    /**
     * @param array{device_id: string, token: string, username: string, email: string, ip_hint?: string|null} $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            deviceId: $data['device_id'],
            token: $data['token'],
            username: $data['username'],
            email: $data['email'],
            ipHint: $data['ip_hint'] ?? null,
        );
    }

    /**
     * @return array{device_id: string, token: string, username: string, email: string, ip_hint: string|null}
     */
    public function toArray(): array
    {
        return [
            'device_id' => $this->deviceId,
            'token' => $this->token,
            'username' => $this->username,
            'email' => $this->email,
            'ip_hint' => $this->ipHint,
        ];
    }
}
