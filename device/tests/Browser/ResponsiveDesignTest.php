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
        '*systemctl is-active ssh*' => Process::result(output: 'inactive', exitCode: 3),
    ]);

    CloudCredential::factory()->paired()->create();
});

describe('Responsive Design Tests', function () {

    describe('Mobile Viewport (iPhone SE)', function () {
        it('renders dashboard at mobile size', function () {
            $this->browse(function (Browser $browser) {
                $browser->visit('/dashboard')
                    ->resize(375, 667)
                    ->assertPathIs('/dashboard')
                    ->assertSee('VibeCodePC');
            });
        });

        it('renders projects page at mobile size', function () {
            $this->browse(function (Browser $browser) {
                $browser->visit('/dashboard/projects')
                    ->resize(375, 667)
                    ->assertPathIs('/dashboard/projects')
                    ->assertSee('Projects');
            });
        });

        it('renders settings at mobile size', function () {
            $this->browse(function (Browser $browser) {
                $browser->visit('/dashboard/settings')
                    ->resize(375, 667)
                    ->assertPathIs('/dashboard/settings')
                    ->assertSee('Settings');
            });
        });

        it('renders AI agents config at mobile size', function () {
            $this->browse(function (Browser $browser) {
                $browser->visit('/dashboard/ai-agents')
                    ->resize(375, 667)
                    ->assertPathIs('/dashboard/ai-agents')
                    ->assertSee('AI Agent Configs');
            });
        });

        it('shows mobile navigation', function () {
            $this->browse(function (Browser $browser) {
                $browser->visit('/dashboard')
                    ->resize(375, 667)
                    ->assertPresent('nav');
            });
        });
    });

    describe('Tablet Viewport (iPad)', function () {
        it('renders dashboard at tablet size', function () {
            $this->browse(function (Browser $browser) {
                $browser->visit('/dashboard')
                    ->resize(768, 1024)
                    ->assertPathIs('/dashboard')
                    ->assertSee('Welcome back');
            });
        });

        it('renders projects page at tablet size', function () {
            $this->browse(function (Browser $browser) {
                $browser->visit('/dashboard/projects')
                    ->resize(768, 1024)
                    ->assertPathIs('/dashboard/projects')
                    ->assertSee('Projects');
            });
        });

        it('renders sidebar at tablet size', function () {
            $this->browse(function (Browser $browser) {
                $browser->visit('/dashboard')
                    ->resize(768, 1024)
                    ->assertPresent('nav');
            });
        });
    });

    describe('Desktop Viewport', function () {
        it('renders dashboard at desktop size', function () {
            $this->browse(function (Browser $browser) {
                $browser->visit('/dashboard')
                    ->resize(1920, 1080)
                    ->assertPathIs('/dashboard')
                    ->assertSee('Welcome back');
            });
        });

        it('renders full sidebar at desktop size', function () {
            $this->browse(function (Browser $browser) {
                $browser->visit('/dashboard')
                    ->resize(1920, 1080)
                    ->assertPresent('nav')
                    ->assertSee('Overview')
                    ->assertSee('Projects');
            });
        });

        it('renders AI services at desktop size', function () {
            $this->browse(function (Browser $browser) {
                $browser->visit('/dashboard/ai-services')
                    ->resize(1920, 1080)
                    ->assertPathIs('/dashboard/ai-services')
                    ->assertSee('AI Services');
            });
        });
    });

    describe('Large Desktop Viewport', function () {
        it('renders dashboard at large desktop size', function () {
            $this->browse(function (Browser $browser) {
                $browser->visit('/dashboard')
                    ->resize(2560, 1440)
                    ->assertPathIs('/dashboard')
                    ->assertSee('Welcome back');
            });
        });
    });

    describe('Responsive Layout Adjustments', function () {
        it('adjusts layout when resizing from mobile to desktop', function () {
            $this->browse(function (Browser $browser) {
                $browser->visit('/dashboard')
                    ->resize(375, 667)
                    ->assertPresent('nav')
                    ->resize(1920, 1080)
                    ->assertPresent('nav')
                    ->assertSee('Welcome back');
            });
        });

        it('maintains content visibility when resizing', function () {
            $this->browse(function (Browser $browser) {
                $browser->visit('/dashboard')
                    ->resize(1920, 1080)
                    ->assertSee('Welcome back')
                    ->resize(768, 1024)
                    ->assertSee('Welcome back')
                    ->resize(375, 667)
                    ->assertSee('VibeCodePC');
            });
        });
    });

    describe('Touch-Friendly Elements', function () {
        it('has clickable navigation items on mobile', function () {
            $this->browse(function (Browser $browser) {
                $browser->visit('/dashboard')
                    ->resize(375, 667)
                    ->clickLink('Projects')
                    ->waitForLocation('/dashboard/projects')
                    ->assertPathIs('/dashboard/projects');
            });
        });

        it('has touch-friendly buttons on mobile', function () {
            $this->browse(function (Browser $browser) {
                $browser->visit('/dashboard/projects/create')
                    ->resize(375, 667)
                    ->assertPresent('button');
            });
        });
    });
});
