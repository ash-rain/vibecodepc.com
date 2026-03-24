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
    ]);
});

describe('Critical User Flows', function () {

    describe('Project Management Flow', function () {
        beforeEach(function () {
            CloudCredential::factory()->paired()->create();
        });

        it('creates a new project', function () {
            $this->browse(function (Browser $browser) {
                $browser->visit('/dashboard/projects/create')
                    ->assertPathIs('/dashboard/projects/create')
                    ->assertSee('Create New Project')
                    ->type('name', 'Test Project Creation')
                    ->type('path', '/tmp/test-project-path')
                    ->press('Create Project')
                    ->waitForLocation('/dashboard/projects')
                    ->assertSee('Test Project Creation');
            });
        });

        it('views project details', function () {
            $project = Project::factory()->create([
                'name' => 'Detail Test Project',
                'path' => '/tmp/detail-test',
            ]);

            $this->browse(function (Browser $browser) use ($project) {
                $browser->visit("/dashboard/projects/{$project->id}")
                    ->assertPathIs("/dashboard/projects/{$project->id}")
                    ->assertSee('Detail Test Project')
                    ->assertSee('/tmp/detail-test');
            });
        });

        it('navigates back from project detail to projects list', function () {
            $project = Project::factory()->create([
                'name' => 'Navigation Test',
                'path' => '/tmp/nav-test',
            ]);

            $this->browse(function (Browser $browser) use ($project) {
                $browser->visit("/dashboard/projects/{$project->id}")
                    ->clickLink('Back')
                    ->waitForLocation('/dashboard/projects')
                    ->assertPathIs('/dashboard/projects')
                    ->assertSee('Projects');
            });
        });
    });

    describe('AI Configuration Flows', function () {
        beforeEach(function () {
            CloudCredential::factory()->paired()->create();
        });

        it('loads AI agents config with tabbed interface', function () {
            $this->browse(function (Browser $browser) {
                $browser->visit('/dashboard/ai-agents')
                    ->assertPathIs('/dashboard/ai-agents')
                    ->assertSee('AI Agent Configs')
                    ->assertSee('Boost.json')
                    ->assertSee('OpenCode')
                    ->assertSee('Claude Code')
                    ->assertSee('Copilot Instructions');
            });
        });

        it('switches between AI agent tabs', function () {
            $this->browse(function (Browser $browser) {
                $browser->visit('/dashboard/ai-agents')
                    ->assertSee('Boost.json')
                    ->click('OpenCode')
                    ->waitForText('OpenCode Configuration')
                    ->click('Claude Code')
                    ->waitForText('Claude Code Configuration')
                    ->click('Copilot Instructions')
                    ->waitForText('Copilot Instructions');
            });
        });

        it('loads AI tools configuration page', function () {
            $this->browse(function (Browser $browser) {
                $browser->visit('/dashboard/ai-tools')
                    ->assertPathIs('/dashboard/ai-tools')
                    ->assertSee('AI Tools');
            });
        });

        it('loads AI services hub', function () {
            $this->browse(function (Browser $browser) {
                $browser->visit('/dashboard/ai-services')
                    ->assertPathIs('/dashboard/ai-services')
                    ->assertSee('AI Services');
            });
        });
    });

    describe('Code Editor Flow', function () {
        beforeEach(function () {
            CloudCredential::factory()->paired()->create();
        });

        it('loads code editor page', function () {
            $this->browse(function (Browser $browser) {
                $browser->visit('/dashboard/code-editor')
                    ->assertPathIs('/dashboard/code-editor')
                    ->assertSee('Code Editor');
            });
        });

        it('shows tunnel status in code editor', function () {
            $this->browse(function (Browser $browser) {
                $browser->visit('/dashboard/code-editor')
                    ->assertSee('Code Editor')
                    ->assertSee('Tunnel');
            });
        });
    });

    describe('Settings and System Flows', function () {
        beforeEach(function () {
            CloudCredential::factory()->paired()->create();
        });

        it('loads system settings', function () {
            $this->browse(function (Browser $browser) {
                $browser->visit('/dashboard/settings')
                    ->assertPathIs('/dashboard/settings')
                    ->assertSee('Settings')
                    ->assertSee('System');
            });
        });

        it('displays system metrics in settings', function () {
            $this->browse(function (Browser $browser) {
                $browser->visit('/dashboard/settings')
                    ->assertSee('Settings')
                    ->assertSee('CPU')
                    ->assertSee('Memory')
                    ->assertSee('Disk');
            });
        });
    });

    describe('Tunnel Management Flow', function () {
        beforeEach(function () {
            CloudCredential::factory()->paired()->create();
        });

        it('loads tunnels page', function () {
            $this->browse(function (Browser $browser) {
                $browser->visit('/dashboard/tunnels')
                    ->assertPathIs('/dashboard/tunnels')
                    ->assertSee('Tunnels');
            });
        });

        it('shows tunnel configuration options', function () {
            $this->browse(function (Browser $browser) {
                $browser->visit('/dashboard/tunnels')
                    ->assertSee('Tunnels')
                    ->assertSee('Status');
            });
        });
    });

    describe('Container Management Flow', function () {
        beforeEach(function () {
            CloudCredential::factory()->paired()->create();
        });

        it('loads containers page', function () {
            $this->browse(function (Browser $browser) {
                $browser->visit('/dashboard/containers')
                    ->assertPathIs('/dashboard/containers')
                    ->assertSee('Containers');
            });
        });
    });

    describe('Analytics Dashboard Flow', function () {
        beforeEach(function () {
            CloudCredential::factory()->paired()->create();
        });

        it('loads analytics page', function () {
            $this->browse(function (Browser $browser) {
                $browser->visit('/dashboard/analytics')
                    ->assertPathIs('/dashboard/analytics')
                    ->assertSee('Analytics');
            });
        });
    });

    describe('Wizard Flow', function () {
        it('loads wizard setup page', function () {
            DeviceState::setValue(DeviceStateService::MODE_KEY, DeviceStateService::MODE_WIZARD);

            $this->browse(function (Browser $browser) {
                $browser->visit('/wizard')
                    ->assertPathIs('/wizard')
                    ->assertSee('Setup');
            });
        });
    });

    describe('Pairing Flow', function () {
        it('loads pairing page', function () {
            DeviceState::setValue(DeviceStateService::MODE_KEY, DeviceStateService::MODE_PAIRING);

            $this->browse(function (Browser $browser) {
                $browser->visit('/pairing')
                    ->assertPathIs('/pairing')
                    ->assertSee('Pairing');
            });
        });
    });

    describe('Tunnel Login Flow', function () {
        it('loads tunnel login page', function () {
            $this->browse(function (Browser $browser) {
                $browser->visit('/tunnel/login')
                    ->assertPathIs('/tunnel/login')
                    ->assertSee('Device Access');
            });
        });
    });

    describe('Navigation and Layout', function () {
        beforeEach(function () {
            CloudCredential::factory()->paired()->create();
        });

        it('shows sidebar navigation on all dashboard pages', function () {
            $this->browse(function (Browser $browser) {
                $pages = [
                    '/dashboard',
                    '/dashboard/projects',
                    '/dashboard/ai-agents',
                    '/dashboard/ai-tools',
                    '/dashboard/settings',
                ];

                foreach ($pages as $page) {
                    $browser->visit($page)
                        ->assertPresent('nav')
                        ->assertSee('Overview');
                }
            });
        });

        it('shows user menu in header', function () {
            $this->browse(function (Browser $browser) {
                $browser->visit('/dashboard')
                    ->assertPresent('header')
                    ->assertSee('VibeCodePC');
            });
        });
    });
});
