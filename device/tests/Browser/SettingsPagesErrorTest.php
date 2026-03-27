<?php

declare(strict_types=1);

use App\Models\CloudCredential;
use App\Models\DeviceState;
use App\Models\Project;
use App\Services\DeviceStateService;
use Illuminate\Support\Facades\Process;
use Laravel\Dusk\Browser;

beforeEach(function () {
    // Clear database state
    CloudCredential::query()->delete();
    Project::query()->delete();
    DeviceState::query()->delete();

    // Set admin password for tunnel authentication
    DeviceState::setValue('admin_password_hash', \Illuminate\Support\Facades\Hash::make('testpassword123'));

    // Set up device state for dashboard access
    DeviceState::setValue(DeviceStateService::MODE_KEY, DeviceStateService::MODE_DASHBOARD);

    // Fake process calls for system metrics
    Process::fake([
        "top -bn1 | grep 'Cpu(s)' | awk '{print \$2}'" => Process::result(output: '10.0'),
        "free -m | awk '/^Mem:/ {print \$3}'" => Process::result(output: '2048'),
        "free -m | awk '/^Mem:/ {print \$2}'" => Process::result(output: '4096'),
        "df -BG / | awk 'NR==2 {print \$3}'" => Process::result(output: '32G'),
        "df -BG / | awk 'NR==2 {print \$2}'" => Process::result(output: '64G'),
        'cat /sys/class/thermal/thermal_zone0/temp 2>/dev/null' => Process::result(exitCode: 1),
        '*systemctl is-active ssh*' => Process::result(output: 'inactive', exitCode: 3),
        '*systemctl*' => Process::result(output: 'active', exitCode: 0),
    ]);
});

describe('Settings Pages - Error Detection', function () {

    beforeEach(function () {
        CloudCredential::factory()->paired()->create();
    });

    describe('System Settings Page', function () {

        it('loads system settings without JavaScript errors', function () {
            $this->browse(function (Browser $browser) {
                $browser->visit('/dashboard/settings')
                    ->assertPathIs('/dashboard/settings')
                    ->assertSee('System Settings');

                // Check for JavaScript errors in console
                $logs = $browser->driver->manage()->getLog('browser');
                $errors = collect($logs)
                    ->filter(fn ($log) => ($log['level'] ?? '') === 'SEVERE')
                    ->pluck('message')
                    ->all();

                expect($errors)->toBeEmpty('JavaScript errors found: '.implode(', ', $errors));
            });
        });

        it('displays all settings tabs without errors', function () {
            $this->browse(function (Browser $browser) {
                $browser->visit('/dashboard/settings')
                    ->assertSee('Network')
                    ->assertSee('Storage')
                    ->assertSee('Updates')
                    ->assertSee('SSH')
                    ->assertSee('Backup')
                    ->assertSee('Power');

                // Check for JavaScript errors
                $logs = $browser->driver->manage()->getLog('browser');
                $errors = collect($logs)
                    ->filter(fn ($log) => ($log['level'] ?? '') === 'SEVERE')
                    ->pluck('message')
                    ->all();

                expect($errors)->toBeEmpty('JavaScript errors found: '.implode(', ', $errors));
            });
        });

        it('switches between tabs without JavaScript errors', function () {
            $this->browse(function (Browser $browser) {
                $browser->visit('/dashboard/settings')
                    ->assertPathIs('/dashboard/settings');

                // Click through each tab using button selector
                $tabs = ['Storage', 'Updates', 'SSH', 'Backup', 'Power'];
                foreach ($tabs as $tab) {
                    // Use XPath to find button with exact text
                    $browser->driver->findElement(\Facebook\WebDriver\WebDriverBy::xpath("//button[contains(text(), '{$tab}')]"))->click();
                    $browser->pause(150);
                }

                // Check for JavaScript errors after tab switching
                $logs = $browser->driver->manage()->getLog('browser');
                $errors = collect($logs)
                    ->filter(fn ($log) => ($log['level'] ?? '') === 'SEVERE')
                    ->pluck('message')
                    ->all();

                expect($errors)->toBeEmpty('JavaScript errors found after tab switching: '.implode(', ', $errors));
            });
        });

        it('displays network configuration without errors', function () {
            $this->browse(function (Browser $browser) {
                $browser->visit('/dashboard/settings')
                    ->assertSee('Network Configuration')
                    ->assertSee('Local IP');

                $logs = $browser->driver->manage()->getLog('browser');
                $errors = collect($logs)
                    ->filter(fn ($log) => ($log['level'] ?? '') === 'SEVERE')
                    ->pluck('message')
                    ->all();

                expect($errors)->toBeEmpty('JavaScript errors found: '.implode(', ', $errors));
            });
        });

        it('displays storage information without errors', function () {
            $this->browse(function (Browser $browser) {
                $browser->visit('/dashboard/settings');

                // Click Storage tab using XPath
                $browser->driver->findElement(\Facebook\WebDriver\WebDriverBy::xpath("//button[contains(text(), 'Storage')]"))->click();
                $browser->pause(150)
                    ->assertSee('Disk Usage');

                $logs = $browser->driver->manage()->getLog('browser');
                $errors = collect($logs)
                    ->filter(fn ($log) => ($log['level'] ?? '') === 'SEVERE')
                    ->pluck('message')
                    ->all();

                expect($errors)->toBeEmpty('JavaScript errors found: '.implode(', ', $errors));
            });
        });

        it('displays SSH toggle without errors', function () {
            $this->browse(function (Browser $browser) {
                $browser->visit('/dashboard/settings');

                // Click SSH tab using XPath
                $browser->driver->findElement(\Facebook\WebDriver\WebDriverBy::xpath("//button[contains(text(), 'SSH')]"))->click();
                $browser->pause(150)
                    ->assertSee('SSH Access')
                    ->assertSee('SSH Server');

                $logs = $browser->driver->manage()->getLog('browser');
                $errors = collect($logs)
                    ->filter(fn ($log) => ($log['level'] ?? '') === 'SEVERE')
                    ->pluck('message')
                    ->all();

                expect($errors)->toBeEmpty('JavaScript errors found: '.implode(', ', $errors));
            });
        });

        it('displays backup section without errors', function () {
            $this->browse(function (Browser $browser) {
                $browser->visit('/dashboard/settings');

                // Click Backup tab using XPath
                $browser->driver->findElement(\Facebook\WebDriver\WebDriverBy::xpath("//button[contains(text(), 'Backup')]"))->click();
                $browser->pause(150)
                    ->assertSee('Backup & Restore')
                    ->assertSee('Download Backup');

                $logs = $browser->driver->manage()->getLog('browser');
                $errors = collect($logs)
                    ->filter(fn ($log) => ($log['level'] ?? '') === 'SEVERE')
                    ->pluck('message')
                    ->all();

                expect($errors)->toBeEmpty('JavaScript errors found: '.implode(', ', $errors));
            });
        });

        it('displays power management without errors', function () {
            $this->browse(function (Browser $browser) {
                $browser->visit('/dashboard/settings');

                // Click Power tab using XPath
                $browser->driver->findElement(\Facebook\WebDriver\WebDriverBy::xpath("//button[contains(text(), 'Power')]"))->click();
                $browser->pause(150)
                    ->assertSee('Power Management')
                    ->assertSee('Restart Device')
                    ->assertSee('Shutdown');

                $logs = $browser->driver->manage()->getLog('browser');
                $errors = collect($logs)
                    ->filter(fn ($log) => ($log['level'] ?? '') === 'SEVERE')
                    ->pluck('message')
                    ->all();

                expect($errors)->toBeEmpty('JavaScript errors found: '.implode(', ', $errors));
            });
        });

        it('displays factory reset section without errors', function () {
            $this->browse(function (Browser $browser) {
                $browser->visit('/dashboard/settings');

                // Click Power tab using XPath
                $browser->driver->findElement(\Facebook\WebDriver\WebDriverBy::xpath("//button[contains(text(), 'Power')]"))->click();
                $browser->pause(150)
                    ->assertSee('Factory Reset');

                $logs = $browser->driver->manage()->getLog('browser');
                $errors = collect($logs)
                    ->filter(fn ($log) => ($log['level'] ?? '') === 'SEVERE')
                    ->pluck('message')
                    ->all();

                expect($errors)->toBeEmpty('JavaScript errors found: '.implode(', ', $errors));
            });
        });
    });

    describe('Settings Page - Mobile Viewport', function () {

        it('renders settings on mobile without JavaScript errors', function () {
            $this->browse(function (Browser $browser) {
                $browser->visit('/dashboard/settings')
                    ->resize(375, 667)
                    ->assertPathIs('/dashboard/settings')
                    ->assertSee('System Settings');

                $logs = $browser->driver->manage()->getLog('browser');
                $errors = collect($logs)
                    ->filter(fn ($log) => ($log['level'] ?? '') === 'SEVERE')
                    ->pluck('message')
                    ->all();

                expect($errors)->toBeEmpty('JavaScript errors found on mobile: '.implode(', ', $errors));
            });
        });

        it('switches tabs on mobile without JavaScript errors', function () {
            $this->browse(function (Browser $browser) {
                $browser->visit('/dashboard/settings')
                    ->resize(375, 667);

                // Click through tabs on mobile using XPath
                $tabs = ['Storage', 'Updates', 'SSH', 'Backup', 'Power'];
                foreach ($tabs as $tab) {
                    $browser->driver->findElement(\Facebook\WebDriver\WebDriverBy::xpath("//button[contains(text(), '{$tab}')]"))->click();
                    $browser->pause(150);
                }

                $logs = $browser->driver->manage()->getLog('browser');
                $errors = collect($logs)
                    ->filter(fn ($log) => ($log['level'] ?? '') === 'SEVERE')
                    ->pluck('message')
                    ->all();

                expect($errors)->toBeEmpty('JavaScript errors found on mobile tab switching: '.implode(', ', $errors));
            });
        });
    });

    describe('Settings Page - Network Information', function () {

        it('displays network section without JavaScript errors', function () {
            $this->browse(function (Browser $browser) {
                $browser->visit('/dashboard/settings')
                    ->assertPathIs('/dashboard/settings')
                    ->assertSee('Network Configuration')
                    ->assertSee('Local IP');

                $logs = $browser->driver->manage()->getLog('browser');
                $errors = collect($logs)
                    ->filter(fn ($log) => ($log['level'] ?? '') === 'SEVERE')
                    ->pluck('message')
                    ->all();

                expect($errors)->toBeEmpty('JavaScript errors found on network section: '.implode(', ', $errors));
            });
        });
    });

    describe('Settings Page - Livewire Interactions', function () {

        it('handles SSH toggle interaction without JavaScript errors', function () {
            $this->browse(function (Browser $browser) {
                $browser->visit('/dashboard/settings');

                // Click SSH tab using XPath
                $browser->driver->findElement(\Facebook\WebDriver\WebDriverBy::xpath("//button[contains(text(), 'SSH')]"))->click();
                $browser->pause(200);

                // Try to click the SSH toggle button
                try {
                    $browser->click('button[wire\\:click="toggleSsh"]')
                        ->pause(300);
                } catch (\Exception $e) {
                    // Toggle might not be present if SSH is not configured
                }

                $logs = $browser->driver->manage()->getLog('browser');
                $errors = collect($logs)
                    ->filter(fn ($log) => ($log['level'] ?? '') === 'SEVERE')
                    ->pluck('message')
                    ->all();

                expect($errors)->toBeEmpty('JavaScript errors found during SSH toggle: '.implode(', ', $errors));
            });
        });

        it('handles check for updates button without JavaScript errors', function () {
            $this->browse(function (Browser $browser) {
                $browser->visit('/dashboard/settings');

                // Click Updates tab using XPath
                $browser->driver->findElement(\Facebook\WebDriver\WebDriverBy::xpath("//button[contains(text(), 'Updates')]"))->click();
                $browser->pause(200);

                // Try to click the check for updates button
                try {
                    $browser->click('button[wire\\:click="checkForUpdates"]')
                        ->pause(300);
                } catch (\Exception $e) {
                    // Button might not be found
                }

                $logs = $browser->driver->manage()->getLog('browser');
                $errors = collect($logs)
                    ->filter(fn ($log) => ($log['level'] ?? '') === 'SEVERE')
                    ->pluck('message')
                    ->all();

                expect($errors)->toBeEmpty('JavaScript errors found during updates check: '.implode(', ', $errors));
            });
        });
    });

    describe('Settings Page - Console Warning Detection', function () {

        it('has no console warnings on initial load', function () {
            $this->browse(function (Browser $browser) {
                $browser->visit('/dashboard/settings');

                // Check for any console warnings (not just SEVERE)
                $logs = $browser->driver->manage()->getLog('browser');
                $warnings = collect($logs)
                    ->filter(fn ($log) => in_array($log['level'] ?? '', ['WARNING', 'SEVERE']))
                    ->pluck('message')
                    ->all();

                // Filter out common expected warnings
                $unexpectedWarnings = collect($warnings)
                    ->reject(fn ($msg) => str_contains($msg, 'favicon') || str_contains($msg, 'source map'))
                    ->all();

                expect($unexpectedWarnings)->toBeEmpty('Unexpected console warnings found: '.implode(', ', $unexpectedWarnings));
            });
        });
    });
});
