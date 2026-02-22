<?php

use App\Models\CloudCredential;
use App\Services\CloudApiClient;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

it('sends heartbeat with correct payload and endpoint', function () {
    $uuid = (string) Str::uuid();
    $cloudUrl = 'https://vibecodepc.test';

    CloudCredential::create([
        'pairing_token_encrypted' => 'test-token-123',
        'cloud_username' => 'testuser',
        'cloud_email' => 'test@example.com',
        'cloud_url' => $cloudUrl,
        'is_paired' => true,
        'paired_at' => now(),
    ]);

    Http::fake([
        "{$cloudUrl}/api/devices/{$uuid}/heartbeat" => Http::response([], 200),
    ]);

    $client = new CloudApiClient($cloudUrl);
    $client->sendHeartbeat($uuid, [
        'cpu_percent' => 25.5,
        'temperature_c' => 42.3,
        'ram_used_mb' => 1024,
        'ram_total_mb' => 8192,
        'disk_used_gb' => 12.5,
        'disk_total_gb' => 64.0,
        'running_projects' => 2,
        'tunnel_active' => true,
        'firmware_version' => '1.0.0',
    ]);

    Http::assertSent(function ($request) use ($cloudUrl, $uuid) {
        return $request->url() === "{$cloudUrl}/api/devices/{$uuid}/heartbeat"
            && $request->method() === 'POST'
            && $request['cpu_percent'] === 25.5
            && $request['cpu_temp'] === 42.3
            && $request['ram_used_mb'] === 1024
            && $request['ram_total_mb'] === 8192
            && $request['disk_used_gb'] === 12.5
            && $request['disk_total_gb'] === 64.0
            && $request['running_projects'] === 2
            && $request['tunnel_active'] === true
            && $request['firmware_version'] === '1.0.0';
    });
});

it('gracefully handles HTTP failure without throwing', function () {
    $uuid = (string) Str::uuid();
    $cloudUrl = 'https://vibecodepc.test';

    CloudCredential::create([
        'pairing_token_encrypted' => 'test-token-123',
        'cloud_username' => 'testuser',
        'cloud_email' => 'test@example.com',
        'cloud_url' => $cloudUrl,
        'is_paired' => true,
        'paired_at' => now(),
    ]);

    Http::fake([
        "{$cloudUrl}/api/devices/{$uuid}/heartbeat" => Http::response('Server Error', 500),
    ]);

    Log::shouldReceive('warning')
        ->once()
        ->withArgs(fn (string $message) => str_contains($message, 'Heartbeat failed'));

    $client = new CloudApiClient($cloudUrl);
    $client->sendHeartbeat($uuid, [
        'cpu_percent' => 10.0,
        'temperature_c' => null,
        'ram_used_mb' => 512,
        'ram_total_mb' => 8192,
        'disk_used_gb' => 5.0,
        'disk_total_gb' => 64.0,
        'running_projects' => 0,
        'tunnel_active' => false,
        'firmware_version' => '1.0.0',
    ]);

    expect(true)->toBeTrue();
});

it('sends heartbeat with bearer token from cloud credential', function () {
    $uuid = (string) Str::uuid();
    $cloudUrl = 'https://vibecodepc.test';

    CloudCredential::create([
        'pairing_token_encrypted' => 'my-secret-token',
        'cloud_username' => 'testuser',
        'cloud_email' => 'test@example.com',
        'cloud_url' => $cloudUrl,
        'is_paired' => true,
        'paired_at' => now(),
    ]);

    Http::fake([
        "{$cloudUrl}/api/devices/{$uuid}/heartbeat" => Http::response([], 200),
    ]);

    $client = new CloudApiClient($cloudUrl);
    $client->sendHeartbeat($uuid, [
        'cpu_percent' => 10.0,
        'temperature_c' => 40.0,
        'ram_used_mb' => 512,
        'ram_total_mb' => 8192,
        'disk_used_gb' => 5.0,
        'disk_total_gb' => 64.0,
        'running_projects' => 1,
        'tunnel_active' => false,
        'firmware_version' => '1.0.0',
    ]);

    Http::assertSent(function ($request) {
        return $request->hasHeader('Authorization')
            && str_contains($request->header('Authorization')[0], 'Bearer');
    });
});
