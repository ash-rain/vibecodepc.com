<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\Device;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class DeviceRegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_registers_new_device(): void
    {
        $uuid = Str::uuid()->toString();

        $response = $this->postJson('/api/devices/register', [
            'id' => $uuid,
            'hardware_serial' => 'dev-abc123',
            'firmware_version' => '1.0.0',
        ]);

        $response->assertStatus(201)
            ->assertJson([
                'uuid' => $uuid,
                'status' => 'unclaimed',
            ]);

        $this->assertDatabaseHas('devices', [
            'uuid' => $uuid,
            'hardware_serial' => 'dev-abc123',
            'firmware_version' => '1.0.0',
        ]);
    }

    public function test_re_registering_existing_device_is_idempotent(): void
    {
        $device = Device::factory()->create([
            'hardware_serial' => 'original-serial',
            'firmware_version' => '1.0.0',
        ]);

        $response = $this->postJson('/api/devices/register', [
            'id' => $device->uuid,
            'hardware_serial' => 'original-serial',
            'firmware_version' => '1.1.0',
        ]);

        $response->assertStatus(201);

        $device->refresh();
        $this->assertSame('1.1.0', $device->firmware_version);
    }

    public function test_validates_required_fields(): void
    {
        $response = $this->postJson('/api/devices/register', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['id', 'hardware_serial', 'firmware_version']);
    }

    public function test_validates_uuid_format(): void
    {
        $response = $this->postJson('/api/devices/register', [
            'id' => 'not-a-uuid',
            'hardware_serial' => 'dev-abc123',
            'firmware_version' => '1.0.0',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['id']);
    }
}
