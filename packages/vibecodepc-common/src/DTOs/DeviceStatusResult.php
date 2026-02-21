<?php

declare(strict_types=1);

namespace VibecodePC\Common\DTOs;

use VibecodePC\Common\Enums\DeviceStatus;

final readonly class DeviceStatusResult
{
    public function __construct(
        public string $deviceId,
        public DeviceStatus $status,
        public ?PairingResult $pairing = null,
    ) {}

    /**
     * @param array{device_id: string, status: string, pairing?: array|null} $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            deviceId: $data['device_id'],
            status: DeviceStatus::from($data['status']),
            pairing: isset($data['pairing']) ? PairingResult::fromArray($data['pairing']) : null,
        );
    }

    /**
     * @return array{device_id: string, status: string, pairing: array|null}
     */
    public function toArray(): array
    {
        return [
            'device_id' => $this->deviceId,
            'status' => $this->status->value,
            'pairing' => $this->pairing?->toArray(),
        ];
    }
}
