<?php

use App\Services\CircuitBreaker;
use Illuminate\Support\Facades\Cache;

beforeEach(function () {
    // Clear cache before each test
    Cache::flush();
});

afterEach(function () {
    Cache::flush();
});

it('starts in closed state', function () {
    $circuitBreaker = new CircuitBreaker('test_service');

    expect($circuitBreaker->isClosed())->toBeTrue()
        ->and($circuitBreaker->getCurrentState())->toBe('closed')
        ->and($circuitBreaker->getFailureCount())->toBe(0)
        ->and($circuitBreaker->getSuccessCount())->toBe(0);
});

it('opens circuit after reaching failure threshold', function () {
    $circuitBreaker = new CircuitBreaker(
        serviceName: 'test_service',
        failureThreshold: 3
    );

    // Record 3 failures
    $circuitBreaker->recordFailure();
    $circuitBreaker->recordFailure();
    $circuitBreaker->recordFailure();

    expect($circuitBreaker->isClosed())->toBeFalse()
        ->and($circuitBreaker->getCurrentState())->toBe('open')
        ->and($circuitBreaker->getFailureCount())->toBe(0); // Reset after opening
});

it('resets failure count on success in closed state', function () {
    $circuitBreaker = new CircuitBreaker(
        serviceName: 'test_service',
        failureThreshold: 5
    );

    // Record 2 failures
    $circuitBreaker->recordFailure();
    $circuitBreaker->recordFailure();

    expect($circuitBreaker->getFailureCount())->toBe(2);

    // Record a success
    $circuitBreaker->recordSuccess();

    expect($circuitBreaker->getFailureCount())->toBe(0)
        ->and($circuitBreaker->isClosed())->toBeTrue();
});

it('transitions to half-open after recovery timeout', function () {
    $circuitBreaker = new CircuitBreaker(
        serviceName: 'test_service',
        failureThreshold: 1,
        recoveryTimeout: 1 // 1 second for testing
    );

    // Open the circuit
    $circuitBreaker->recordFailure();

    // Should be open immediately after failure
    expect($circuitBreaker->isClosed())->toBeFalse();
    expect($circuitBreaker->getCurrentState())->toBe('open');

    // Wait for recovery timeout
    sleep(2);

    // isClosed() should trigger transition to half-open and return true
    $isClosed = $circuitBreaker->isClosed();
    expect($isClosed)->toBeTrue();

    // State should now be half_open
    expect($circuitBreaker->getCurrentState())->toBe('half_open');
});

it('closes circuit after success threshold in half-open state', function () {
    $circuitBreaker = new CircuitBreaker(
        serviceName: 'test_service',
        failureThreshold: 1,
        recoveryTimeout: 1, // 1 second timeout
        successThreshold: 2
    );

    // Open the circuit
    $circuitBreaker->recordFailure();

    // Circuit should be open
    expect($circuitBreaker->getCurrentState())->toBe('open');

    // Wait for recovery timeout to transition to half-open
    sleep(2);

    // Trigger transition to half-open
    $circuitBreaker->isClosed();

    expect($circuitBreaker->getCurrentState())->toBe('half_open');

    // First success in half-open
    $circuitBreaker->recordSuccess();

    expect($circuitBreaker->getCurrentState())->toBe('half_open')
        ->and($circuitBreaker->getSuccessCount())->toBe(1);

    // Second success closes the circuit
    $circuitBreaker->recordSuccess();

    expect($circuitBreaker->getCurrentState())->toBe('closed')
        ->and($circuitBreaker->getSuccessCount())->toBe(0)
        ->and($circuitBreaker->isClosed())->toBeTrue();
});

it('reopens circuit immediately on failure in half-open state', function () {
    $circuitBreaker = new CircuitBreaker(
        serviceName: 'test_service',
        failureThreshold: 1,
        recoveryTimeout: 1 // 1 second timeout
    );

    // Open the circuit
    $circuitBreaker->recordFailure();

    // Circuit should be open
    expect($circuitBreaker->getCurrentState())->toBe('open');

    // Wait for recovery timeout
    sleep(2);

    // Trigger transition to half-open
    $circuitBreaker->isClosed();

    // Should be half-open
    expect($circuitBreaker->getCurrentState())->toBe('half_open');

    // Failure in half-open reopens immediately
    $circuitBreaker->recordFailure();

    expect($circuitBreaker->getCurrentState())->toBe('open')
        ->and($circuitBreaker->isClosed())->toBeFalse();
});

it('allows manual reset to closed state', function () {
    $circuitBreaker = new CircuitBreaker(
        serviceName: 'test_service',
        failureThreshold: 1
    );

    // Open the circuit
    $circuitBreaker->recordFailure();

    expect($circuitBreaker->isClosed())->toBeFalse();

    // Reset manually
    $circuitBreaker->reset();

    expect($circuitBreaker->isClosed())->toBeTrue()
        ->and($circuitBreaker->getCurrentState())->toBe('closed')
        ->and($circuitBreaker->getFailureCount())->toBe(0)
        ->and($circuitBreaker->getSuccessCount())->toBe(0);
});

it('maintains separate state per service name', function () {
    $circuitBreaker1 = new CircuitBreaker('service_a', failureThreshold: 1);
    $circuitBreaker2 = new CircuitBreaker('service_b', failureThreshold: 1);

    // Open circuit 1
    $circuitBreaker1->recordFailure();

    // Circuit 2 should still be closed
    expect($circuitBreaker1->isClosed())->toBeFalse()
        ->and($circuitBreaker2->isClosed())->toBeTrue();
});

it('tracks failure count incrementally', function () {
    $circuitBreaker = new CircuitBreaker(
        serviceName: 'test_service',
        failureThreshold: 10
    );

    expect($circuitBreaker->getFailureCount())->toBe(0);

    $circuitBreaker->recordFailure();
    expect($circuitBreaker->getFailureCount())->toBe(1);

    $circuitBreaker->recordFailure();
    expect($circuitBreaker->getFailureCount())->toBe(2);

    $circuitBreaker->recordFailure();
    expect($circuitBreaker->getFailureCount())->toBe(3);
});

it('prevents requests when circuit is open', function () {
    $circuitBreaker = new CircuitBreaker(
        serviceName: 'test_service',
        failureThreshold: 1
    );

    expect($circuitBreaker->isClosed())->toBeTrue();

    $circuitBreaker->recordFailure();

    expect($circuitBreaker->isClosed())->toBeFalse();
});

it('uses custom configuration values', function () {
    $circuitBreaker = new CircuitBreaker(
        serviceName: 'custom_service',
        failureThreshold: 10,
        recoveryTimeout: 300,
        successThreshold: 5
    );

    // Record 9 failures - should still be closed
    for ($i = 0; $i < 9; $i++) {
        $circuitBreaker->recordFailure();
    }

    expect($circuitBreaker->isClosed())->toBeTrue();

    // 10th failure opens circuit
    $circuitBreaker->recordFailure();

    expect($circuitBreaker->isClosed())->toBeFalse();
});

it('resets success count on transition to half-open', function () {
    $circuitBreaker = new CircuitBreaker(
        serviceName: 'test_service',
        failureThreshold: 1,
        recoveryTimeout: 0
    );

    // Open circuit
    $circuitBreaker->recordFailure();

    // Transition to half-open
    $circuitBreaker->isClosed();

    expect($circuitBreaker->getCurrentState())->toBe('half_open')
        ->and($circuitBreaker->getSuccessCount())->toBe(0);
});

it('persists state across multiple instances', function () {
    // Create first instance and open circuit
    $circuitBreaker1 = new CircuitBreaker(
        serviceName: 'persistent_service',
        failureThreshold: 1
    );
    $circuitBreaker1->recordFailure();

    // Create second instance with same service name
    $circuitBreaker2 = new CircuitBreaker(
        serviceName: 'persistent_service',
        failureThreshold: 1
    );

    // Should see the same state
    expect($circuitBreaker2->isClosed())->toBeFalse()
        ->and($circuitBreaker2->getCurrentState())->toBe('open');
});
