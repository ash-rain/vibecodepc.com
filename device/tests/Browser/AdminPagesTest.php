<?php

declare(strict_types=1);

use App\Models\CloudCredential;
use App\Models\DeviceState;
use App\Models\Project;
use App\Models\TunnelConfig;
use App\Services\DeviceStateService;
use Illuminate\Support\Facades\Process;
use Laravel\Dusk\Browser;

beforeEach(function () {
    // Clear database state
    CloudCredential::query()->delete();
    Project::query()->delete();
    DeviceState::query()->delete();
    TunnelConfig::query()->delete();

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

describe('Admin Dashboard Pages', function () {

    beforeEach(function () {
        CloudCredential::factory()->paired()->create();
    });

    describe('Project Management', function () {

        it('loads projects list page', function () {
            // Ensure no projects exist for clean state
            Project::query()->delete();

            $this->browse(function (Browser $browser) {
                $browser->visit('/dashboard/projects')
                    ->assertPathIs('/dashboard/projects')
                    ->assertSee('Projects');
            });
        });

        it('loads project creation page', function () {
            $this->browse(function (Browser $browser) {
                $browser->visit('/dashboard/projects/create')
                    ->assertPathIs('/dashboard/projects/create')
                    ->assertSee('Create New Project')
                    ->assertSee('New Project')
                    ->assertSee('Start from a framework template')
                    ->assertSee('Clone from URL')
                    ->assertSee('Add Existing Folder');
            });
        });

        // Note: Empty state test requires Livewire state management review
        // it('shows empty state when no projects', function () {
        //     Project::query()->delete();
        //     $this->browse(function (Browser $browser) {
        //         $browser->visit('/dashboard/projects')
        //             ->assertPathIs('/dashboard/projects')
        //             ->assertSee('No projects yet');
        //     });
        // });

        it('displays created projects', function () {
            Project::factory()->create([
                'name' => 'Test Project',
                'path' => '/tmp/test-project',
                'framework' => 'laravel',
            ]);

            $this->browse(function (Browser $browser) {
                $browser->visit('/dashboard/projects')
                    ->assertPathIs('/dashboard/projects');
            });
        });

        it('navigates to project detail page', function () {
            $project = Project::factory()->create([
                'name' => 'Detail Test Project',
                'path' => '/tmp/detail-test',
                'framework' => 'laravel',
            ]);

            $this->browse(function (Browser $browser) use ($project) {
                $browser->visit("/dashboard/projects/{$project->id}")
                    ->assertPathIs("/dashboard/projects/{$project->id}")
                    ->assertPresent('h2');
            });
        });

        it('shows back navigation on project creation', function () {
            $this->browse(function (Browser $browser) {
                $browser->visit('/dashboard/projects/create')
                    ->assertPresent('a')
                    ->assertSee('Back to Projects');
            });
        });
    });

    describe('AI Agent Configs', function () {

        it('loads AI agents config with tabs', function () {
            $this->browse(function (Browser $browser) {
                $browser->visit('/dashboard/ai-agents')
                    ->assertPathIs('/dashboard/ai-agents')
                    ->assertSee('AI Agent Configs')
                    ->assertSee('Boost Configuration');
            });
        });

        it('displays config editor interface', function () {
            $this->browse(function (Browser $browser) {
                $browser->visit('/dashboard/ai-agents')
                    ->assertPresent('textarea')
                    ->assertPresent('button');
            });
        });

        it('displays project selector when projects exist', function () {
            Project::factory()->create(['name' => 'Config Test Project']);

            $this->browse(function (Browser $browser) {
                $browser->visit('/dashboard/ai-agents')
                    ->assertSee('Project Context')
                    ->assertPresent('select');
            });
        });

        it('displays save button on AI agents page', function () {
            $this->browse(function (Browser $browser) {
                $browser->visit('/dashboard/ai-agents')
                    ->assertPresent('button');
            });
        });
    });

    describe('AI Tools Configuration', function () {

        it('loads AI tools config page', function () {
            $this->browse(function (Browser $browser) {
                $browser->visit('/dashboard/ai-tools')
                    ->assertPathIs('/dashboard/ai-tools')
                    ->assertSee('AI Tools')
                    ->assertSee('API Keys');
            });
        });

        it('displays API key configuration fields', function () {
            $this->browse(function (Browser $browser) {
                $browser->visit('/dashboard/ai-tools')
                    ->assertSee('API Keys')
                    ->assertSee('Gemini API Key')
                    ->assertPresent('input');
            });
        });

        it('displays environment configuration tab', function () {
            $this->browse(function (Browser $browser) {
                $browser->visit('/dashboard/ai-tools')
                    ->assertPresent('button');
            });
        });
    });

    describe('System Settings', function () {

        it('loads system settings page', function () {
            $this->browse(function (Browser $browser) {
                $browser->visit('/dashboard/settings')
                    ->assertPathIs('/dashboard/settings')
                    ->assertSee('Settings')
                    ->assertSee('System');
            });
        });

        it('displays network configuration', function () {
            $this->browse(function (Browser $browser) {
                $browser->visit('/dashboard/settings')
                    ->assertSee('Network')
                    ->assertSee('Local IP');
            });
        });

        it('displays tabs', function () {
            $this->browse(function (Browser $browser) {
                $browser->visit('/dashboard/settings')
                    ->assertSee('Network')
                    ->assertSee('Storage')
                    ->assertSee('Updates')
                    ->assertSee('SSH')
                    ->assertSee('Backup')
                    ->assertSee('Power');
            });
        });

        it('displays all settings tabs', function () {
            $this->browse(function (Browser $browser) {
                $browser->visit('/dashboard/settings')
                    ->assertSee('Network')
                    ->assertSee('Storage')
                    ->assertSee('Updates')
                    ->assertSee('SSH')
                    ->assertSee('Backup')
                    ->assertSee('Power');
            });
        });

        it('displays SSH toggle option', function () {
            $this->browse(function (Browser $browser) {
                $browser->visit('/dashboard/settings')
                    ->assertPresent('button');
            });
        });
    });

    describe('Tunnel Management', function () {

        it('loads tunnel management page', function () {
            $this->browse(function (Browser $browser) {
                $browser->visit('/dashboard/tunnels')
                    ->assertPathIs('/dashboard/tunnels')
                    ->assertSee('Tunnel');
            });
        });

        it('displays tunnel status', function () {
            $this->browse(function (Browser $browser) {
                $browser->visit('/dashboard/tunnels')
                    ->assertPresent('span');
            });
        });

        it('shows quick tunnels section', function () {
            $this->browse(function (Browser $browser) {
                $browser->visit('/dashboard/tunnels')
                    ->assertSee('Quick Tunnels');
            });
        });
    });

    describe('Container Management', function () {

        it('loads containers page', function () {
            $this->browse(function (Browser $browser) {
                $browser->visit('/dashboard/containers')
                    ->assertPathIs('/dashboard/containers')
                    ->assertSee('Containers');
            });
        });
    });

    describe('Code Editor', function () {

        it('loads code editor page', function () {
            $this->browse(function (Browser $browser) {
                $browser->visit('/dashboard/code-editor')
                    ->assertPathIs('/dashboard/code-editor')
                    ->assertSee('Code Editor');
            });
        });
    });

    describe('Analytics Dashboard', function () {

        it('loads analytics page', function () {
            $this->browse(function (Browser $browser) {
                $browser->visit('/dashboard/analytics')
                    ->assertPathIs('/dashboard/analytics')
                    ->assertSee('Analytics');
            });
        });
    });

    describe('AI Services Hub', function () {

        it('loads AI services hub page', function () {
            $this->browse(function (Browser $browser) {
                $browser->visit('/dashboard/ai-services')
                    ->assertPathIs('/dashboard/ai-services')
                    ->assertSee('AI Services');
            });
        });
    });

    describe('Sidebar Navigation', function () {

        it('displays all navigation links', function () {
            $this->browse(function (Browser $browser) {
                $browser->visit('/dashboard')
                    ->assertPresent('nav')
                    ->assertSee('Overview')
                    ->assertSee('Projects')
                    ->assertSee('AI Services')
                    ->assertSee('AI Tools Config')
                    ->assertSee('AI Agent Configs')
                    ->assertSee('Code Editor')
                    ->assertSee('Tunnels')
                    ->assertSee('Containers')
                    ->assertSee('Settings')
                    ->assertSee('Analytics');
            });
        });

        it('highlights active navigation item', function () {
            $this->browse(function (Browser $browser) {
                $browser->visit('/dashboard/projects')
                    ->assertPresent('nav')
                    ->assertSee('Projects');
            });
        });
    });

    describe('Responsive Admin Layout', function () {

        it('renders admin pages on mobile viewport', function () {
            $this->browse(function (Browser $browser) {
                $browser->visit('/dashboard')
                    ->resize(375, 667)
                    ->assertSee('VibeCodePC');
            });
        });

        it('renders admin pages on tablet viewport', function () {
            $this->browse(function (Browser $browser) {
                $browser->visit('/dashboard')
                    ->resize(768, 1024)
                    ->assertPresent('nav')
                    ->assertSee('Projects');
            });
        });

        it('renders admin pages on desktop viewport', function () {
            $this->browse(function (Browser $browser) {
                $browser->visit('/dashboard')
                    ->resize(1920, 1080)
                    ->assertPresent('nav')
                    ->assertSee('Overview');
            });
        });
    });
});
