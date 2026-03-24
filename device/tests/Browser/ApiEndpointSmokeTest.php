<?php

declare(strict_types=1);

use App\Models\CloudCredential;
use App\Models\DeviceState;
use App\Services\DeviceStateService;
use Illuminate\Support\Facades\Process;
use Laravel\Dusk\Browser;

beforeEach(function () {
    CloudCredential::query()->delete();
    DeviceState::query()->delete();

    DeviceState::setValue('admin_password_hash', \Illuminate\Support\Facades\Hash::make('testpassword123'));
    DeviceState::setValue(DeviceStateService::MODE_KEY, DeviceStateService::MODE_DASHBOARD);

    Process::fake([
        "top -bn1 | grep 'Cpu(s)' | awk '{print \$2}'" => Process::result(output: '10.0'),
        "free -m | awk '/^Mem:/ {print \$3}'" => Process::result(output: '2048'),
        "free -m | awk '/^Mem:/ {print \$2}'" => Process::result(output: '4096'),
        "df -BG / | awk 'NR==2 {print \$3}'" => Process::result(output: '32G'),
        "df -BG / | awk 'NR==2 {print \$2}'" => Process::result(output: '64G'),
        'cat /sys/class/thermal/thermal_zone0/temp 2>/dev/null' => Process::result(exitCode: 1),
    ]);
});

describe('API Endpoint Smoke Tests', function () {

    describe('Health Check API', function () {
        it('returns health status', function () {
            $this->browse(function (Browser $browser) {
                $browser->visit('/api/health')
                    ->assertSee('status');
            });
        });
    });

    describe('Schema Endpoint', function () {
        it('serves valid schema files', function () {
            // Create a test schema file
            $schemaDir = storage_path('schemas');
            if (! is_dir($schemaDir)) {
                mkdir($schemaDir, 0755, true);
            }
            file_put_contents("{$schemaDir}/test.json", '{"type": "object"}');

            $this->browse(function (Browser $browser) {
                $browser->visit('/schemas/test.json')
                    ->assertSee('object');
            });

            // Cleanup
            @unlink("{$schemaDir}/test.json");
        });
    });

    describe('Livewire Endpoints', function () {
        beforeEach(function () {
            CloudCredential::factory()->paired()->create();
        });

        it('loads Livewire CSS files', function () {
            $this->browse(function (Browser $browser) {
                $browser->visit('/dashboard')
                    ->assertPresent('link[href*="livewire"]');
            });
        });

        it('loads Livewire JS files', function () {
            $this->browse(function (Browser $browser) {
                $browser->visit('/dashboard')
                    ->assertPresent('script[src*="livewire"]');
            });
        });
    });

    describe('Storage Endpoints', function () {
        it('handles storage file requests', function () {
            $this->browse(function (Browser $browser) {
                // Test with a non-existent file - should not crash
                $browser->visit('/storage/non-existent-file.txt')
                    ->assertSee('error');
            });
        });
    });

    describe('Dusk Testing Endpoints', function () {
        it('has dusk login endpoint available', function () {
            // This should exist in testing environment
            $response = $this->get('/_dusk/login/1');
            $response->assertStatus(200);
        });

        it('has dusk logout endpoint available', function () {
            $response = $this->get('/_dusk/logout');
            $response->assertStatus(200);
        });
    });

    describe('Up Endpoint', function () {
        it('returns up status', function () {
            $this->browse(function (Browser $browser) {
                $browser->visit('/up')
                    ->assertSee('status');
            });
        });
    });
});
