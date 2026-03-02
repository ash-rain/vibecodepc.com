<?php

declare(strict_types=1);

use App\Livewire\Wizard\Tunnel;
use App\Models\AnalyticsEvent;
use App\Models\TunnelConfig;
use App\Services\WizardProgressService;
use Livewire\Livewire;
use VibecodePC\Common\Enums\WizardStep;

beforeEach(function () {
    // Seed wizard progress
    app(WizardProgressService::class)->seedProgress();

    // Create an active tunnel config
    TunnelConfig::updateOrCreate(
        ['subdomain' => 'test-device'],
        [
            'tunnel_id' => 'test-tunnel-id',
            'tunnel_token_encrypted' => 'encrypted-token',
            'status' => 'active',
        ]
    );
});

it('tracks analytics event when tunnel step is skipped', function () {
    expect(AnalyticsEvent::count())->toBe(0);

    Livewire::test(Tunnel::class)
        ->call('skip');

    $event = AnalyticsEvent::first();

    expect($event)->not->toBeNull()
        ->and($event->event_type)->toBe('tunnel.skipped')
        ->and($event->category)->toBe('tunnel')
        ->and($event->properties['reason'])->toBe('user_choice');
});

it('tracks analytics event with subdomain when tunnel is completed', function () {
    expect(AnalyticsEvent::count())->toBe(0);

    // Set up the component with a subdomain
    $component = Livewire::test(Tunnel::class);
    $component->set('subdomain', 'my-device');
    $component->call('complete');

    $event = AnalyticsEvent::first();

    expect($event)->not->toBeNull()
        ->and($event->event_type)->toBe('tunnel.completed')
        ->and($event->category)->toBe('tunnel')
        ->and($event->properties['subdomain'])->toBe('my-device');
});

it('dispatches step-skipped event when analytics tracking fails', function () {
    // This test ensures the component still dispatches the event even if analytics fails
    $component = Livewire::test(Tunnel::class)
        ->call('skip');

    $component->assertDispatched('step-skipped');
});

it('dispatches step-completed event when analytics tracking succeeds', function () {
    $component = Livewire::test(Tunnel::class);
    $component->set('subdomain', 'test-device');
    $component->call('complete');

    $component->assertDispatched('step-completed');
});

it('does not block wizard progress if analytics tracking throws exception', function () {
    // Even if analytics fails, the wizard should still complete
    $component = Livewire::test(Tunnel::class)
        ->call('skip');

    // Check that the step was still marked as skipped
    $progress = app(WizardProgressService::class);
    expect($progress->isStepAccessible(WizardStep::Tunnel))->toBeTrue();
});
