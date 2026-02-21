<?php

declare(strict_types=1);

use App\Livewire\Dashboard\Overview;
use App\Models\CloudCredential;
use Illuminate\Support\Facades\Process;
use Livewire\Livewire;

beforeEach(function () {
    Process::fake();
    CloudCredential::create([
        'pairing_token_encrypted' => 'test-token',
        'cloud_username' => 'testuser',
        'cloud_email' => 'test@example.com',
        'cloud_url' => 'https://vibecodepc.com',
        'is_paired' => true,
        'paired_at' => now(),
    ]);
});

it('renders the overview dashboard', function () {
    Livewire::test(Overview::class)
        ->assertStatus(200)
        ->assertSee('Welcome back, testuser');
});

it('shows quick stats', function () {
    Livewire::test(Overview::class)
        ->assertSee('Projects')
        ->assertSee('Running')
        ->assertSee('Tunnel')
        ->assertSee('AI Providers');
});

it('shows quick action buttons', function () {
    Livewire::test(Overview::class)
        ->assertSee('New Project')
        ->assertSee('Open Editor')
        ->assertSee('Manage AI Keys');
});
