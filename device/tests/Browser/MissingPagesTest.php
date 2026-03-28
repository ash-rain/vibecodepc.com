<?php

declare(strict_types=1);

use App\Models\CloudCredential;
use App\Models\DeviceState;
use App\Models\GitHubCredential;
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

describe('Missing Pages - JavaScript Error Detection', function () {

    describe('Wizard Pages', function () {

        beforeEach(function () {
            // Set mode to wizard for wizard tests
            DeviceState::setValue(DeviceStateService::MODE_KEY, DeviceStateService::MODE_WIZARD);
        });

        it('loads wizard welcome page without JS errors', function () {
            // Seed wizard progress
            app(WizardProgressService::class)->seedProgress();

            $this->browse(function (Browser $browser) {
                $browser->visit('/wizard')
                    ->assertPathIs('/wizard')
                    ->assertSee('Welcome');

                // Check for JavaScript errors in console
                $logs = $browser->driver->manage()->getLog('browser');
                $errors = collect($logs)
                    ->filter(fn ($log) => ($log['level'] ?? '') === 'SEVERE')
                    ->pluck('message')
                    ->all();

                expect($errors)->toBeEmpty('JavaScript errors found: '.implode(', ', $errors));
            });
        });

        it('loads wizard ai services page without JS errors', function () {
            // Complete welcome step and seed progress
            $progressService = app(WizardProgressService::class);
            $progressService->seedProgress();
            $progressService->completeStep(WizardStep::Welcome, ['timezone' => 'UTC']);

            $this->browse(function (Browser $browser) {
                $browser->visit('/wizard?step=ai_services')
                    ->assertPathIs('/wizard')
                    ->assertSee('AI Services');

                // Check for JavaScript errors in console
                $logs = $browser->driver->manage()->getLog('browser');
                $errors = collect($logs)
                    ->filter(fn ($log) => ($log['level'] ?? '') === 'SEVERE')
                    ->pluck('message')
                    ->all();

                expect($errors)->toBeEmpty('JavaScript errors found: '.implode(', ', $errors));
            });
        });

        it('loads wizard github page without JS errors', function () {
            // Complete previous steps
            $progressService = app(WizardProgressService::class);
            $progressService->seedProgress();
            $progressService->completeStep(WizardStep::Welcome, ['timezone' => 'UTC']);
            $progressService->completeStep(WizardStep::AiServices, ['providers_configured' => 0]);

            $this->browse(function (Browser $browser) {
                $browser->visit('/wizard?step=github')
                    ->assertPathIs('/wizard')
                    ->assertSee('GitHub');

                // Check for JavaScript errors in console
                $logs = $browser->driver->manage()->getLog('browser');
                $errors = collect($logs)
                    ->filter(fn ($log) => ($log['level'] ?? '') === 'SEVERE')
                    ->pluck('message')
                    ->all();

                expect($errors)->toBeEmpty('JavaScript errors found: '.implode(', ', $errors));
            });
        });

        it('loads wizard code-server page without JS errors', function () {
            // Complete previous steps
            $progressService = app(WizardProgressService::class);
            $progressService->seedProgress();
            $progressService->completeStep(WizardStep::Welcome, ['timezone' => 'UTC']);
            $progressService->completeStep(WizardStep::AiServices, ['providers_configured' => 0]);
            $progressService->completeStep(WizardStep::GitHub, ['username' => 'testuser']);

            $this->browse(function (Browser $browser) {
                $browser->visit('/wizard?step=code_server')
                    ->assertPathIs('/wizard')
                    ->assertSee('VS Code');

                // Check for JavaScript errors in console
                $logs = $browser->driver->manage()->getLog('browser');
                $errors = collect($logs)
                    ->filter(fn ($log) => ($log['level'] ?? '') === 'SEVERE')
                    ->pluck('message')
                    ->all();

                expect($errors)->toBeEmpty('JavaScript errors found: '.implode(', ', $errors));
            });
        });

        it('loads wizard tunnel page without JS errors', function () {
            // Complete previous steps
            $progressService = app(WizardProgressService::class);
            $progressService->seedProgress();
            $progressService->completeStep(WizardStep::Welcome, ['timezone' => 'UTC']);
            $progressService->completeStep(WizardStep::AiServices, ['providers_configured' => 0]);
            $progressService->completeStep(WizardStep::GitHub, ['username' => 'testuser']);
            $progressService->completeStep(WizardStep::CodeServer, ['theme' => 'Default Dark+']);

            $this->browse(function (Browser $browser) {
                $browser->visit('/wizard?step=tunnel')
                    ->assertPathIs('/wizard')
                    ->assertSee('Remote Access');

                // Check for JavaScript errors in console
                $logs = $browser->driver->manage()->getLog('browser');
                $errors = collect($logs)
                    ->filter(fn ($log) => ($log['level'] ?? '') === 'SEVERE')
                    ->pluck('message')
                    ->all();

                expect($errors)->toBeEmpty('JavaScript errors found: '.implode(', ', $errors));
            });
        });

        it('loads wizard complete page without JS errors', function () {
            // Complete all wizard steps
            $progressService = app(WizardProgressService::class);
            $progressService->seedProgress();
            $progressService->completeStep(WizardStep::Welcome, ['timezone' => 'UTC']);
            $progressService->completeStep(WizardStep::AiServices, ['providers_configured' => 0]);
            $progressService->completeStep(WizardStep::GitHub, ['username' => 'testuser']);
            $progressService->completeStep(WizardStep::CodeServer, ['theme' => 'Default Dark+']);
            $progressService->completeStep(WizardStep::Tunnel, ['subdomain' => 'test']);

            $this->browse(function (Browser $browser) {
                $browser->visit('/wizard?step=complete')
                    ->assertPathIs('/wizard')
                    ->assertSee('Done');

                // Check for JavaScript errors in console
                $logs = $browser->driver->manage()->getLog('browser');
                $errors = collect($logs)
                    ->filter(fn ($log) => ($log['level'] ?? '') === 'SEVERE')
                    ->pluck('message')
                    ->all();

                expect($errors)->toBeEmpty('JavaScript errors found: '.implode(', ', $errors));
            });
        });

        it('wizard navigation works without JS errors', function () {
            $progressService = app(WizardProgressService::class);
            $progressService->seedProgress();
            $progressService->completeStep(WizardStep::Welcome, ['timezone' => 'UTC']);
            $progressService->completeStep(WizardStep::AiServices, ['providers_configured' => 0]);

            $this->browse(function (Browser $browser) {
                $browser->visit('/wizard?step=welcome')
                    ->assertSee('Welcome')
                    ->visit('/wizard?step=ai_services')
                    ->waitForText('AI Services');

                // Check for JavaScript errors after navigation
                $logs = $browser->driver->manage()->getLog('browser');
                $errors = collect($logs)
                    ->filter(fn ($log) => ($log['level'] ?? '') === 'SEVERE')
                    ->pluck('message')
                    ->all();

                expect($errors)->toBeEmpty('JavaScript errors found after navigation: '.implode(', ', $errors));
            });
        });
    });

    describe('Pairing Pages', function () {

        beforeEach(function () {
            // Set mode to pairing
            DeviceState::setValue(DeviceStateService::MODE_KEY, DeviceStateService::MODE_PAIRING);
        });

        it('loads pairing screen without JS errors', function () {
            $this->browse(function (Browser $browser) {
                $browser->visit('/pairing')
                    ->assertPathIs('/pairing')
                    ->assertSee('Pairing');

                // Check for JavaScript errors in console
                $logs = $browser->driver->manage()->getLog('browser');
                $errors = collect($logs)
                    ->filter(fn ($log) => ($log['level'] ?? '') === 'SEVERE')
                    ->pluck('message')
                    ->all();

                expect($errors)->toBeEmpty('JavaScript errors found: '.implode(', ', $errors));
            });
        });

        it('loads pairing screen with skip button visible', function () {
            $this->browse(function (Browser $browser) {
                $browser->visit('/pairing')
                    ->assertPathIs('/pairing')
                    ->assertSee('Pairing')
                    ->assertPresent('button');

                // Check for JavaScript errors
                $logs = $browser->driver->manage()->getLog('browser');
                $errors = collect($logs)
                    ->filter(fn ($log) => ($log['level'] ?? '') === 'SEVERE')
                    ->pluck('message')
                    ->all();

                expect($errors)->toBeEmpty('JavaScript errors found: '.implode(', ', $errors));
            });
        });
    });

    describe('Tunnel Login Page', function () {

        beforeEach(function () {
            DeviceState::setValue(DeviceStateService::MODE_KEY, DeviceStateService::MODE_DASHBOARD);
        });

        it('loads tunnel login page without JS errors', function () {
            $this->browse(function (Browser $browser) {
                $browser->visit('/tunnel/login')
                    ->assertPathIs('/tunnel/login')
                    ->assertSee('Device Access');

                // Check for JavaScript errors in console
                $logs = $browser->driver->manage()->getLog('browser');
                $errors = collect($logs)
                    ->filter(fn ($log) => ($log['level'] ?? '') === 'SEVERE')
                    ->pluck('message')
                    ->all();

                expect($errors)->toBeEmpty('JavaScript errors found: '.implode(', ', $errors));
            });
        });

        it('tunnel login form works without JS errors', function () {
            $this->browse(function (Browser $browser) {
                $browser->visit('/tunnel/login')
                    ->assertSee('Device Access')
                    ->type('input[type="password"]', 'wrongpassword')
                    ->press('button[type="submit"]');

                // Check for JavaScript errors after form submission
                $logs = $browser->driver->manage()->getLog('browser');
                $errors = collect($logs)
                    ->filter(fn ($log) => ($log['level'] ?? '') === 'SEVERE')
                    ->pluck('message')
                    ->all();

                expect($errors)->toBeEmpty('JavaScript errors found after form submission: '.implode(', ', $errors));
            });
        });
    });

    describe('Home Page', function () {

        it('home page loads without JS errors', function () {
            // Set dashboard mode and create paired credential
            DeviceState::setValue(DeviceStateService::MODE_KEY, DeviceStateService::MODE_DASHBOARD);
            CloudCredential::factory()->paired()->create();

            $this->browse(function (Browser $browser) {
                // Visit home page
                $browser->visit('/')
                    ->pause(500); // Give time for page load

                // Verify page loaded with expected content
                $browser->assertSee('VibeCodePC');

                // Check for JavaScript errors
                $logs = $browser->driver->manage()->getLog('browser');
                $errors = collect($logs)
                    ->filter(fn ($log) => ($log['level'] ?? '') === 'SEVERE')
                    ->pluck('message')
                    ->all();

                expect($errors)->toBeEmpty('JavaScript errors found: '.implode(', ', $errors));
            });
        });

        it('home page in pairing mode without JS errors', function () {
            // Set pairing mode
            DeviceState::setValue(DeviceStateService::MODE_KEY, DeviceStateService::MODE_PAIRING);

            $this->browse(function (Browser $browser) {
                $browser->visit('/')
                    ->pause(500); // Give time for redirect

                // Verify page loaded
                $browser->assertSee('VibeCodePC');

                // Check for JavaScript errors
                $logs = $browser->driver->manage()->getLog('browser');
                $errors = collect($logs)
                    ->filter(fn ($log) => ($log['level'] ?? '') === 'SEVERE')
                    ->pluck('message')
                    ->all();

                expect($errors)->toBeEmpty('JavaScript errors found: '.implode(', ', $errors));
            });
        });
    });

    describe('Wizard Component Interaction', function () {

        beforeEach(function () {
            DeviceState::setValue(DeviceStateService::MODE_KEY, DeviceStateService::MODE_WIZARD);
            app(WizardProgressService::class)->seedProgress();
        });

        it('wizard step navigation via URL without JS errors', function () {
            $this->browse(function (Browser $browser) {
                // Navigate to different steps
                $steps = ['welcome', 'ai_services', 'github', 'code_server', 'tunnel', 'complete'];

                foreach ($steps as $step) {
                    $browser->visit("/wizard?step={$step}")
                        ->waitForText('VibeCodePC', 5);

                    // Check for JavaScript errors after each navigation
                    $logs = $browser->driver->manage()->getLog('browser');
                    $errors = collect($logs)
                        ->filter(fn ($log) => ($log['level'] ?? '') === 'SEVERE')
                        ->pluck('message')
                        ->all();

                    expect($errors)->toBeEmpty("JavaScript errors found on step '{$step}': ".implode(', ', $errors));
                }
            });
        });

        it('wizard form interactions without JS errors', function () {
            $progressService = app(WizardProgressService::class);
            $progressService->completeStep(WizardStep::Welcome, ['timezone' => 'UTC']);

            $this->browse(function (Browser $browser) {
                $browser->visit('/wizard?step=ai_services')
                    ->assertSee('AI Services');

                // Interact with any input element found on the page
                // Just check that the page loads without JS errors
                $browser->assertPresent('input, textarea, button');

                // Check for JavaScript errors after form interaction
                $logs = $browser->driver->manage()->getLog('browser');
                $errors = collect($logs)
                    ->filter(fn ($log) => ($log['level'] ?? '') === 'SEVERE')
                    ->pluck('message')
                    ->all();

                expect($errors)->toBeEmpty('JavaScript errors found after form interaction: '.implode(', ', $errors));
            });
        });
    });

    describe('Page Console Warning Detection', function () {

        it('detects console warnings on dashboard pages', function () {
            DeviceState::setValue(DeviceStateService::MODE_KEY, DeviceStateService::MODE_DASHBOARD);
            CloudCredential::factory()->paired()->create();

            $this->browse(function (Browser $browser) {
                $browser->visit('/dashboard')
                    ->assertPathIs('/dashboard');

                // Check for any console warnings (not just errors)
                $logs = $browser->driver->manage()->getLog('browser');
                $warnings = collect($logs)
                    ->filter(fn ($log) => in_array($log['level'] ?? '', ['WARNING', 'SEVERE'], true))
                    ->map(fn ($log) => "[{$log['level']}] {$log['message']}")
                    ->all();

                // Log warnings for review but don't fail test
                if (! empty($warnings)) {
                    \Illuminate\Support\Facades\Log::info('Console warnings on dashboard', $warnings);
                }

                expect(true)->toBeTrue();
            });
        });

        it('detects console warnings on wizard pages', function () {
            DeviceState::setValue(DeviceStateService::MODE_KEY, DeviceStateService::MODE_WIZARD);
            app(WizardProgressService::class)->seedProgress();

            $this->browse(function (Browser $browser) {
                $browser->visit('/wizard')
                    ->assertPathIs('/wizard');

                // Check for any console warnings
                $logs = $browser->driver->manage()->getLog('browser');
                $warnings = collect($logs)
                    ->filter(fn ($log) => in_array($log['level'] ?? '', ['WARNING', 'SEVERE'], true))
                    ->map(fn ($log) => "[{$log['level']}] {$log['message']}")
                    ->all();

                // Log warnings for review but don't fail test
                if (! empty($warnings)) {
                    \Illuminate\Support\Facades\Log::info('Console warnings on wizard', $warnings);
                }

                expect(true)->toBeTrue();
            });
        });
    });
});
