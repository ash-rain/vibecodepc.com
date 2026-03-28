<?php

declare(strict_types=1);

use App\Models\CloudCredential;
use App\Models\DeviceState;
use App\Models\Project;
use App\Services\DeviceStateService;
use Illuminate\Support\Facades\Process;
use Laravel\Dusk\Browser;

beforeEach(function () {
    // Clear database state to ensure clean test environment
    CloudCredential::query()->delete();
    Project::query()->delete();
    DeviceState::query()->delete();

    // Set admin password for tunnel authentication
    DeviceState::setValue('admin_password_hash', \Illuminate\Support\Facades\Hash::make('testpassword123'));

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

describe('Page Loading Tests', function () {

    describe('Dashboard Pages', function () {

        beforeEach(function () {
            // Set up device state for dashboard access and create paired credential
            DeviceState::setValue(DeviceStateService::MODE_KEY, DeviceStateService::MODE_DASHBOARD);
            CloudCredential::factory()->paired()->create();
        });

        it('loads dashboard overview page', function () {
            $this->browse(function (Browser $browser) {
                $browser->visit('/dashboard')
                    ->assertPathIs('/dashboard')
                    ->assertSee('Welcome back');
            });
        });

        it('loads projects list page', function () {
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
                    ->assertSee('Create New Project');
            });
        });

        it('loads project detail page', function () {
            $project = Project::factory()->create([
                'name' => 'Test Project',
                'path' => '/tmp/test-project',
            ]);
            $project->refresh();

            $this->browse(function (Browser $browser) use ($project) {
                $browser->visit("/dashboard/projects/{$project->id}")
                    ->assertPathIs("/dashboard/projects/{$project->id}");
            });
        });

        it('loads AI services hub page', function () {
            $this->browse(function (Browser $browser) {
                $browser->visit('/dashboard/ai-services')
                    ->assertPathIs('/dashboard/ai-services')
                    ->assertSee('AI Services');
            });
        });

        it('loads AI tools config page', function () {
            $this->browse(function (Browser $browser) {
                $browser->visit('/dashboard/ai-tools')
                    ->assertPathIs('/dashboard/ai-tools')
                    ->assertSee('AI Tools');
            });
        });

        it('loads AI agents config page', function () {
            $this->browse(function (Browser $browser) {
                $browser->visit('/dashboard/ai-agents')
                    ->assertPathIs('/dashboard/ai-agents')
                    ->assertSee('AI Agent Configs');
            });
        });

        it('loads code editor page', function () {
            $this->browse(function (Browser $browser) {
                $browser->visit('/dashboard/code-editor')
                    ->assertPathIs('/dashboard/code-editor')
                    ->assertSee('Code Editor');
            });
        });

        it('loads tunnels page', function () {
            $this->browse(function (Browser $browser) {
                $browser->visit('/dashboard/tunnels')
                    ->assertPathIs('/dashboard/tunnels')
                    ->assertSee('Tunnels');
            });
        });

        it('loads containers page', function () {
            $this->browse(function (Browser $browser) {
                $browser->visit('/dashboard/containers')
                    ->assertPathIs('/dashboard/containers')
                    ->assertSee('Containers');
            });
        });

        it('loads system settings page', function () {
            $this->browse(function (Browser $browser) {
                $browser->visit('/dashboard/settings')
                    ->assertPathIs('/dashboard/settings')
                    ->assertSee('Settings');
            });
        });

        it('loads analytics page', function () {
            $this->browse(function (Browser $browser) {
                $browser->visit('/dashboard/analytics')
                    ->assertPathIs('/dashboard/analytics')
                    ->assertSee('Analytics');
            });
        });
    });

    describe('Non-Dashboard Pages', function () {

        beforeEach(function () {
            // Reset state for non-dashboard tests
            CloudCredential::query()->delete();
            DeviceState::query()->delete();
        });

        it('loads home page and redirects to dashboard when paired', function () {
            // Set up device state for dashboard access and create paired credential
            DeviceState::setValue(DeviceStateService::MODE_KEY, DeviceStateService::MODE_DASHBOARD);
            CloudCredential::factory()->paired()->create();

            $this->browse(function (Browser $browser) {
                $browser->visit('/')
                    ->assertPathIs('/dashboard')
                    ->assertSee('Welcome back');
            });
        });

        it('loads pairing page', function () {
            // Set mode to pairing
            DeviceState::setValue(DeviceStateService::MODE_KEY, DeviceStateService::MODE_PAIRING);

            $this->browse(function (Browser $browser) {
                $browser->visit('/pairing')
                    ->assertPathIs('/pairing')
                    ->assertSee('Pairing');
            });
        });

        it('loads tunnel login page', function () {
            // Set mode to dashboard for tunnel login access
            DeviceState::setValue(DeviceStateService::MODE_KEY, DeviceStateService::MODE_DASHBOARD);

            $this->browse(function (Browser $browser) {
                $browser->visit('/tunnel/login')
                    ->assertPathIs('/tunnel/login')
                    ->assertSee('Device Access');
            });
        });

        it('loads wizard page', function () {
            // Set mode to wizard
            DeviceState::setValue(DeviceStateService::MODE_KEY, DeviceStateService::MODE_WIZARD);

            $this->browse(function (Browser $browser) {
                $browser->visit('/wizard')
                    ->assertPathIs('/wizard')
                    ->assertSee('Setup');
            });
        });
    });

    describe('Page Navigation', function () {

        beforeEach(function () {
            // Set up device state for dashboard access and create paired credential
            DeviceState::setValue(DeviceStateService::MODE_KEY, DeviceStateService::MODE_DASHBOARD);
            CloudCredential::factory()->paired()->create();
        });

        it('navigates between dashboard pages via sidebar', function () {
            $this->browse(function (Browser $browser) {
                $browser->visit('/dashboard')
                    ->assertSee('Welcome back');

                // Navigate to projects
                $browser->clickLink('Projects')
                    ->waitForLocation('/dashboard/projects')
                    ->assertSee('Projects');

                // Navigate to settings
                $browser->clickLink('Settings')
                    ->waitForLocation('/dashboard/settings')
                    ->assertSee('Settings');

                // Navigate back to dashboard
                $browser->clickLink('Overview')
                    ->waitForLocation('/dashboard')
                    ->assertSee('Welcome back');
            });
        });

        it('maintains active state in sidebar navigation', function () {
            $this->browse(function (Browser $browser) {
                $browser->visit('/dashboard/projects')
                    ->assertPathIs('/dashboard/projects');
            });
        });
    });

    describe('Page Responsiveness', function () {

        beforeEach(function () {
            // Set up device state for dashboard access and create paired credential
            DeviceState::setValue(DeviceStateService::MODE_KEY, DeviceStateService::MODE_DASHBOARD);
            CloudCredential::factory()->paired()->create();
        });

        it('renders dashboard at mobile viewport', function () {
            $this->browse(function (Browser $browser) {
                $browser->visit('/dashboard')
                    ->resize(375, 667) // iPhone viewport
                    ->assertPathIs('/dashboard')
                    ->assertSee('VibeCodePC');
            });
        });

        it('renders dashboard at tablet viewport', function () {
            $this->browse(function (Browser $browser) {
                $browser->visit('/dashboard')
                    ->resize(768, 1024) // iPad viewport
                    ->assertPathIs('/dashboard')
                    ->assertSee('VibeCodePC');
            });
        });

        it('renders dashboard at desktop viewport', function () {
            $this->browse(function (Browser $browser) {
                $browser->visit('/dashboard')
                    ->resize(1920, 1080)
                    ->assertPathIs('/dashboard')
                    ->assertSee('VibeCodePC');
            });
        });
    });

    describe('Error Handling', function () {

        it('returns 404 for non-existent project', function () {
            $this->browse(function (Browser $browser) {
                $browser->visit('/dashboard/projects/99999')
                    ->assertDontSee('Test Project')
                    ->assertPathIs('/dashboard/projects/99999');
            });
        });

        it('returns 404 for non-existent routes', function () {
            $this->browse(function (Browser $browser) {
                $browser->visit('/non-existent-route')
                    ->assertDontSee('Dashboard')
                    ->assertPathIs('/non-existent-route');
            });
        });

        it('handles schema endpoint for valid schema', function () {
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

        it('returns 404 for non-existent schema', function () {
            $this->browse(function (Browser $browser) {
                $browser->visit('/schemas/non-existent.json')
                    ->assertSee('error')
                    ->assertSee('not found');
            });
        });
    });
});
