<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\AiProviderConfig;
use App\Models\AnalyticsEvent;
use App\Models\CloudCredential;
use App\Models\DeviceState;
use App\Models\GitHubCredential;
use App\Models\Project;
use App\Models\ProjectLog;
use App\Models\QuickTunnel;
use App\Models\TunnelConfig;
use App\Models\User;
use App\Models\WizardProgress;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use VibecodePC\Common\Enums\AiProvider;
use VibecodePC\Common\Enums\ProjectFramework;
use VibecodePC\Common\Enums\ProjectStatus;
use VibecodePC\Common\Enums\WizardStep;
use VibecodePC\Common\Enums\WizardStepStatus;

class DevelopmentSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database with development sample data.
     */
    public function run(): void
    {
        $this->command->info('Seeding development environment with sample data...');

        $this->seedUsers();
        $this->seedCloudCredentials();
        $this->seedGitHubCredentials();
        $this->seedAiProviderConfigs();
        $this->seedTunnelConfigs();
        $this->seedProjects();
        $this->seedQuickTunnels();
        $this->seedWizardProgress();
        $this->seedAnalyticsEvents();
        $this->seedDeviceState();

        $this->command->info('Development seeding completed successfully!');
    }

    /**
     * Seed users for development.
     */
    private function seedUsers(): void
    {
        $this->command->info('Creating users...');

        User::factory()->create([
            'name' => 'Developer',
            'email' => 'dev@example.com',
        ]);

        User::factory()->create([
            'name' => 'Test Admin',
            'email' => 'admin@example.com',
        ]);

        User::factory(3)->create();
    }

    /**
     * Seed cloud credentials (paired and unpaired states).
     */
    private function seedCloudCredentials(): void
    {
        $this->command->info('Creating cloud credentials...');

        CloudCredential::factory()->paired()->create([
            'cloud_username' => 'vibecode-dev',
            'cloud_email' => 'dev@vibecodepc.com',
            'cloud_url' => 'https://cloud.vibecodepc.com',
        ]);

        CloudCredential::factory()->unpaired()->create();
    }

    /**
     * Seed GitHub credentials.
     */
    private function seedGitHubCredentials(): void
    {
        $this->command->info('Creating GitHub credentials...');

        GitHubCredential::factory()->create([
            'github_username' => 'vibecode-developer',
            'github_email' => 'dev@example.com',
            'github_name' => 'VibeCode Developer',
            'has_copilot' => true,
        ]);

        GitHubCredential::factory()->withCopilot()->create([
            'github_username' => 'copilot-user',
            'github_email' => 'copilot@example.com',
        ]);
    }

    /**
     * Seed AI provider configurations.
     */
    private function seedAiProviderConfigs(): void
    {
        $this->command->info('Creating AI provider configurations...');

        AiProviderConfig::factory()->validated()->create([
            'provider' => AiProvider::OpenAI,
            'display_name' => 'OpenAI GPT-4',
            'base_url' => 'https://api.openai.com/v1',
        ]);

        AiProviderConfig::factory()->validated()->create([
            'provider' => AiProvider::Anthropic,
            'display_name' => 'Anthropic Claude',
        ]);

        AiProviderConfig::factory()->create([
            'provider' => AiProvider::Custom,
            'display_name' => 'Local Ollama',
            'base_url' => 'http://localhost:11434',
        ]);
    }

    /**
     * Seed tunnel configurations.
     */
    private function seedTunnelConfigs(): void
    {
        $this->command->info('Creating tunnel configurations...');

        TunnelConfig::factory()->verified()->create([
            'subdomain' => 'dev-device-001',
            'tunnel_id' => 'test-tunnel-001',
        ]);

        TunnelConfig::factory()->active()->create([
            'subdomain' => 'dev-device-002',
            'tunnel_id' => 'test-tunnel-002',
        ]);

        TunnelConfig::factory()->available()->create([
            'subdomain' => 'dev-device-003',
        ]);

        TunnelConfig::factory()->skipped()->create([
            'subdomain' => 'dev-device-004',
        ]);

        TunnelConfig::factory()->create([
            'subdomain' => 'dev-device-005',
            'status' => 'pending',
        ]);
    }

    /**
     * Seed projects with various states and configurations.
     */
    private function seedProjects(): void
    {
        $this->command->info('Creating projects...');

        $runningProject = Project::factory()->running()->create([
            'name' => 'Laravel Blog',
            'framework' => ProjectFramework::Laravel,
            'tunnel_enabled' => true,
            'tunnel_subdomain_path' => 'blog',
        ]);
        $this->seedProjectLogs($runningProject, 5);

        $stoppedProject = Project::factory()->stopped()->create([
            'name' => 'Astro Site',
            'framework' => ProjectFramework::Astro,
            'tunnel_enabled' => true,
            'tunnel_subdomain_path' => 'astro-site',
        ]);
        $this->seedProjectLogs($stoppedProject, 3);

        $laravelProject = Project::factory()->cloned()->running()->create([
            'name' => 'API Service',
            'framework' => ProjectFramework::Laravel,
            'clone_url' => 'https://github.com/laravel/laravel.git',
            'tunnel_enabled' => false,
        ]);
        $this->seedProjectLogs($laravelProject, 8);

        $nextjsProject = Project::factory()->cloned()->forFramework(ProjectFramework::NextJs)->create([
            'name' => 'Marketing Site',
            'framework' => ProjectFramework::NextJs,
            'clone_url' => 'https://github.com/vercel/next.js.git',
            'status' => ProjectStatus::Scaffolding,
        ]);
        $this->seedProjectLogs($nextjsProject, 2);

        $fastApiProject = Project::factory()->forFramework(ProjectFramework::FastApi)->create([
            'name' => 'Python API',
            'framework' => ProjectFramework::FastApi,
            'status' => ProjectStatus::Created,
        ]);

        Project::factory(3)->create();
    }

    /**
     * Seed project logs for a specific project.
     */
    private function seedProjectLogs(Project $project, int $count): void
    {
        $types = ['info', 'warning', 'error', 'scaffold', 'docker'];

        foreach (range(1, $count) as $i) {
            ProjectLog::factory()->create([
                'project_id' => $project->id,
                'type' => $types[array_rand($types)],
                'message' => fake()->sentence(),
                'metadata' => [
                    'timestamp' => now()->subMinutes($i * 10)->toIso8601String(),
                    'source' => fake()->randomElement(['docker', 'git', 'npm', 'artisan']),
                ],
            ]);
        }
    }

    /**
     * Seed quick tunnels.
     */
    private function seedQuickTunnels(): void
    {
        $this->command->info('Creating quick tunnels...');

        QuickTunnel::factory()->running()->dashboard()->create([
            'container_name' => 'dev-dashboard-tunnel',
            'local_port' => 8080,
            'tunnel_url' => 'https://dev-dashboard-tunnel.trycloudflare.com',
        ]);

        QuickTunnel::factory()->running()->forProject()->create([
            'container_name' => 'project-tunnel-001',
            'local_port' => 3000,
            'tunnel_url' => 'https://project-001.trycloudflare.com',
        ]);

        QuickTunnel::factory()->stopped()->dashboard()->create([
            'container_name' => 'old-dashboard-tunnel',
            'local_port' => 8080,
        ]);

        QuickTunnel::factory()->starting()->forProject()->create([
            'container_name' => 'starting-tunnel',
            'local_port' => 5000,
        ]);

        QuickTunnel::factory()->error()->dashboard()->create([
            'container_name' => 'failed-tunnel',
            'local_port' => 9000,
        ]);
    }

    /**
     * Seed wizard progress steps.
     */
    private function seedWizardProgress(): void
    {
        $this->command->info('Creating wizard progress...');

        $completedSteps = [
            WizardStep::Welcome,
            WizardStep::AiServices,
            WizardStep::GitHub,
            WizardStep::CodeServer,
        ];

        foreach ($completedSteps as $step) {
            WizardProgress::updateOrCreate(
                ['step' => $step],
                [
                    'status' => WizardStepStatus::Completed,
                    'data_json' => [
                        'completed_by' => 'developer',
                        'ip_address' => fake()->ipv4(),
                    ],
                    'completed_at' => now(),
                ]
            );
        }

        WizardProgress::updateOrCreate(
            ['step' => WizardStep::Tunnel],
            [
                'status' => WizardStepStatus::Pending,
                'data_json' => null,
                'completed_at' => null,
            ]
        );

        WizardProgress::updateOrCreate(
            ['step' => WizardStep::Complete],
            [
                'status' => WizardStepStatus::Skipped,
                'data_json' => null,
                'completed_at' => now(),
            ]
        );
    }

    /**
     * Seed analytics events.
     */
    private function seedAnalyticsEvents(): void
    {
        $this->command->info('Creating analytics events...');

        AnalyticsEvent::factory()->tunnelCompleted()->create([
            'occurred_at' => now()->subDays(2),
        ]);

        AnalyticsEvent::factory()->tunnelSkipped()->create([
            'occurred_at' => now()->subDay(),
        ]);

        AnalyticsEvent::factory(5)->create([
            'event_type' => 'project.created',
            'category' => 'project',
            'properties' => [
                'framework' => fake()->randomElement(['laravel', 'nextjs', 'astro', 'fastapi']),
                'source' => 'wizard',
            ],
            'occurred_at' => fake()->dateTimeBetween('-1 week', 'now'),
        ]);

        AnalyticsEvent::factory(3)->create([
            'event_type' => 'project.started',
            'category' => 'project',
            'properties' => [
                'duration_seconds' => fake()->numberBetween(10, 300),
            ],
            'occurred_at' => fake()->dateTimeBetween('-3 days', 'now'),
        ]);

        AnalyticsEvent::factory(2)->create([
            'event_type' => 'wizard.step_completed',
            'category' => 'wizard',
            'properties' => [
                'step' => fake()->randomElement(['welcome', 'ai_services', 'github', 'code_server', 'tunnel']),
            ],
            'occurred_at' => fake()->dateTimeBetween('-1 week', 'now'),
        ]);
    }

    /**
     * Seed device state.
     */
    private function seedDeviceState(): void
    {
        $this->command->info('Creating device state...');

        DeviceState::setValue('device_id', 'dev-device-'.fake()->uuid());
        DeviceState::setValue('setup_completed', 'true');
        DeviceState::setValue('last_setup_step', 'tunnel');
        DeviceState::setValue('network.interface', 'eth0');
        DeviceState::setValue('network.ip', fake()->localIpv4());
        DeviceState::setValue('docker.version', '24.0.7');
        DeviceState::setValue('last_backup_at', now()->subDays(3)->toIso8601String());
    }
}
