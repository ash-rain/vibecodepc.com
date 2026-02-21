<?php

declare(strict_types=1);

use App\Livewire\Dashboard\ProjectCreate;
use App\Models\Project;
use Illuminate\Support\Facades\Process;
use Livewire\Livewire;

beforeEach(function () {
    Process::fake();
});

it('renders the create project form', function () {
    Livewire::test(ProjectCreate::class)
        ->assertStatus(200)
        ->assertSee('Create New Project');
});

it('shows framework options', function () {
    Livewire::test(ProjectCreate::class)
        ->assertSee('Laravel')
        ->assertSee('Next.js')
        ->assertSee('Astro')
        ->assertSee('FastAPI')
        ->assertSee('Static HTML')
        ->assertSee('Custom');
});

it('validates required fields before proceeding', function () {
    Livewire::test(ProjectCreate::class)
        ->call('nextStep')
        ->assertHasErrors(['name', 'framework']);
});

it('advances to step 2 with valid input', function () {
    Livewire::test(ProjectCreate::class)
        ->set('name', 'Test Project')
        ->set('framework', 'laravel')
        ->call('nextStep')
        ->assertSet('step', 2);
});

it('prevents duplicate project names', function () {
    Project::factory()->create(['name' => 'Existing']);

    Livewire::test(ProjectCreate::class)
        ->set('name', 'Existing')
        ->set('framework', 'laravel')
        ->call('nextStep')
        ->assertHasErrors('name');
});

it('can go back to step 1', function () {
    Livewire::test(ProjectCreate::class)
        ->set('name', 'Test')
        ->set('framework', 'laravel')
        ->call('nextStep')
        ->assertSet('step', 2)
        ->call('back')
        ->assertSet('step', 1);
});
