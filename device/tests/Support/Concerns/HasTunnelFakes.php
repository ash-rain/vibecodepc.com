<?php

declare(strict_types=1);

namespace Tests\Support\Concerns;

use App\Services\CloudApiClient;
use App\Services\Tunnel\QuickTunnelService;
use App\Services\Tunnel\TunnelService;
use Illuminate\Support\Facades\Http;
use Mockery;
use Tests\Support\Fakes\CloudApiClientFake;

trait HasTunnelFakes
{
    /**
     * The fake CloudApiClient instance.
     */
    protected CloudApiClientFake $cloudApiFake;

    /**
     * The mocked TunnelService instance.
     */
    protected $tunnelMock;

    /**
     * The mocked QuickTunnelService instance.
     */
    protected $quickTunnelMock;

    /**
     * Set up the tunnel fakes before each test.
     */
    protected function setUpTunnelFakes(): void
    {
        // Create and bind the CloudApiClient fake with predictable responses
        $this->cloudApiFake = new CloudApiClientFake;
        $this->app->instance(CloudApiClient::class, $this->cloudApiFake);

        // Create and bind the TunnelService mock with sensible defaults
        $this->tunnelMock = Mockery::mock(TunnelService::class);
        $this->tunnelMock
            ->shouldReceive('getStatus')
            ->andReturn([
                'installed' => true,
                'running' => true,
                'configured' => true,
            ])
            ->byDefault();
        $this->tunnelMock->shouldReceive('isRunning')->andReturn(true)->byDefault();
        $this->tunnelMock->shouldReceive('hasCredentials')->andReturn(true)->byDefault();
        $this->tunnelMock->shouldReceive('isSkipped')->andReturn(false)->byDefault();
        $this->tunnelMock->shouldReceive('wasSkippedButNowAvailable')->andReturn(false)->byDefault();
        $this->tunnelMock->shouldReceive('stop')->andReturn(null)->byDefault();
        $this->tunnelMock->shouldReceive('start')->andReturn(null)->byDefault();
        $this->tunnelMock->shouldReceive('cleanup')->byDefault();
        $this->app->instance(TunnelService::class, $this->tunnelMock);

        // Create and bind the QuickTunnelService mock
        $this->quickTunnelMock = Mockery::mock(QuickTunnelService::class);
        $this->quickTunnelMock->shouldReceive('start')->byDefault();
        $this->quickTunnelMock->shouldReceive('stop')->byDefault();
        $this->quickTunnelMock->shouldReceive('isHealthy')->andReturn(true)->byDefault();
        $this->quickTunnelMock->shouldReceive('cleanup')->byDefault();
        $this->quickTunnelMock->shouldReceive('refreshUrl')->byDefault();
        $this->app->instance(QuickTunnelService::class, $this->quickTunnelMock);

        // Set up Http fake for quick-tunnel endpoint
        $cloudUrl = config('vibecodepc.cloud_url', 'https://vibecodepc.com');
        Http::fake([
            // Fake the cloud API tunnel registration endpoint
            "{$cloudUrl}/api/devices/*/tunnel/register" => Http::response([
                'success' => true,
                'subdomain' => 'test-subdomain-abc123',
                'tunnel_id' => 'test-tunnel-999',
                'token' => 'fake-token-for-testing',
            ], 200),
            // Also fake the trycloudflare.com URLs for QuickTunnelService
            '*.trycloudflare.com/*' => Http::response([
                'success' => true,
                'subdomain' => 'test-subdomain-abc123',
                'tunnel_id' => 'test-tunnel-999',
                'token' => 'fake-token-for-testing',
            ], 200),
        ]);
    }

    /**
     * Configure the tunnel mock for a "not configured" state.
     */
    protected function configureUnconfiguredState(): void
    {
        $this->tunnelMock->shouldReceive('getStatus')->andReturn([
            'installed' => true,
            'running' => false,
            'configured' => false,
        ])->byDefault();
        $this->tunnelMock->shouldReceive('hasCredentials')->andReturn(false)->byDefault();
    }

    /**
     * Configure the tunnel mock for a "not running" state.
     */
    protected function configureNotRunningState(): void
    {
        $this->tunnelMock->shouldReceive('isRunning')->andReturn(false)->byDefault();
    }

    /**
     * Configure the tunnel mock for a "skipped" state.
     */
    protected function configureSkippedState(): void
    {
        $this->tunnelMock->shouldReceive('getStatus')->andReturn([
            'installed' => true,
            'running' => false,
            'configured' => true,
        ])->byDefault();
        $this->tunnelMock->shouldReceive('isSkipped')->andReturn(true)->byDefault();
    }

    /**
     * Configure the CloudApiClient fake to make subdomains available.
     *
     * @param  array<string>  $availableSubdomains
     * @param  array<string>  $unavailableSubdomains
     */
    protected function configureSubdomainAvailability(
        array $availableSubdomains = [],
        array $unavailableSubdomains = []
    ): void {
        $this->cloudApiFake->setResponse('checkSubdomainAvailability', true);
    }

    /**
     * Configure the CloudApiClient fake to fail on next call.
     *
     * @param  string  $method  The method that should fail, or 'all' for any method
     * @param  string  $message  The error message
     */
    protected function configureApiFailure(string $method = 'all', string $message = 'API connection failed'): void
    {
        $this->cloudApiFake->setException(new \Exception($message));
    }

    /**
     * Configure the CloudApiClient fake provision response.
     *
     * @param  string  $tunnelId  The tunnel ID to return (defaults to predictable test-tunnel-999)
     * @param  string  $token  The token to return
     */
    protected function configureProvisionResponse(
        string $tunnelId = 'test-tunnel-999',
        string $token = 'test-token-value'
    ): void {
        $this->cloudApiFake->setResponse('provisionTunnel', [
            'tunnel_id' => $tunnelId,
            'tunnel_token' => $token,
        ]);
    }

    /**
     * Assert that the CloudApiClient method was called.
     */
    protected function assertCloudApiCalled(string $method, ?int $times = null): void
    {
        $wasCalled = $this->cloudApiFake->wasCalled($method);

        if ($times === null) {
            expect($wasCalled)->toBeTrue("Expected CloudApiClient::{$method} to be called");
        } else {
            $callCount = count($this->cloudApiFake->getCallsForMethod($method));
            expect($callCount)->toBe($times, "Expected CloudApiClient::{$method} to be called {$times} times, but was called {$callCount} times");
        }
    }

    /**
     * Assert that the CloudApiClient method was NOT called.
     */
    protected function assertCloudApiNotCalled(string $method): void
    {
        expect($this->cloudApiFake->wasCalled($method))->toBeFalse("Expected CloudApiClient::{$method} NOT to be called");
    }

    /**
     * Configure Http fake for quick-tunnel API responses.
     *
     * @param  array<string, mixed>|null  $responseData
     */
    protected function configureQuickTunnelHttpFake(?array $responseData = null, int $statusCode = 200): void
    {
        $responseData ??= [
            'success' => true,
            'subdomain' => 'test-subdomain-abc123',
            'tunnel_id' => 'test-tunnel-999',
            'token' => 'fake-token-for-testing',
        ];

        Http::fake([
            '*trycloudflare.com*' => Http::response($responseData, $statusCode),
        ]);
    }

    /**
     * Configure Http fake for connection error (status 0).
     */
    protected function configureHttpConnectionError(): void
    {
        Http::fake([
            '*' => Http::response(null, 0),
        ]);
    }

    /**
     * Configure Http fake for server error (500).
     */
    protected function configureHttpServerError(): void
    {
        Http::fake([
            '*' => Http::response(['error' => 'Internal Server Error'], 500),
        ]);
    }

    /**
     * Configure Http fake for timeout.
     */
    protected function configureHttpTimeout(): void
    {
        Http::fake([
            '*' => function () {
                throw new \Illuminate\Http\Client\ConnectionException('Request timed out');
            },
        ]);
    }
}
