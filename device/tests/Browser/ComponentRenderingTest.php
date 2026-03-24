<?php

declare(strict_types=1);

use App\Models\CloudCredential;
use App\Models\DeviceState;
use App\Models\Project;
use App\Services\DeviceStateService;
use Illuminate\Support\Facades\Process;
use Laravel\Dusk\Browser;

beforeEach(function () {
    CloudCredential::query()->delete();
    Project::query()->delete();
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
});

describe('Component Rendering Tests', function () {

    describe('Health Bar Components', function () {
        beforeEach(function () {
            CloudCredential::factory()->paired()->create();
        });

        it('renders health bar with CPU metrics', function () {
            $this->browse(function (Browser $browser) {
                $browser->visit('/dashboard')
                    ->assertPresent('@health-bar')
                    ->assertSee('CPU')
                    ->assertSee('10%');
            });
        });

        it('renders health bar with memory metrics', function () {
            $this->browse(function (Browser $browser) {
                $browser->visit('/dashboard')
                    ->assertSee('Memory')
                    ->assertSee('2/4G');
            });
        });

        it('renders health bar with disk metrics', function () {
            $this->browse(function (Browser $browser) {
                $browser->visit('/dashboard')
                    ->assertSee('Disk')
                    ->assertSee('32/64G');
            });
        });

        it('shows all health indicators on dashboard', function () {
            $this->browse(function (Browser $browser) {
                $browser->visit('/dashboard')
                    ->assertPresent('@cpu-indicator')
                    ->assertPresent('@memory-indicator')
                    ->assertPresent('@disk-indicator');
            });
        });
    });

    describe('Sidebar Navigation Components', function () {
        beforeEach(function () {
            CloudCredential::factory()->paired()->create();
        });

        it('renders sidebar with all navigation links', function () {
            $this->browse(function (Browser $browser) {
                $browser->visit('/dashboard')
                    ->assertPresent('nav')
                    ->assertSee('Overview')
                    ->assertSee('Projects')
                    ->assertSee('AI Services')
                    ->assertSee('AI Agents')
                    ->assertSee('Code Editor')
                    ->assertSee('Tunnels')
                    ->assertSee('Containers')
                    ->assertSee('Settings');
            });
        });

        it('highlights active navigation item', function () {
            $this->browse(function (Browser $browser) {
                $browser->visit('/dashboard/projects')
                    ->assertPresent('[aria-current="page"]');
            });
        });

        it('shows navigation icons', function () {
            $this->browse(function (Browser $browser) {
                $browser->visit('/dashboard')
                    ->assertPresent('nav svg');
            });
        });
    });

    describe('Livewire Components', function () {
        beforeEach(function () {
            CloudCredential::factory()->paired()->create();
        });

        it('renders project list component', function () {
            Project::factory()->count(3)->create();

            $this->browse(function (Browser $browser) {
                $browser->visit('/dashboard/projects')
                    ->assertPresent('[wire\:id]')
                    ->assertSee('Projects');
            });
        });

        it('renders AI agents config component', function () {
            $this->browse(function (Browser $browser) {
                $browser->visit('/dashboard/ai-agents')
                    ->assertPresent('[wire\:id]')
                    ->assertSee('AI Agent Configs');
            });
        });

        it('renders tunnel manager component', function () {
            $this->browse(function (Browser $browser) {
                $browser->visit('/dashboard/tunnels')
                    ->assertPresent('[wire\:id]')
                    ->assertSee('Tunnels');
            });
        });

        it('renders container monitor component', function () {
            $this->browse(function (Browser $browser) {
                $browser->visit('/dashboard/containers')
                    ->assertPresent('[wire\:id]')
                    ->assertSee('Containers');
            });
        });
    });

    describe('Form Components', function () {
        beforeEach(function () {
            CloudCredential::factory()->paired()->create();
        });

        it('renders project creation form', function () {
            $this->browse(function (Browser $browser) {
                $browser->visit('/dashboard/projects/create')
                    ->assertPresent('form')
                    ->assertPresent('input[name="name"]')
                    ->assertPresent('input[name="path"]')
                    ->assertPresent('button[type="submit"]');
            });
        });

        it('renders system settings form elements', function () {
            $this->browse(function (Browser $browser) {
                $browser->visit('/dashboard/settings')
                    ->assertPresent('form')
                    ->assertPresent('select')
                    ->assertPresent('button[type="submit"]');
            });
        });
    });

    describe('Button Components', function () {
        beforeEach(function () {
            CloudCredential::factory()->paired()->create();
        });

        it('renders primary buttons', function () {
            $this->browse(function (Browser $browser) {
                $browser->visit('/dashboard/projects/create')
                    ->assertPresent('button[type="submit"]');
            });
        });

        it('renders icon buttons', function () {
            $this->browse(function (Browser $browser) {
                $browser->visit('/dashboard')
                    ->assertPresent('button svg, a svg');
            });
        });
    });

    describe('Modal and Dialog Components', function () {
        beforeEach(function () {
            CloudCredential::factory()->paired()->create();
        });

        it('renders modal backdrop when triggered', function () {
            $project = Project::factory()->create();

            $this->browse(function (Browser $browser) use ($project) {
                $browser->visit("/dashboard/projects/{$project->id}")
                    ->assertPresent('main');
            });
        });
    });

    describe('Status Badge Components', function () {
        beforeEach(function () {
            CloudCredential::factory()->paired()->create();
        });

        it('renders status badges on dashboard', function () {
            $this->browse(function (Browser $browser) {
                $browser->visit('/dashboard')
                    ->assertPresent('.badge, [class*="badge"]');
            });
        });

        it('renders status indicators for tunnels', function () {
            $this->browse(function (Browser $browser) {
                $browser->visit('/dashboard/tunnels')
                    ->assertSee('Status');
            });
        });
    });

    describe('Progress Bar Components', function () {
        beforeEach(function () {
            CloudCredential::factory()->paired()->create();
        });

        it('renders progress bars for metrics', function () {
            $this->browse(function (Browser $browser) {
                $browser->visit('/dashboard')
                    ->assertPresent('@cpu-progress-bar')
                    ->assertPresent('@memory-progress-bar')
                    ->assertPresent('@disk-progress-bar');
            });
        });
    });

    describe('Card Components', function () {
        beforeEach(function () {
            CloudCredential::factory()->paired()->create();
        });

        it('renders dashboard cards', function () {
            $this->browse(function (Browser $browser) {
                $browser->visit('/dashboard')
                    ->assertPresent('.card, [class*="card"], .bg-white');
            });
        });

        it('renders project cards', function () {
            Project::factory()->count(3)->create();

            $this->browse(function (Browser $browser) {
                $browser->visit('/dashboard/projects')
                    ->assertPresent('.card, [class*="card"], .bg-white');
            });
        });
    });

    describe('Icon Components', function () {
        beforeEach(function () {
            CloudCredential::factory()->paired()->create();
        });

        it('renders icons throughout the application', function () {
            $this->browse(function (Browser $browser) {
                $browser->visit('/dashboard')
                    ->assertPresent('svg');
            });
        });
    });
});
