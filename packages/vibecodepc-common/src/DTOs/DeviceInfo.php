<?php

declare(strict_types=1);

namespace VibecodePC\Common\DTOs;

final readonly class DeviceInfo
{
    public function __construct(
        public string $id,
        public string $hardwareSerial,
        public string $manufacturedAt,
        public string $firmwareVersion,
    ) {}

    /**
     * @param array{id: string, hardware_serial: string, manufactured_at: string, firmware_version: string} $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            id: $data['id'],
            hardwareSerial: $data['hardware_serial'],
            manufacturedAt: $data['manufactured_at'],
            firmwareVersion: $data['firmware_version'],
        );
    }

    public static function fromJson(string $json): self
    {
        $data = json_decode($json, true, 512, JSON_THROW_ON_ERROR);

        return self::fromArray($data);
    }

    /**
     * @return array{id: string, hardware_serial: string, manufactured_at: string, firmware_version: string}
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'hardware_serial' => $this->hardwareSerial,
            'manufactured_at' => $this->manufacturedAt,
            'firmware_version' => $this->firmwareVersion,
        ];
    }

    public function toJson(): string
    {
        return json_encode($this->toArray(), JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR);
    }
}
