<?php

declare(strict_types=1);

use App\Models\CloudCredential;
use App\Models\DeviceState;
use App\Models\GitHubCredential;
use App\Models\Project;
use App\Models\TunnelConfig;
use App\Models\WizardProgress;
use App\Services\DeviceStateService;
use App\Services\WizardProgressService;
use Illuminate\Support\Facades\Process;
use Laravel\Dusk\Browser;
use VibecodePC\Common\Enums\WizardStep;

beforeEach(function () {
    // Clear database state
    CloudCredential::query()->delete();
    GitHubCredential::query()->delete();
    WizardProgress::query()->delete();
    Project::query()->delete();
    TunnelConfig::query()->delete();
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
        '*systemctl*' => Process::result(output: 'active', exitCode: 0),
    ]);
});

describe('Edge Case Page Tests', function () {

    describe('Wizard Step Pages', function () {

        beforeEach(function () {
            DeviceState::setValue(DeviceStateService::MODE_KEY, DeviceStateService::MODE_WIZARD);
            app(WizardProgressService::class)->seedProgress();
        });

        it('loads wizard welcome step', function () {
            $this->browse(function (Browser $browser) {
                $browser->visit('/wizard')
                    ->assertPathIs('/wizard')
                    ->assertSee('Welcome');
            });
        });

        it('loads wizard ai services step', function () {
            $progressService = app(WizardProgressService::class);
            $progressService->completeStep(WizardStep::Welcome, ['timezone' => 'UTC']);

            $this->browse(function (Browser $browser) {
                $browser->visit('/wizard?step=ai_services')
                    ->assertPathIs('/wizard')
                    ->assertSee('AI Services');
            });
        });

        it('loads wizard github step', function () {
            $progressService = app(WizardProgressService::class);
            $progressService->completeStep(WizardStep::Welcome, ['timezone' => 'UTC']);
            $progressService->completeStep(WizardStep::AiServices, ['providers_configured' => 0]);

            $this->browse(function (Browser $browser) {
                $browser->visit('/wizard?step=github')
                    ->assertPathIs('/wizard')
                    ->assertSee('GitHub');
            });
        });

        it('loads wizard code server step', function () {
            $progressService = app(WizardProgressService::class);
            $progressService->completeStep(WizardStep::Welcome, ['timezone' => 'UTC']);
            $progressService->completeStep(WizardStep::AiServices, ['providers_configured' => 0]);
            $progressService->completeStep(WizardStep::GitHub, ['username' => 'testuser']);

            GitHubCredential::factory()->create();

            $this->browse(function (Browser $browser) {
                $browser->visit('/wizard?step=code_server')
                    ->assertPathIs('/wizard')
                    ->assertSee('Code:');
            });
        });

        it('loads wizard tunnel step', function () {
            $progressService = app(WizardProgressService::class);
            $progressService->completeStep(WizardStep::Welcome, ['timezone' => 'UTC']);
            $progressService->completeStep(WizardStep::AiServices, ['providers_configured' => 0]);
            $progressService->completeStep(WizardStep::GitHub, ['username' => 'testuser']);
            $progressService->completeStep(WizardStep::CodeServer, ['theme' => 'Default Dark+']);

            $this->browse(function (Browser $browser) {
                $browser->visit('/wizard?step=tunnel')
                    ->assertPathIs('/wizard')
                    ->assertSee('Remote Access');
            });
        });

        it('loads wizard complete step', function () {
            $progressService = app(WizardProgressService::class);
            $progressService->completeStep(WizardStep::Welcome, ['timezone' => 'UTC']);
            $progressService->completeStep(WizardStep::AiServices, ['providers_configured' => 0]);
            $progressService->completeStep(WizardStep::GitHub, ['username' => 'testuser']);
            $progressService->completeStep(WizardStep::CodeServer, ['theme' => 'Default Dark+']);
            $progressService->completeStep(WizardStep::Tunnel, ['subdomain' => 'test']);

            $this->browse(function (Browser $browser) {
                $browser->visit('/wizard?step=complete')
                    ->assertPathIs('/wizard')
                    ->assertSee('Done');
            });
        });

        it('wizard step navigation via query parameters', function () {
            $this->browse(function (Browser $browser) {
                $steps = [
                    'welcome' => 'Welcome',
                    'ai_services' => 'AI Services',
                    'github' => 'GitHub',
                    'code_server' => 'Code:',
                    'tunnel' => 'Remote Access',
                ];

                foreach ($steps as $step => $expectedText) {
                    $browser->visit("/wizard?step={$step}")
                        ->assertPathIs('/wizard')
                        ->assertSee($expectedText);
                }
            });
        });
    });

    describe('Dashboard Overview Page', function () {

        beforeEach(function () {
            DeviceState::setValue(DeviceStateService::MODE_KEY, DeviceStateService::MODE_DASHBOARD);
            CloudCredential::factory()->paired()->create();
        });

        it('loads dashboard overview with system metrics', function () {
            $this->browse(function (Browser $browser) {
                $browser->visit('/dashboard')
                    ->assertPathIs('/dashboard')
                    ->assertSee('VibeCodePC')
                    ->assertSee('Overview');
            });
        });

        it('displays health bar with system metrics', function () {
            $this->browse(function (Browser $browser) {
                $browser->visit('/dashboard')
                    ->assertSee('CPU')
                    ->assertSee('Memory')
                    ->assertSee('Disk');
            });
        });
    });

    describe('Project Pages', function () {

        beforeEach(function () {
            DeviceState::setValue(DeviceStateService::MODE_KEY, DeviceStateService::MODE_DASHBOARD);
            CloudCredential::factory()->paired()->create();
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
                'name' => 'Test Detail Project',
                'path' => '/tmp/test-detail',
            ]);

            $this->browse(function (Browser $browser) use ($project) {
                $browser->visit("/dashboard/projects/{$project->id}")
                    ->assertPathIs("/dashboard/projects/{$project->id}");
            });
        });

        it('handles non-existent project gracefully', function () {
            $this->browse(function (Browser $browser) {
                $browser->visit('/dashboard/projects/99999')
                    ->assertPathIs('/dashboard/projects/99999');
            });
        });
    });

    describe('AI Configuration Pages', function () {

        beforeEach(function () {
            DeviceState::setValue(DeviceStateService::MODE_KEY, DeviceStateService::MODE_DASHBOARD);
            CloudCredential::factory()->paired()->create();
        });

        it('loads AI agents config page', function () {
            $this->browse(function (Browser $browser) {
                $browser->visit('/dashboard/ai-agents')
                    ->assertPathIs('/dashboard/ai-agents')
                    ->assertSee('AI Agent Configs');
            });
        });

        it('loads AI tools config page', function () {
            $this->browse(function (Browser $browser) {
                $browser->visit('/dashboard/ai-tools')
                    ->assertPathIs('/dashboard/ai-tools')
                    ->assertSee('AI Tools');
            });
        });

        it('loads AI services hub page', function () {
            $this->browse(function (Browser $browser) {
                $browser->visit('/dashboard/ai-services')
                    ->assertPathIs('/dashboard/ai-services')
                    ->assertSee('AI Services');
            });
        });
    });

    describe('System and Infrastructure Pages', function () {

        beforeEach(function () {
            DeviceState::setValue(DeviceStateService::MODE_KEY, DeviceStateService::MODE_DASHBOARD);
            CloudCredential::factory()->paired()->create();
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
                    ->assertSee('Tunnel');
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

    describe('Pairing Page', function () {

        beforeEach(function () {
            DeviceState::setValue(DeviceStateService::MODE_KEY, DeviceStateService::MODE_PAIRING);
        });

        it('loads pairing page', function () {
            $this->browse(function (Browser $browser) {
                $browser->visit('/pairing')
                    ->assertPathIs('/pairing')
                    ->assertSee('Pairing');
            });
        });

        it('displays pairing instructions', function () {
            $this->browse(function (Browser $browser) {
                $browser->visit('/pairing')
                    ->assertSee('VibeCodePC');
            });
        });

        it('shows skip pairing option when available', function () {
            $this->browse(function (Browser $browser) {
                $browser->visit('/pairing')
                    ->assertPresent('button');
            });
        });
    });

    describe('Tunnel Login Page', function () {

        beforeEach(function () {
            DeviceState::setValue(DeviceStateService::MODE_KEY, DeviceStateService::MODE_DASHBOARD);
        });

        it('loads tunnel login page', function () {
            $this->browse(function (Browser $browser) {
                $browser->visit('/tunnel/login')
                    ->assertPathIs('/tunnel/login')
                    ->assertSee('Device Access');
            });
        });

        it('displays password input field', function () {
            $this->browse(function (Browser $browser) {
                $browser->visit('/tunnel/login')
                    ->assertPresent('input[type="password"]');
            });
        });

        it('displays submit button', function () {
            $this->browse(function (Browser $browser) {
                $browser->visit('/tunnel/login')
                    ->assertPresent('button[type="submit"]');
            });
        });
    });

    describe('Home Route Redirects', function () {

        it('redirects to pairing when in pairing mode', function () {
            DeviceState::setValue(DeviceStateService::MODE_KEY, DeviceStateService::MODE_PAIRING);

            $this->browse(function (Browser $browser) {
                $browser->visit('/')
                    ->assertPathIs('/pairing');
            });
        });

        it('redirects to wizard when in wizard mode', function () {
            DeviceState::setValue(DeviceStateService::MODE_KEY, DeviceStateService::MODE_WIZARD);

            $this->browse(function (Browser $browser) {
                $browser->visit('/')
                    ->assertPathIs('/wizard');
            });
        });

        it('redirects to dashboard when in dashboard mode', function () {
            DeviceState::setValue(DeviceStateService::MODE_KEY, DeviceStateService::MODE_DASHBOARD);
            CloudCredential::factory()->paired()->create();

            $this->browse(function (Browser $browser) {
                $browser->visit('/')
                    ->assertPathIs('/dashboard');
            });
        });
    });

    describe('Schema Endpoints', function () {

        it('returns 404 for non-existent schema', function () {
            $this->browse(function (Browser $browser) {
                $browser->visit('/schemas/nonexistent.json')
                    ->assertSee('error');
            });
        });
    });

    describe('Page Layouts', function () {

        beforeEach(function () {
            DeviceState::setValue(DeviceStateService::MODE_KEY, DeviceStateService::MODE_DASHBOARD);
            CloudCredential::factory()->paired()->create();
        });

        it('renders dashboard with navigation sidebar', function () {
            $this->browse(function (Browser $browser) {
                $browser->visit('/dashboard')
                    ->assertPresent('nav')
                    ->assertSee('Overview')
                    ->assertSee('Projects')
                    ->assertSee('Settings');
            });
        });

        it('renders dashboard with top bar', function () {
            $this->browse(function (Browser $browser) {
                $browser->visit('/dashboard')
                    ->assertPresent('header');
            });
        });
    });

    describe('Responsive Rendering', function () {

        beforeEach(function () {
            DeviceState::setValue(DeviceStateService::MODE_KEY, DeviceStateService::MODE_DASHBOARD);
            CloudCredential::factory()->paired()->create();
        });

        it('renders at mobile viewport (iPhone)', function () {
            $this->browse(function (Browser $browser) {
                $browser->visit('/dashboard')
                    ->resize(375, 667)
                    ->assertSee('VibeCodePC');
            });
        });

        it('renders at tablet viewport (iPad)', function () {
            $this->browse(function (Browser $browser) {
                $browser->visit('/dashboard')
                    ->resize(768, 1024)
                    ->assertSee('VibeCodePC');
            });
        });

        it('renders at desktop viewport', function () {
            $this->browse(function (Browser $browser) {
                $browser->visit('/dashboard')
                    ->resize(1920, 1080)
                    ->assertSee('VibeCodePC')
                    ->assertPresent('nav');
            });
        });
    });

    describe('Livewire Components Loading', function () {

        beforeEach(function () {
            DeviceState::setValue(DeviceStateService::MODE_KEY, DeviceStateService::MODE_DASHBOARD);
            CloudCredential::factory()->paired()->create();
        });

        it('loads Livewire components on projects page', function () {
            $this->browse(function (Browser $browser) {
                $browser->visit('/dashboard/projects')
                    ->assertPathIs('/dashboard/projects');
            });
        });

        it('loads Livewire components on AI agents page', function () {
            $this->browse(function (Browser $browser) {
                $browser->visit('/dashboard/ai-agents')
                    ->assertPathIs('/dashboard/ai-agents');
            });
        });

        it('loads Livewire components on settings page', function () {
            $this->browse(function (Browser $browser) {
                $browser->visit('/dashboard/settings')
                    ->assertPathIs('/dashboard/settings');
            });
        });
    });

    describe('Navigation Flows', function () {

        beforeEach(function () {
            DeviceState::setValue(DeviceStateService::MODE_KEY, DeviceStateService::MODE_DASHBOARD);
            CloudCredential::factory()->paired()->create();
        });

        it('navigates from overview to projects', function () {
            $this->browse(function (Browser $browser) {
                $browser->visit('/dashboard')
                    ->clickLink('Projects')
                    ->waitForLocation('/dashboard/projects')
                    ->assertPathIs('/dashboard/projects');
            });
        });

        it('navigates from projects to AI agents', function () {
            $this->browse(function (Browser $browser) {
                $browser->visit('/dashboard/projects')
                    ->clickLink('AI Agent Configs')
                    ->waitForLocation('/dashboard/ai-agents')
                    ->assertPathIs('/dashboard/ai-agents');
            });
        });

        it('navigates from dashboard to settings', function () {
            $this->browse(function (Browser $browser) {
                $browser->visit('/dashboard')
                    ->clickLink('Settings')
                    ->waitForLocation('/dashboard/settings')
                    ->assertPathIs('/dashboard/settings');
            });
        });
    });
});
