<?php

namespace Database\Seeders;

use App\Models\Device;
use Illuminate\Database\Seeder;

class DeviceSeeder extends Seeder
{
    public function run(): void
    {
        // Create a few unclaimed test devices
        Device::factory()->count(3)->create();

        // Create a device with a known UUID for manual testing
        Device::factory()->create([
            'uuid' => '00000000-0000-0000-0000-000000000001',
            'hardware_serial' => 'test-serial-001',
            'firmware_version' => '1.0.0',
        ]);

        // Create a claimed device
        Device::factory()->claimed()->create([
            'uuid' => '00000000-0000-0000-0000-000000000002',
            'hardware_serial' => 'test-serial-002',
        ]);
    }
}
