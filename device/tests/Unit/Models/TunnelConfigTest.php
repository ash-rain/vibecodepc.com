<?php

declare(strict_types=1);

use App\Models\TunnelConfig;
use Illuminate\Support\Facades\DB;

it('current returns the latest tunnel config', function () {
    TunnelConfig::factory()->create([
        'created_at' => now()->subDays(2),
    ]);

    $latest = TunnelConfig::factory()->create([
        'created_at' => now(),
    ]);

    TunnelConfig::factory()->create([
        'created_at' => now()->subDay(),
    ]);

    $result = TunnelConfig::current();

    expect($result->id)->toBe($latest->id);
});

it('current returns null when no tunnel configs exist', function () {
    expect(TunnelConfig::current())->toBeNull();
});

it('current returns most recent even when only one config exists', function () {
    $config = TunnelConfig::factory()->create();

    $result = TunnelConfig::current();

    expect($result->id)->toBe($config->id);
});

it('current scope orders by created_at descending', function () {
    $oldest = TunnelConfig::factory()->create(['created_at' => now()->subDays(3)]);
    $middle = TunnelConfig::factory()->create(['created_at' => now()->subDays(1)]);
    $newest = TunnelConfig::factory()->create(['created_at' => now()]);

    $result = TunnelConfig::current();

    expect($result->id)->toBe($newest->id);
});

it('tunnel_token_encrypted is encrypted in database', function () {
    $rawToken = 'test_tunnel_token_12345';
    $config = TunnelConfig::factory()->create([
        'tunnel_token_encrypted' => $rawToken,
    ]);

    $databaseValue = DB::table('tunnel_configs')->where('id', $config->id)->value('tunnel_token_encrypted');

    expect($databaseValue)->not->toBe($rawToken)
        ->and($config->tunnel_token_encrypted)->toBe($rawToken);
});

it('encrypts and decrypts tunnel token correctly', function () {
    $token = 'my-secret-tunnel-token';
    $config = TunnelConfig::factory()->create([
        'tunnel_token_encrypted' => $token,
    ]);

    // Retrieve fresh from database
    $freshConfig = TunnelConfig::find($config->id);

    expect($freshConfig->tunnel_token_encrypted)->toBe($token);
});

it('handles null tunnel_token_encrypted', function () {
    $config = TunnelConfig::factory()->create([
        'tunnel_token_encrypted' => null,
    ]);

    expect($config->tunnel_token_encrypted)->toBeNull();
});

it('handles empty string tunnel_token_encrypted', function () {
    $config = TunnelConfig::factory()->create([
        'tunnel_token_encrypted' => '',
    ]);

    expect($config->tunnel_token_encrypted)->toBe('');
});

it('isSkipped returns true when skipped_at is set', function () {
    $config = TunnelConfig::factory()->create([
        'skipped_at' => now(),
    ]);

    expect($config->isSkipped())->toBeTrue();
});

it('isSkipped returns true when status is skipped', function () {
    $config = TunnelConfig::factory()->create([
        'status' => 'skipped',
        'skipped_at' => null,
    ]);

    expect($config->isSkipped())->toBeTrue();
});

it('isSkipped returns false when not skipped', function () {
    $config = TunnelConfig::factory()->create([
        'status' => 'active',
        'skipped_at' => null,
    ]);

    expect($config->isSkipped())->toBeFalse();
});

it('markAsAvailable updates status to available and clears skipped_at', function () {
    $config = TunnelConfig::factory()->create([
        'status' => 'skipped',
        'skipped_at' => now(),
    ]);

    $config->markAsAvailable();

    expect($config->fresh()->status)->toBe('available')
        ->and($config->fresh()->skipped_at)->toBeNull();
});

it('isAvailableAfterSkip returns true when status is available and verified_at is null', function () {
    $config = TunnelConfig::factory()->create([
        'status' => 'available',
        'verified_at' => null,
    ]);

    expect($config->isAvailableAfterSkip())->toBeTrue();
});

it('isAvailableAfterSkip returns false when status is not available', function () {
    $config = TunnelConfig::factory()->create([
        'status' => 'active',
        'verified_at' => null,
    ]);

    expect($config->isAvailableAfterSkip())->toBeFalse();
});

it('isAvailableAfterSkip returns false when verified_at is set', function () {
    $config = TunnelConfig::factory()->create([
        'status' => 'available',
        'verified_at' => now(),
    ]);

    expect($config->isAvailableAfterSkip())->toBeFalse();
});

it('verified_at is cast to datetime', function () {
    $config = TunnelConfig::factory()->create([
        'verified_at' => now(),
    ]);

    expect($config->verified_at)->toBeInstanceOf(DateTime::class);
});

it('skipped_at is cast to datetime', function () {
    $config = TunnelConfig::factory()->create([
        'skipped_at' => now(),
    ]);

    expect($config->skipped_at)->toBeInstanceOf(DateTime::class);
});

it('factory creates config with default values', function () {
    $config = TunnelConfig::factory()->create();

    expect($config->subdomain)->toBeString()
        ->and($config->tunnel_token_encrypted)->toBeNull()
        ->and($config->tunnel_id)->toBeNull()
        ->and($config->status)->toBe('pending')
        ->and($config->verified_at)->toBeNull()
        ->and($config->skipped_at)->toBeNull();
});

it('factory verified state creates active config with token', function () {
    $config = TunnelConfig::factory()->verified()->create();

    expect($config->status)->toBe('active')
        ->and($config->tunnel_id)->toBeString()
        ->and($config->tunnel_token_encrypted)->toBeString()
        ->and($config->verified_at)->toBeInstanceOf(DateTime::class)
        ->and($config->skipped_at)->toBeNull();
});

it('factory skipped state creates skipped config', function () {
    $config = TunnelConfig::factory()->skipped()->create();

    expect($config->status)->toBe('skipped')
        ->and($config->tunnel_token_encrypted)->toBeNull()
        ->and($config->tunnel_id)->toBeNull()
        ->and($config->verified_at)->toBeNull()
        ->and($config->skipped_at)->toBeInstanceOf(DateTime::class);
});

it('factory active state creates active config', function () {
    $config = TunnelConfig::factory()->active()->create();

    expect($config->status)->toBe('active')
        ->and($config->tunnel_token_encrypted)->toBeString()
        ->and($config->tunnel_id)->toBeString()
        ->and($config->skipped_at)->toBeNull();
});

it('factory available state creates available config', function () {
    $config = TunnelConfig::factory()->available()->create();

    expect($config->status)->toBe('available')
        ->and($config->tunnel_token_encrypted)->toBeNull()
        ->and($config->tunnel_id)->toBeNull()
        ->and($config->verified_at)->toBeNull()
        ->and($config->skipped_at)->toBeNull();
});

it('can query configs by status', function () {
    TunnelConfig::factory()->count(2)->create(['status' => 'active']);
    TunnelConfig::factory()->count(3)->create(['status' => 'pending']);
    TunnelConfig::factory()->count(1)->create(['status' => 'skipped']);

    expect(TunnelConfig::where('status', 'active')->count())->toBe(2)
        ->and(TunnelConfig::where('status', 'pending')->count())->toBe(3)
        ->and(TunnelConfig::where('status', 'skipped')->count())->toBe(1);
});

it('handles all fillable attributes', function () {
    $config = TunnelConfig::factory()->create([
        'subdomain' => 'test-subdomain',
        'tunnel_token_encrypted' => 'test-token',
        'tunnel_id' => 'test-tunnel-id',
        'status' => 'active',
        'verified_at' => now(),
        'skipped_at' => now(),
    ]);

    expect($config->subdomain)->toBe('test-subdomain')
        ->and($config->tunnel_token_encrypted)->toBe('test-token')
        ->and($config->tunnel_id)->toBe('test-tunnel-id')
        ->and($config->status)->toBe('active')
        ->and($config->verified_at)->toBeInstanceOf(DateTime::class)
        ->and($config->skipped_at)->toBeInstanceOf(DateTime::class);
});

it('markAsAvailable works after refresh', function () {
    $config = TunnelConfig::factory()->create([
        'status' => 'skipped',
        'skipped_at' => now(),
    ]);

    $config->refresh();
    $config->markAsAvailable();

    expect($config->fresh()->status)->toBe('available')
        ->and($config->fresh()->skipped_at)->toBeNull();
});

it('isSkipped works after model refresh', function () {
    $config = TunnelConfig::factory()->create([
        'status' => 'active',
        'skipped_at' => null,
    ]);

    expect($config->isSkipped())->toBeFalse();

    $config->update(['status' => 'skipped', 'skipped_at' => now()]);

    expect($config->fresh()->isSkipped())->toBeTrue();
});
