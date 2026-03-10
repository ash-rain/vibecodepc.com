<?php

declare(strict_types=1);

use App\Models\Project;
use App\Models\ProjectLog;
use App\Models\TunnelConfig;
use Illuminate\Foundation\Testing\RefreshDatabase;
use VibecodePC\Common\Enums\ProjectFramework;
use VibecodePC\Common\Enums\ProjectStatus;

uses(RefreshDatabase::class);

// ============================================================================
// Status Check Methods
// ============================================================================

it('returns true for isRunning when status is Running', function () {
    $project = Project::factory()->create(['status' => ProjectStatus::Running]);

    expect($project->isRunning())->toBeTrue();
});

it('returns false for isRunning when status is not Running', function () {
    $project = Project::factory()->create(['status' => ProjectStatus::Stopped]);

    expect($project->isRunning())->toBeFalse();
});

it('returns true for isStopped when status is Stopped', function () {
    $project = Project::factory()->create(['status' => ProjectStatus::Stopped]);

    expect($project->isStopped())->toBeTrue();
});

it('returns false for isStopped when status is not Stopped', function () {
    $project = Project::factory()->create(['status' => ProjectStatus::Running]);

    expect($project->isStopped())->toBeFalse();
});

it('returns true for isProvisioning when status is Scaffolding', function () {
    $project = Project::factory()->create(['status' => ProjectStatus::Scaffolding]);

    expect($project->isProvisioning())->toBeTrue();
});

it('returns true for isProvisioning when status is Cloning', function () {
    $project = Project::factory()->create(['status' => ProjectStatus::Cloning]);

    expect($project->isProvisioning())->toBeTrue();
});

it('returns false for isProvisioning when status is Created', function () {
    $project = Project::factory()->create(['status' => ProjectStatus::Created]);

    expect($project->isProvisioning())->toBeFalse();
});

it('returns false for isProvisioning when status is Running', function () {
    $project = Project::factory()->create(['status' => ProjectStatus::Running]);

    expect($project->isProvisioning())->toBeFalse();
});

it('returns false for isProvisioning when status is Error', function () {
    $project = Project::factory()->create(['status' => ProjectStatus::Error]);

    expect($project->isProvisioning())->toBeFalse();
});

it('returns false for isProvisioning when status is Stopped', function () {
    $project = Project::factory()->create(['status' => ProjectStatus::Stopped]);

    expect($project->isProvisioning())->toBeFalse();
});

// ============================================================================
// Status Transitions
// ============================================================================

it('can transition from Created to Scaffolding', function () {
    $project = Project::factory()->create(['status' => ProjectStatus::Created]);

    $project->update(['status' => ProjectStatus::Scaffolding]);

    expect($project->fresh()->status)->toBe(ProjectStatus::Scaffolding);
});

it('can transition from Scaffolding to Cloning', function () {
    $project = Project::factory()->create(['status' => ProjectStatus::Scaffolding]);

    $project->update(['status' => ProjectStatus::Cloning]);

    expect($project->fresh()->status)->toBe(ProjectStatus::Cloning);
});

it('can transition from Cloning to Running', function () {
    $project = Project::factory()->create(['status' => ProjectStatus::Cloning]);

    $project->update(['status' => ProjectStatus::Running]);

    expect($project->fresh()->status)->toBe(ProjectStatus::Running);
});

it('can transition from Running to Stopped', function () {
    $project = Project::factory()->running()->create();

    $project->update(['status' => ProjectStatus::Stopped]);

    expect($project->fresh()->status)->toBe(ProjectStatus::Stopped);
});

it('can transition from Running to Error', function () {
    $project = Project::factory()->running()->create();

    $project->update(['status' => ProjectStatus::Error]);

    expect($project->fresh()->status)->toBe(ProjectStatus::Error);
});

it('can transition from Error to Running', function () {
    $project = Project::factory()->create(['status' => ProjectStatus::Error]);

    $project->update(['status' => ProjectStatus::Running]);

    expect($project->fresh()->status)->toBe(ProjectStatus::Running);
});

// ============================================================================
// Relationships
// ============================================================================

it('has many logs', function () {
    $project = Project::factory()->create();
    ProjectLog::factory()->count(3)->for($project)->create();

    expect($project->logs)->toHaveCount(3)
        ->and($project->logs->first())->toBeInstanceOf(ProjectLog::class);
});

it('can access logs relationship after refresh', function () {
    $project = Project::factory()->create();
    ProjectLog::factory()->for($project)->create();

    $project->refresh();

    expect($project->logs->first())->toBeInstanceOf(ProjectLog::class);
});

it('deleting project cascades to its logs', function () {
    $project = Project::factory()->create();
    $log = ProjectLog::factory()->for($project)->create();
    $logId = $log->id;

    $project->delete();

    expect(ProjectLog::find($logId))->toBeNull();
});

// ============================================================================
// Query Scopes
// ============================================================================

it('scopeRunning returns only running projects', function () {
    Project::factory()->running()->create(['name' => 'Running Project']);
    Project::factory()->stopped()->create(['name' => 'Stopped Project']);
    Project::factory()->create(['name' => 'Created Project', 'status' => ProjectStatus::Created]);

    $running = Project::running()->get();

    expect($running)->toHaveCount(1)
        ->and($running->first()->name)->toBe('Running Project');
});

it('scopeStopped returns only stopped projects', function () {
    Project::factory()->running()->create(['name' => 'Running Project']);
    Project::factory()->stopped()->create(['name' => 'Stopped Project']);
    Project::factory()->create(['name' => 'Created Project', 'status' => ProjectStatus::Created]);

    $stopped = Project::stopped()->get();

    expect($stopped)->toHaveCount(1)
        ->and($stopped->first()->name)->toBe('Stopped Project');
});

it('scopeRunning returns empty when no running projects', function () {
    Project::factory()->stopped()->create();
    Project::factory()->create(['status' => ProjectStatus::Created]);

    $running = Project::running()->get();

    expect($running)->toBeEmpty();
});

it('scopeStopped returns empty when no stopped projects', function () {
    Project::factory()->running()->create();
    Project::factory()->create(['status' => ProjectStatus::Created]);

    $stopped = Project::stopped()->get();

    expect($stopped)->toBeEmpty();
});

// ============================================================================
// Business Logic Methods - URL Generation
// ============================================================================

it('returns public url when tunnel is enabled and subdomain path is set', function () {
    TunnelConfig::factory()->create(['subdomain' => 'test-subdomain']);
    $project = Project::factory()->create([
        'tunnel_enabled' => true,
        'tunnel_subdomain_path' => 'my-project',
    ]);

    $url = $project->getPublicUrl();

    expect($url)->toBe('https://test-subdomain.'.config('vibecodepc.cloud_domain').'/my-project');
});

it('returns null for public url when tunnel is disabled', function () {
    TunnelConfig::factory()->create(['subdomain' => 'test-subdomain']);
    $project = Project::factory()->create([
        'tunnel_enabled' => false,
        'tunnel_subdomain_path' => 'my-project',
    ]);

    $url = $project->getPublicUrl();

    expect($url)->toBeNull();
});

it('returns null for public url when tunnel subdomain path is null', function () {
    TunnelConfig::factory()->create(['subdomain' => 'test-subdomain']);
    $project = Project::factory()->create([
        'tunnel_enabled' => true,
        'tunnel_subdomain_path' => null,
    ]);

    $url = $project->getPublicUrl();

    expect($url)->toBeNull();
});

it('returns null for public url when tunnel config does not exist', function () {
    $project = Project::factory()->create([
        'tunnel_enabled' => true,
        'tunnel_subdomain_path' => 'my-project',
    ]);

    $url = $project->getPublicUrl();

    expect($url)->toBeNull();
});

it('returns subdomain url when tunnel is enabled', function () {
    TunnelConfig::factory()->create(['subdomain' => 'test-subdomain']);
    $project = Project::factory()->create([
        'name' => 'My Project',
        'slug' => 'my-project',
        'tunnel_enabled' => true,
    ]);

    $url = $project->getSubdomainUrl();

    expect($url)->toBe('https://my-project--test-subdomain.'.config('vibecodepc.cloud_domain'));
});

it('returns null for subdomain url when tunnel is disabled', function () {
    TunnelConfig::factory()->create(['subdomain' => 'test-subdomain']);
    $project = Project::factory()->create([
        'slug' => 'my-project',
        'tunnel_enabled' => false,
    ]);

    $url = $project->getSubdomainUrl();

    expect($url)->toBeNull();
});

it('returns null for subdomain url when tunnel config does not exist', function () {
    $project = Project::factory()->create([
        'slug' => 'my-project',
        'tunnel_enabled' => true,
    ]);

    $url = $project->getSubdomainUrl();

    expect($url)->toBeNull();
});

// ============================================================================
// Casts
// ============================================================================

it('casts framework to ProjectFramework enum', function () {
    $project = Project::factory()->create(['framework' => ProjectFramework::Laravel]);

    expect($project->framework)->toBe(ProjectFramework::Laravel)
        ->and($project->framework)->toBeInstanceOf(ProjectFramework::class);
});

it('casts status to ProjectStatus enum', function () {
    $project = Project::factory()->create(['status' => ProjectStatus::Running]);

    expect($project->status)->toBe(ProjectStatus::Running)
        ->and($project->status)->toBeInstanceOf(ProjectStatus::class);
});

it('casts tunnel_enabled to boolean', function () {
    $project = Project::factory()->create(['tunnel_enabled' => true]);

    expect($project->tunnel_enabled)->toBeBool()
        ->and($project->tunnel_enabled)->toBeTrue();
});

it('casts tunnel_enabled false to boolean', function () {
    $project = Project::factory()->create(['tunnel_enabled' => false]);

    expect($project->tunnel_enabled)->toBeBool()
        ->and($project->tunnel_enabled)->toBeFalse();
});

it('casts last_started_at to datetime', function () {
    $now = now();
    $project = Project::factory()->create(['last_started_at' => $now]);

    expect($project->last_started_at)->toBeInstanceOf(DateTime::class);
});

it('casts last_stopped_at to datetime', function () {
    $now = now();
    $project = Project::factory()->create(['last_stopped_at' => $now]);

    expect($project->last_stopped_at)->toBeInstanceOf(DateTime::class);
});

it('casts env_vars to encrypted array', function () {
    $envVars = ['KEY1' => 'value1', 'KEY2' => 'value2'];
    $project = Project::factory()->create(['env_vars' => $envVars]);

    expect($project->env_vars)->toBeArray()
        ->and($project->env_vars['KEY1'])->toBe('value1')
        ->and($project->env_vars['KEY2'])->toBe('value2');
});

it('casts null env_vars correctly', function () {
    $project = Project::factory()->create(['env_vars' => null]);

    expect($project->env_vars)->toBeNull();
});

it('casts empty array env_vars correctly', function () {
    $project = Project::factory()->create(['env_vars' => []]);

    expect($project->env_vars)->toBeArray()->toBeEmpty();
});

// ============================================================================
// Factory States
// ============================================================================

it('factory running state creates project with running status', function () {
    $project = Project::factory()->running()->create();

    expect($project->status)->toBe(ProjectStatus::Running)
        ->and($project->container_id)->not->toBeNull()
        ->and($project->last_started_at)->toBeInstanceOf(DateTime::class);
});

it('factory stopped state creates project with stopped status', function () {
    $project = Project::factory()->stopped()->create();

    expect($project->status)->toBe(ProjectStatus::Stopped)
        ->and($project->last_stopped_at)->toBeInstanceOf(DateTime::class);
});

it('factory cloned state creates project with clone url', function () {
    $project = Project::factory()->cloned()->create();

    expect($project->clone_url)->toBeString()
        ->and(strpos($project->clone_url, 'github.com'))->toBeInt();
});

it('factory forFramework state sets framework and default port', function () {
    $project = Project::factory()->forFramework(ProjectFramework::Laravel)->create();

    expect($project->framework)->toBe(ProjectFramework::Laravel)
        ->and($project->port)->toBe(ProjectFramework::Laravel->defaultPort());
});

// ============================================================================
// Business Logic Edge Cases
// ============================================================================

it('handles all ProjectStatus values in isProvisioning', function () {
    $allStatuses = ProjectStatus::cases();
    $provisioningStatuses = [ProjectStatus::Scaffolding, ProjectStatus::Cloning];

    foreach ($allStatuses as $status) {
        $project = Project::factory()->create(['status' => $status]);
        $expected = in_array($status, $provisioningStatuses, true);

        expect($project->isProvisioning())->toBe($expected,
            "Status {$status->name} should ".($expected ? '' : 'not ').'be provisioning');
    }
});

it('factory creates unique slugs for each project', function () {
    $project1 = Project::factory()->create(['name' => 'My Project']);
    $project2 = Project::factory()->create(['name' => 'My Project']);

    expect($project1->slug)->not->toBe($project2->slug);
});

it('project can be updated with new attributes', function () {
    $project = Project::factory()->create(['name' => 'Original Name']);

    $project->update([
        'name' => 'Updated Name',
        'status' => ProjectStatus::Running,
        'port' => 8080,
    ]);

    $fresh = $project->fresh();
    expect($fresh->name)->toBe('Updated Name')
        ->and($fresh->status)->toBe(ProjectStatus::Running)
        ->and($fresh->port)->toBe(8080);
});

it('project attributes are fillable', function () {
    $project = new Project([
        'name' => 'Test Project',
        'slug' => 'test-project',
        'framework' => ProjectFramework::Laravel,
        'status' => ProjectStatus::Created,
        'path' => '/tmp/test-project',
        'port' => 3000,
        'tunnel_enabled' => false,
    ]);

    expect($project->name)->toBe('Test Project')
        ->and($project->slug)->toBe('test-project')
        ->and($project->framework)->toBe(ProjectFramework::Laravel)
        ->and($project->status)->toBe(ProjectStatus::Created);
});

it('project can have multiple statuses throughout lifecycle', function () {
    $project = Project::factory()->create(['status' => ProjectStatus::Created]);

    $statuses = [
        ProjectStatus::Scaffolding,
        ProjectStatus::Cloning,
        ProjectStatus::Running,
        ProjectStatus::Stopped,
        ProjectStatus::Running,
        ProjectStatus::Error,
    ];

    foreach ($statuses as $status) {
        $project->update(['status' => $status]);
        expect($project->fresh()->status)->toBe($status);
    }
});
