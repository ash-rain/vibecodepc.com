<?php

namespace Database\Seeders;

use App\Models\Device;
use Illuminate\Database\Seeder;

class DeviceSeeder extends Seeder
{
    public function run(): void
    {
        // Create a device with a known UUID for manual testing
        Device::firstOrCreate(
            ['uuid' => '00000000-0000-0000-0000-000000000001'],
            Device::factory()->make([
                'hardware_serial' => 'test-serial-001',
                'firmware_version' => '1.0.0',
            ])->toArray(),
        );

        // Create a claimed device
        if (! Device::where('uuid', '00000000-0000-0000-0000-000000000002')->exists()) {
            Device::factory()->claimed()->create([
                'uuid' => '00000000-0000-0000-0000-000000000002',
                'hardware_serial' => 'test-serial-002',
            ]);
        }

        // Create a few unclaimed test devices (only if we have fewer than 5 total)
        $remaining = 5 - Device::count();
        if ($remaining > 0) {
            Device::factory()->count($remaining)->create();
        }
    }
}
