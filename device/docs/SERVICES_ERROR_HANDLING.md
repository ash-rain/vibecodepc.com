# Error Handling Patterns and Retry Strategies in Services

This document describes the error handling patterns, retry strategies, and resilience mechanisms used throughout the VibeCodePC Services layer.

## Table of Contents

- [Overview](#overview)
- [Error Handling Patterns](#error-handling-patterns)
  - [Exception-Based Error Handling](#exception-based-error-handling)
  - [Null/Optional Error Handling](#nulloptional-error-handling)
  - [Error Message String Returns](#error-message-string-returns)
  - [Silent Failure with Logging](#silent-failure-with-logging)
  - [Result Object Pattern](#result-object-pattern)
- [Retry Strategies](#retry-strategies)
  - [RetryableTrait](#retryabletrait)
  - [Exponential Backoff](#exponential-backoff)
  - [Jitter](#jitter)
  - [Retryable Status Codes](#retryable-status-codes)
- [Circuit Breaker Pattern](#circuit-breaker-pattern)
  - [States](#states)
  - [Configuration](#configuration)
  - [Usage](#usage)
- [Service-Specific Patterns](#service-specific-patterns)
  - [CloudApiClient](#cloudapiclient)
  - [BackupService](#backupservice)
  - [TunnelService](#tunnelservice)
  - [CodeServerService](#codeserverservice)
  - [ProjectContainerService](#projectcontainerservice)
  - [AnalyticsService](#analyticsservice)
  - [DeviceHealthService](#devicehealthservice)
- [Best Practices](#best-practices)
- [Testing Error Handling](#testing-error-handling)

## Overview

The VibeCodePC Services layer employs multiple error handling strategies depending on the context:

1. **External API calls** (CloudApiClient) use retry logic with circuit breakers
2. **File system operations** (BackupService, TunnelService) validate preconditions and throw exceptions
3. **Process execution** (CodeServerService, ProjectContainerService) return error messages as strings
4. **Non-critical operations** (AnalyticsService) fail silently with logging
5. **Health checks** (DeviceHealthService) return safe defaults on failure

## Error Handling Patterns

### Exception-Based Error Handling

Services throw exceptions for unrecoverable errors that callers must handle.

**When to use:**
- Invalid preconditions (missing files, insufficient permissions)
- Data integrity violations
- Configuration errors

**Example from BackupService:**

```php
public function restoreBackup(string $zipPath): void
{
    if (! file_exists($zipPath)) {
        throw new \RuntimeException('Backup file does not exist.');
    }

    if (! is_readable($zipPath)) {
        throw new \RuntimeException('Backup file is not readable.');
    }

    // ... restore logic
}
```

**Best practices:**
- Use specific exception types (\RuntimeException, \InvalidArgumentException)
- Include context in error messages (file paths, expected vs actual values)
- Validate all preconditions before performing operations

### Null/Optional Error Handling

Services return `null` for optional values that may not exist or when an operation is not applicable.

**When to use:**
- Optional configuration values
- Operations skipped due to missing prerequisites
- Cached values that may not exist

**Example from TunnelService:**

```php
public function start(): ?string
{
    if (! $this->hasCredentials()) {
        return 'Tunnel is not configured. Complete the setup wizard to provision tunnel credentials.';
    }

    // Returns null on success, error message on failure
    return null;
}
```

**Best practices:**
- Document return type with `?string` or `?array` type hints
- Use null coalescing operator (`??`) when consuming nullable values
- Consider using `Optional` pattern for complex cases

### Error Message String Returns

Services return error messages as strings to provide detailed failure information without throwing exceptions.

**When to use:**
- User-facing operations where error details are needed
- Multi-step operations where partial failure is expected
- Operations that should not halt execution on failure

**Example from ProjectContainerService:**

```php
public function start(Project $project): ?string
{
    $result = Process::path($project->path)
        ->timeout(120)
        ->run($this->composeCommand($project, 'up -d'));

    if ($result->successful()) {
        // Update project status
        return null; // Success
    }

    $error = trim($result->errorOutput() ?: $result->output());
    $this->log($project, 'docker', "Start failed: {$error}");
    $project->update(['status' => ProjectStatus::Error]);

    return $error ?: 'Failed to start container (no output).';
}
```

**Best practices:**
- Return `null` to indicate success
- Return descriptive error messages on failure
- Log errors for debugging while returning user-friendly messages

### Silent Failure with Logging

Services catch exceptions and log them without propagating to callers.

**When to use:**
- Analytics and telemetry tracking
- Non-critical background operations
- Operations that should never disrupt user experience

**Example from AnalyticsService:**

```php
public function track(string $eventType, array $properties = [], ?string $category = null): void
{
    try {
        AnalyticsEvent::create([
            'event_type' => $eventType,
            'category' => $category,
            'properties' => $properties,
            'occurred_at' => now(),
        ]);
    } catch (\Throwable $e) {
        Log::warning('Failed to track analytics event', [
            'event_type' => $eventType,
            'error' => $e->getMessage(),
        ]);
    }
}
```

**Best practices:**
- Always include context in log messages
- Use appropriate log levels (warning, error, debug)
- Catch specific exception types when possible

### Result Object Pattern

Services return structured result arrays containing success status and output.

**When to use:**
- Command execution results
- Operations with multiple outputs
- Complex success/failure scenarios

**Example from ProjectContainerService:**

```php
/**
 * @return array{success: bool, output: string}
 */
public function execCommand(Project $project, string $command): array
{
    if (! $project->container_id) {
        return ['success' => false, 'output' => 'No running container found.'];
    }

    $result = Process::path($project->path)
        ->timeout(30)
        ->run($this->composeCommand($project, sprintf('exec -T app %s', $command)));

    return [
        'success' => $result->successful(),
        'output' => trim($result->output() ?: $result->errorOutput()),
    ];
}
```

**Best practices:**
- Document array shape with PHPDoc `@return` annotations
- Include both success status and output/error details
- Use consistent key names (`success`, `output`, `error`)

## Retry Strategies

### RetryableTrait

The `RetryableTrait` provides reusable retry logic for HTTP clients and other retryable operations.

**Location:** `app/Services/Traits/RetryableTrait.php`

**Default Configuration:**

```php
protected int $maxRetries = 4;           // Maximum retry attempts
protected int $baseDelayMs = 100;        // Base delay in milliseconds
protected int $maxDelayMs = 5000;        // Maximum delay cap
protected array $retryableStatuses = [    // HTTP status codes to retry
    408,  // Request Timeout
    429,  // Too Many Requests
    500,  // Internal Server Error
    502,  // Bad Gateway
    503,  // Service Unavailable
    504,  // Gateway Timeout
];
```

**Usage Example:**

```php
use App\Services\Traits\RetryableTrait;

class CloudApiClient
{
    use RetryableTrait;

    private function http(): PendingRequest
    {
        $retryConfig = $this->getRetryConfig();

        return Http::baseUrl($this->cloudUrl)
            ->timeout(10)
            ->retry(
                times: $retryConfig['times'],
                sleepMilliseconds: $retryConfig['sleepMilliseconds'],
                when: $retryConfig['when'],
                throw: $retryConfig['throw']
            );
    }
}
```

### Exponential Backoff

The trait implements exponential backoff with configurable parameters:

**Formula:**
```
delay = min(maxDelayMs, baseDelayMs * 2^(attempt - 1))
```

**Example delays:**
- Attempt 1: 100ms
- Attempt 2: 200ms
- Attempt 3: 400ms
- Attempt 4: 800ms
- Attempt 5+: Capped at 5000ms

**Customization:**

```php
$client = new CloudApiClient($url);
$client->setMaxRetries(6)
       ->setBaseDelayMs(200)
       ->setMaxDelayMs(10000);
```

### Jitter

Jitter prevents thundering herd problems by adding random variation to delays:

```php
public function calculateBackoffDelay(int $attempt): int
{
    $exponentialDelay = $this->baseDelayMs * (2 ** ($attempt - 1));
    $cappedDelay = min($exponentialDelay, $this->maxDelayMs);

    // Add random jitter (±20%)
    $jitter = (int) ($cappedDelay * 0.2 * (mt_rand() / mt_getrandmax() * 2 - 1));

    return max(0, $cappedDelay + $jitter);
}
```

**Benefits:**
- Prevents synchronized retries from overwhelming recovering services
- Distributes retry attempts over time
- Reduces collision probability

### Retryable Status Codes

The trait identifies transient failures by HTTP status code:

```php
public function shouldRetry(\Throwable $exception): bool
{
    // Connection errors (network issues, timeouts)
    if ($exception instanceof ConnectionException) {
        return true;
    }

    // Request exceptions with retryable status codes
    if ($exception instanceof RequestException && $exception->response !== null) {
        return in_array($exception->response->status(), $this->retryableStatuses, true);
    }

    return false;
}
```

**Status Code Meanings:**

| Code | Meaning | Retry Reason |
|------|---------|--------------|
| 408 | Request Timeout | Client timeout, safe to retry |
| 429 | Too Many Requests | Rate limited, back off and retry |
| 500 | Internal Server Error | Server error, may be transient |
| 502 | Bad Gateway | Upstream error, may recover |
| 503 | Service Unavailable | Server overloaded, retry after delay |
| 504 | Gateway Timeout | Upstream timeout, may succeed on retry |

## Circuit Breaker Pattern

The Circuit Breaker pattern prevents cascading failures by temporarily rejecting requests when a service is experiencing high failure rates.

**Location:** `app/Services/CircuitBreaker.php`

### States

```
CLOSED  →  OPEN  →  HALF_OPEN  →  CLOSED
(normal)   (fail-fast)  (testing)    (recovered)
```

**CLOSED:** Normal operation, requests pass through.

**OPEN:** Requests fail fast after threshold failures, preventing cascading failures.

**HALF_OPEN:** Limited trial requests sent to check if service has recovered.

### Configuration

```php
public function __construct(
    private readonly string $serviceName,      // Unique identifier
    private readonly int $failureThreshold = 5,   // Failures before opening
    private readonly int $recoveryTimeout = 60,    // Seconds before half-open
    private readonly int $successThreshold = 2,  // Successes to close circuit
)
```

### Usage

```php
$circuitBreaker = new CircuitBreaker('cloud_api');

if (! $circuitBreaker->isClosed()) {
    throw new \RuntimeException('Circuit breaker is OPEN');
}

try {
    $response = $this->makeRequest();
    $circuitBreaker->recordSuccess();
    return $response;
} catch (\Exception $e) {
    $circuitBreaker->recordFailure();
    throw $e;
}
```

**CloudApiClient Integration:**

The `CloudApiClient` combines `RetryableTrait` with a circuit breaker:

```php
private const int FAILURE_THRESHOLD = 5;
private const int CIRCUIT_TIMEOUT_MINUTES = 1;

public function getDeviceStatus(string $deviceId): DeviceStatusResult
{
    $this->checkCircuit();  // Fail fast if circuit is open

    try {
        $response = $this->http()->get("/api/devices/{$deviceId}/status");
        $response->throw();
        $this->recordSuccess();

        return DeviceStatusResult::fromArray($response->json());
    } catch (ConnectionException|RequestException $e) {
        $this->recordFailure();
        throw $e;
    }
}
```

## Service-Specific Patterns

### CloudApiClient

**Patterns:** Retry + Circuit Breaker

**Error Handling:**
- Retries transient HTTP failures up to 4 times
- Opens circuit after 5 consecutive failures
- Records successes/failures in cache
- Silently skips operations when tunnel is not configured

**Key Methods:**

```php
// Throws RuntimeException if circuit is open
public function getDeviceStatus(string $deviceId): DeviceStatusResult;

// Returns null on failure (non-critical operation)
public function fetchTrafficStats(string $deviceId): ?array;

// Silently logs and continues (heartbeat is non-blocking)
public function sendHeartbeat(string $deviceId, array $metrics): void;
```

### BackupService

**Patterns:** Exception-Based Validation

**Error Handling:**
- Validates file existence and readability before operations
- Checks data integrity with SHA-256 checksums
- Uses database transactions for atomic restoration
- Throws descriptive \RuntimeException on failures

**Validation Chain:**

1. File existence check
2. File readability check
3. ZIP archive integrity check
4. Encrypted payload presence check
5. JSON structure validation
6. Checksum verification
7. Database transaction rollback on failure

### TunnelService

**Patterns:** Error Message Returns + Precondition Checks

**Error Handling:**
- Validates disk space before file operations
- Returns descriptive error messages as strings
- Logs detailed errors while returning user-friendly messages
- Handles edge cases (skipped setup, auto-detection)

**Disk Space Check:**

```php
protected function hasSufficientDiskSpace(int $requiredBytes = 1024): bool
{
    $dir = dirname($this->tokenFilePath);
    $freeSpace = disk_free_space($dir);

    return $freeSpace !== false && $freeSpace >= $requiredBytes;
}
```

### CodeServerService

**Patterns:** Silent Fallbacks + Process Execution

**Error Handling:**
- Falls back to alternative commands on failure
- Returns `null` for missing information (version, config)
- Uses timeouts to prevent hanging processes
- Handles both systemd and direct process management

**Port Detection Fallback Chain:**

```php
public function isRunning(): bool
{
    $result = Process::run(sprintf(
        '/usr/sbin/lsof -iTCP:%d ... || ' .
        'lsof -iTCP:%d ... || ' .
        'ss -tlnp ... || ' .
        'curl -sf ...',
        $port, $port, $port, $port
    ));

    return $result->successful();
}
```

### ProjectContainerService

**Patterns:** Result Objects + Error Logging

**Error Handling:**
- Returns error messages as strings (null on success)
- Logs all operations to ProjectLog
- Updates project status on failure
- Uses database transactions for consistency

**Health Check Response:**

```php
public function healthCheck(Project $project): array
{
    return [
        'status' => $project->status->value,
        'isRunning' => $isRunning,
        'healthStatus' => $healthStatus,
        'resources' => $resources,
        'containerId' => $project->container_id,
        'lastStartedAt' => $project->last_started_at?->toISOString(),
        'lastStoppedAt' => $project->last_stopped_at?->toISOString(),
        'error' => null,
    ];
}
```

### AnalyticsService

**Patterns:** Silent Failure with Logging

**Error Handling:**
- Catches all exceptions during event tracking
- Logs warnings with context
- Never propagates failures to callers
- Analytics failures do not block user operations

### DeviceHealthService

**Patterns:** Safe Defaults + Fallbacks

**Error Handling:**
- Returns `0` or `0.0` for missing metrics
- Implements OS-specific fallbacks (Linux → macOS)
- Returns `null` for unavailable data (temperature)
- Never throws exceptions

**Fallback Chain Example:**

```php
private function getCpuPercent(): float
{
    // Try Linux first
    $result = Process::run("top -bn1 | grep 'Cpu(s)' ...");
    if ($result->successful()) {
        return round((float) trim($result->output()), 1);
    }

    // Fallback to macOS
    $result = Process::run("ps -A -o %cpu | awk ...");
    if ($result->successful()) {
        return min(100.0, round((float) trim($result->output()), 1));
    }

    return 0.0; // Safe default
}
```

## Best Practices

### 1. Choose the Right Pattern

| Scenario | Recommended Pattern |
|----------|---------------------|
| External API calls | Retry + Circuit Breaker |
| File operations | Exception-Based |
| Process execution | Error Message Returns |
| Analytics/telemetry | Silent Failure with Logging |
| Health checks | Safe Defaults |
| User-facing operations | Result Objects |

### 2. Always Include Context

```php
// Bad
throw new \RuntimeException('Failed');

// Good
throw new \RuntimeException("Backup file does not exist: {$zipPath}");
```

### 3. Validate Before Acting

```php
public function start(): ?string
{
    if (! $this->hasCredentials()) {
        return 'Tunnel is not configured.';
    }

    if (! $this->hasSufficientDiskSpace($requiredBytes)) {
        return 'Insufficient disk space.';
    }

    // ... perform operation
}
```

### 4. Use Timeouts for External Operations

```php
$result = Process::timeout(120)->run($command);
```

### 5. Log Appropriately

```php
// Use debug for expected conditions
Log::debug('Skipped sending heartbeat: tunnel is skipped');

// Use warning for recoverable issues
Log::warning('Failed to track analytics event', [...]);

// Use error for failures requiring attention
Log::error('Failed to write tunnel token file', [...]);
```

### 6. Fail Fast with Circuit Breakers

```php
if (! $circuitBreaker->isClosed()) {
    throw new \RuntimeException('Service temporarily unavailable');
}
```

### 7. Document Error Scenarios

```php
/**
 * Start the tunnel.
 *
 * @return string|null null on success, error message on failure
 * @throws \RuntimeException if tunnel configuration is invalid
 */
public function start(): ?string;
```

## Testing Error Handling

### Retry Logic Tests

See `tests/Unit/Services/Traits/RetryableTraitTest.php`:

```php
public function test_retryable_trait_calculates_exponential_backoff(): void
{
    $service = new class {
        use RetryableTrait;
    };

    $delay1 = $service->calculateBackoffDelay(1);
    $delay2 = $service->calculateBackoffDelay(2);
    $delay3 = $service->calculateBackoffDelay(3);

    $this->assertGreaterThanOrEqual(80, $delay1);   // 100ms ±20%
    $this->assertGreaterThanOrEqual(160, $delay2);  // 200ms ±20%
    $this->assertGreaterThanOrEqual(320, $delay3);  // 400ms ±20%
}
```

### Circuit Breaker Tests

See `tests/Unit/Services/CircuitBreakerTest.php`:

```php
public function test_circuit_opens_after_failure_threshold(): void
{
    $breaker = new CircuitBreaker('test_service', failureThreshold: 3);

    $breaker->recordFailure();
    $breaker->recordFailure();
    $this->assertTrue($breaker->isClosed()); // Still closed

    $breaker->recordFailure();
    $this->assertFalse($breaker->isClosed()); // Now open
}
```

### Service Edge Case Tests

Each service has corresponding tests in `tests/Unit/Services/`:

- `BackupServiceTest.php` - Tests corrupted files, disk full scenarios
- `TunnelServiceTest.php` - Tests empty token files, permission errors
- `CodeServerServiceTest.php` - Tests config write failures, port conflicts
- `CloudApiClientTest.php` - Tests connection failures, retry behavior

---

**Last Updated:** 2026-03-13

For questions or updates to this document, refer to the service implementations in `app/Services/` and their corresponding test files.
