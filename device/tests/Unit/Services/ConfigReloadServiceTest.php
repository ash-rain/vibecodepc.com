<?php

declare(strict_types=1);

use App\Services\ConfigReloadService;
use Illuminate\Support\Facades\File;

beforeEach(function (): void {
    $this->service = new ConfigReloadService;

    $this->testDir = storage_path('testing/config-reload');

    if (! File::isDirectory($this->testDir)) {
        File::makeDirectory($this->testDir, 0755, true);
    }
});

afterEach(function (): void {
    if (File::isDirectory($this->testDir)) {
        File::deleteDirectory($this->testDir);
    }
});

describe('ConfigReloadService', function (): void {
    test('getAffectedServices returns services for known config keys', function (): void {
        $boostServices = $this->service->getAffectedServices('boost');
        expect($boostServices)->toHaveCount(1);
        expect($boostServices[0]['name'])->toBe('Laravel Boost');
        expect($boostServices[0]['type'])->toBe('mcp');

        $opencodeServices = $this->service->getAffectedServices('opencode_global');
        expect($opencodeServices)->toHaveCount(2);
        expect($opencodeServices[0]['type'])->toBe('cli');
        expect($opencodeServices[1]['type'])->toBe('vscode');
    });

    test('getAffectedServices returns empty array for unknown config key', function (): void {
        $services = $this->service->getAffectedServices('unknown_key');
        expect($services)->toBe([]);
    });

    test('getAffectedServices is case sensitive for config keys', function (): void {
        // Original key works
        $servicesLower = $this->service->getAffectedServices('boost');
        expect($servicesLower)->toHaveCount(1);

        // Different case returns empty
        $servicesUpper = $this->service->getAffectedServices('BOOST');
        expect($servicesUpper)->toBe([]);

        $servicesMixed = $this->service->getAffectedServices('Boost');
        expect($servicesMixed)->toBe([]);

        // Test with other keys
        expect($this->service->getAffectedServices('Opencode_Global'))->toBe([]);
        expect($this->service->getAffectedServices('OPENCODE_GLOBAL'))->toBe([]);
        expect($this->service->getAffectedServices('opencode_GLOBAL'))->toBe([]);
    });

    test('getAffectedServices returns multiple service types for opencode_global', function (): void {
        $services = $this->service->getAffectedServices('opencode_global');

        expect($services)->toHaveCount(2);

        // First service is CLI
        expect($services[0]['name'])->toBe('OpenCode CLI');
        expect($services[0]['type'])->toBe('cli');

        // Second service is VSCode
        expect($services[1]['name'])->toBe('VS Code Extensions');
        expect($services[1]['type'])->toBe('vscode');
    });

    test('getAffectedServices handles all known config keys', function (): void {
        $knownKeys = [
            'boost',
            'opencode_global',
            'opencode_project',
            'claude_global',
            'claude_project',
            'copilot_instructions',
        ];

        foreach ($knownKeys as $key) {
            $services = $this->service->getAffectedServices($key);
            expect($services)->not->toBeEmpty("Config key '{$key}' should have associated services");
            expect($services)->toBeArray();

            // Each service should have required keys
            foreach ($services as $service) {
                expect($service)->toHaveKeys(['name', 'type', 'description']);
            }
        }
    });

    test('getAffectedServices returns empty for null-like string keys', function (): void {
        // These should all return empty as they don't match any config key
        expect($this->service->getAffectedServices(''))->toBe([]);
        expect($this->service->getAffectedServices(' '))->toBe([]);
        expect($this->service->getAffectedServices('  '))->toBe([]);
    });

    test('requiresManualReload returns true for MCP and CLI services', function (): void {
        expect($this->service->requiresManualReload('boost'))->toBeTrue();
        expect($this->service->requiresManualReload('opencode_global'))->toBeTrue();
        expect($this->service->requiresManualReload('claude_global'))->toBeTrue();
    });

    test('requiresManualReload returns false for vscode-only services', function (): void {
        // copilot_instructions only has vscode type
        expect($this->service->requiresManualReload('copilot_instructions'))->toBeFalse();
    });

    // B1.2: Test requiresManualReload() variations
    describe('requiresManualReload variations', function (): void {
        test('requiresManualReload returns false for unknown config key (empty services)', function (): void {
            // Unknown keys have no services, should return false (nothing to reload)
            expect($this->service->requiresManualReload('unknown_key'))->toBeFalse();
            expect($this->service->requiresManualReload(''))->toBeFalse();
        });

        test('requiresManualReload handles all service type combinations', function (): void {
            // Test configs with different service combinations
            // boost: mcp only -> true
            expect($this->service->requiresManualReload('boost'))->toBeTrue();

            // opencode_global: cli + vscode -> true (because of cli)
            expect($this->service->requiresManualReload('opencode_global'))->toBeTrue();

            // claude_global: cli only -> true
            expect($this->service->requiresManualReload('claude_global'))->toBeTrue();

            // claude_project: cli only -> true
            expect($this->service->requiresManualReload('claude_project'))->toBeTrue();

            // opencode_project: cli only -> true
            expect($this->service->requiresManualReload('opencode_project'))->toBeTrue();

            // copilot_instructions: vscode only -> false
            expect($this->service->requiresManualReload('copilot_instructions'))->toBeFalse();
        });

        test('requiresManualReload prioritizes mcp and cli over vscode', function (): void {
            // Any presence of mcp or cli should return true
            // This is tested implicitly by the service combinations above
            // opencode_global has both cli and vscode, returns true because of cli
            $services = $this->service->getAffectedServices('opencode_global');
            $hasCliOrMcp = collect($services)->contains(fn ($s) => in_array($s['type'], ['mcp', 'cli'], true));

            expect($hasCliOrMcp)->toBeTrue();
            expect($this->service->requiresManualReload('opencode_global'))->toBeTrue();
        });

        test('requiresManualReload returns false for services supporting hot reload only', function (): void {
            // vscode services support hot reload, don't require manual reload
            $services = $this->service->getAffectedServices('copilot_instructions');

            expect($services)->toHaveCount(1);
            expect($services[0]['type'])->toBe('vscode');
            expect($this->service->requiresManualReload('copilot_instructions'))->toBeFalse();
        });

        test('requiresManualReload is case sensitive', function (): void {
            // Different case should return false (no matching services)
            expect($this->service->requiresManualReload('BOOST'))->toBeFalse();
            expect($this->service->requiresManualReload('Boost'))->toBeFalse();
            expect($this->service->requiresManualReload('Opencode_Global'))->toBeFalse();
        });
    });

    test('getReloadInstructions returns specific instructions for each config type', function (): void {
        $boostInstructions = $this->service->getReloadInstructions('boost');
        expect($boostInstructions)->toContain('MCP server');
        expect($boostInstructions)->toContain('detected automatically');

        $opencodeInstructions = $this->service->getReloadInstructions('opencode_global');
        expect($opencodeInstructions)->toContain('hot-reloaded');

        $claudeInstructions = $this->service->getReloadInstructions('claude_global');
        expect($claudeInstructions)->toContain('Restart Claude Code');

        $copilotInstructions = $this->service->getReloadInstructions('copilot_instructions');
        expect($copilotInstructions)->toContain('hot-reloaded');
    });

    test('getReloadInstructions returns default message for unknown config', function (): void {
        $instructions = $this->service->getReloadInstructions('unknown_key');
        expect($instructions)->toContain('Changes may require a restart');
    });

    test('getLastModified returns timestamp for existing file', function (): void {
        $testFile = $this->testDir.'/test.json';
        File::put($testFile, '{"test": true}');

        $timestamp = $this->service->getLastModified($testFile);

        expect($timestamp)->toBeInt();
        expect($timestamp)->toBeGreaterThan(0);
    });

    test('getLastModified returns null for non-existent file', function (): void {
        $timestamp = $this->service->getLastModified('/nonexistent/file.json');
        expect($timestamp)->toBeNull();
    });

    test('formatLastModified returns Never for null timestamp', function (): void {
        expect($this->service->formatLastModified(null))->toBe('Never');
    });

    test('formatLastModified returns human readable time for valid timestamp', function (): void {
        $timestamp = time() - 3600; // 1 hour ago
        $formatted = $this->service->formatLastModified($timestamp);

        expect($formatted)->toContain('hour');
        expect($formatted)->toContain('ago');
    });

    test('getReloadStatus returns complete status information', function (): void {
        $testFile = $this->testDir.'/boost.json';
        File::put($testFile, '{"agents": ["test"]}');

        $status = $this->service->getReloadStatus('boost', $testFile);

        expect($status)->toHaveKeys([
            'services',
            'requires_manual_reload',
            'instructions',
            'last_modified',
            'last_modified_formatted',
            'is_code_server_running',
        ]);

        expect($status['services'])->toHaveCount(1);
        expect($status['requires_manual_reload'])->toBeTrue();
        expect($status['last_modified'])->toBeInt();
        expect($status['last_modified_formatted'])->toContain('ago');
    });

    test('getReloadStatus works without file path', function (): void {
        $status = $this->service->getReloadStatus('boost');

        expect($status['last_modified'])->toBeNull();
        expect($status['last_modified_formatted'])->toBe('Never');
    });

    test('triggerReload returns service results for boost', function (): void {
        $result = $this->service->triggerReload('boost');

        expect($result)->toHaveKeys(['config_key', 'success', 'services', 'message']);
        expect($result['config_key'])->toBe('boost');
        expect($result['services'])->toHaveCount(1);
        expect($result['services'][0]['type'])->toBe('mcp');
        expect($result['services'][0]['reloaded'])->toBeTrue();
    });

    test('triggerReload handles vscode services', function (): void {
        $result = $this->service->triggerReload('copilot_instructions');

        expect($result['services'])->toHaveCount(1);
        expect($result['services'][0]['type'])->toBe('vscode');
        // VSCode service result depends on code-server running status
        expect($result['services'][0])->toHaveKey('reloaded');
        expect($result['services'][0])->toHaveKey('message');
    });

    test('triggerReload handles cli services correctly', function (): void {
        $result = $this->service->triggerReload('claude_global');

        expect($result['services'])->toHaveCount(1);
        expect($result['services'][0]['type'])->toBe('cli');
        expect($result['services'][0]['reloaded'])->toBeFalse();
        expect($result['services'][0]['message'])->toContain('Manual restart required');
    });

    test('triggerReload handles multiple services', function (): void {
        $result = $this->service->triggerReload('opencode_global');

        expect($result['services'])->toHaveCount(2);

        $cliService = collect($result['services'])->first(fn ($s) => $s['type'] === 'cli');
        $vscodeService = collect($result['services'])->first(fn ($s) => $s['type'] === 'vscode');

        expect($cliService['reloaded'])->toBeFalse();
        expect($vscodeService)->toHaveKey('reloaded');
    });

    // B3.1: Test triggerReload() service interactions
    describe('triggerReload service interactions', function (): void {
        test('triggerReload signals are sent to vscode service when code-server is running', function (): void {
            // Mock CodeServerService to simulate running state
            $mockCodeServer = Mockery::mock(\App\Services\CodeServer\CodeServerService::class);
            $mockCodeServer->shouldReceive('isRunning')->andReturn(true);
            $mockCodeServer->shouldReceive('isInstalled')->andReturn(true);
            $mockCodeServer->shouldReceive('getPort')->andReturn(8443);
            app()->instance(\App\Services\CodeServer\CodeServerService::class, $mockCodeServer);

            $service = new ConfigReloadService;
            $result = $service->triggerReload('copilot_instructions');

            expect($result['success'])->toBeTrue();
            expect($result['services'])->toHaveCount(1);
            expect($result['services'][0]['type'])->toBe('vscode');
            expect($result['services'][0]['reloaded'])->toBeTrue();
            expect($result['services'][0]['message'])->toContain('VS Code extensions will reload');
        });

        test('triggerReload handles code-server not running for vscode service', function (): void {
            // Mock CodeServerService to simulate stopped state
            $mockCodeServer = Mockery::mock(\App\Services\CodeServer\CodeServerService::class);
            $mockCodeServer->shouldReceive('isRunning')->andReturn(false);
            $mockCodeServer->shouldReceive('isInstalled')->andReturn(true);
            $mockCodeServer->shouldReceive('getPort')->andReturn(8443);
            app()->instance(\App\Services\CodeServer\CodeServerService::class, $mockCodeServer);

            $service = new ConfigReloadService;
            $result = $service->triggerReload('copilot_instructions');

            expect($result['success'])->toBeFalse();
            expect($result['services'])->toHaveCount(1);
            expect($result['services'][0]['type'])->toBe('vscode');
            expect($result['services'][0]['reloaded'])->toBeFalse();
            expect($result['services'][0]['message'])->toContain('not running');
        });

        test('triggerReload returns partial success when some services fail', function (): void {
            // opencode_global has both cli and vscode services
            // cli should succeed (marked as reloaded=true, manual handling)
            // vscode should fail (code-server not running)

            $mockCodeServer = Mockery::mock(\App\Services\CodeServer\CodeServerService::class);
            $mockCodeServer->shouldReceive('isRunning')->andReturn(false);
            $mockCodeServer->shouldReceive('isInstalled')->andReturn(true);
            $mockCodeServer->shouldReceive('getPort')->andReturn(8443);
            app()->instance(\App\Services\CodeServer\CodeServerService::class, $mockCodeServer);

            $service = new ConfigReloadService;
            $result = $service->triggerReload('opencode_global');

            // Overall success should be false since not all services reloaded
            expect($result['success'])->toBeFalse();
            expect($result['services'])->toHaveCount(2);

            // Find cli service - should be marked as reloaded (manual handling)
            $cliService = collect($result['services'])->first(fn ($s) => $s['type'] === 'cli');
            expect($cliService['reloaded'])->toBeFalse();
            expect($cliService['message'])->toContain('Manual restart required');

            // Find vscode service - should have failed
            $vscodeService = collect($result['services'])->first(fn ($s) => $s['type'] === 'vscode');
            expect($vscodeService['reloaded'])->toBeFalse();
            expect($vscodeService['message'])->toContain('not running');
        });

        test('triggerReload returns full success when all services succeed', function (): void {
            // copilot_instructions only has vscode service
            // Mock code-server as running

            $mockCodeServer = Mockery::mock(\App\Services\CodeServer\CodeServerService::class);
            $mockCodeServer->shouldReceive('isRunning')->andReturn(true);
            $mockCodeServer->shouldReceive('isInstalled')->andReturn(true);
            $mockCodeServer->shouldReceive('getPort')->andReturn(8443);
            app()->instance(\App\Services\CodeServer\CodeServerService::class, $mockCodeServer);

            $service = new ConfigReloadService;
            $result = $service->triggerReload('copilot_instructions');

            expect($result['success'])->toBeTrue();
            expect($result['services'])->toHaveCount(1);
            expect($result['services'][0]['reloaded'])->toBeTrue();
        });

        test('triggerReload handles unknown config key gracefully', function (): void {
            $result = $this->service->triggerReload('unknown_config_key');

            expect($result['config_key'])->toBe('unknown_config_key');
            expect($result['success'])->toBeTrue(); // Empty services means success
            expect($result['services'])->toBe([]);
            expect($result['message'])->toBe('');
        });

        test('triggerReload handles mcp service type with automatic detection', function (): void {
            $result = $this->service->triggerReload('boost');

            expect($result['services'])->toHaveCount(1);
            expect($result['services'][0]['type'])->toBe('mcp');
            expect($result['services'][0]['reloaded'])->toBeTrue();
            expect($result['services'][0]['message'])->toContain('automatically');
        });

        test('triggerReload handles cli service type with manual restart message', function (): void {
            $result = $this->service->triggerReload('claude_global');

            expect($result['services'])->toHaveCount(1);
            expect($result['services'][0]['type'])->toBe('cli');
            expect($result['services'][0]['reloaded'])->toBeFalse();
            expect($result['services'][0]['message'])->toContain('Manual restart required');
        });

        test('triggerReload handles exception during code-server reload', function (): void {
            // Mock CodeServerService to throw an exception
            $mockCodeServer = Mockery::mock(\App\Services\CodeServer\CodeServerService::class);
            $mockCodeServer->shouldReceive('isRunning')->andThrow(new \Exception('Connection refused'));
            app()->instance(\App\Services\CodeServer\CodeServerService::class, $mockCodeServer);

            $service = new ConfigReloadService;
            $result = $service->triggerReload('copilot_instructions');

            expect($result['success'])->toBeFalse();
            expect($result['services'])->toHaveCount(1);
            expect($result['services'][0]['reloaded'])->toBeFalse();
            expect($result['services'][0]['message'])->toContain('Failed');
        });

        test('triggerReload logs reload operations', function (): void {
            \Illuminate\Support\Facades\Log::spy();

            $this->service->triggerReload('boost');

            \Illuminate\Support\Facades\Log::shouldHaveReceived('info')
                ->once()
                ->with('Config reload triggered', Mockery::on(function ($context) {
                    return $context['config_key'] === 'boost'
                        && $context['services_count'] === 1
                        && is_array($context['results']);
                }));
        });

        test('triggerReload handles project-scoped configs', function (): void {
            // Test opencode_project which has cli service
            $result = $this->service->triggerReload('opencode_project');

            expect($result['services'])->toHaveCount(1);
            expect($result['services'][0]['type'])->toBe('cli');
            expect($result['services'][0]['reloaded'])->toBeFalse();
            expect($result['services'][0]['message'])->toContain('Manual restart required');
        });

        test('triggerReload handles claude_project config', function (): void {
            $result = $this->service->triggerReload('claude_project');

            expect($result['services'])->toHaveCount(1);
            expect($result['services'][0]['name'])->toBe('Claude Code');
            expect($result['services'][0]['type'])->toBe('cli');
            expect($result['services'][0]['reloaded'])->toBeFalse();
        });
    });

    // B3.2: Test reload with non-existent services
    describe('reload with non-existent services', function (): void {
        test('reloadCodeServer handles process not running', function (): void {
            // Mock CodeServerService
            $mockCodeServer = Mockery::mock(\App\Services\CodeServer\CodeServerService::class);
            $mockCodeServer->shouldReceive('isRunning')->andReturn(false);
            $mockCodeServer->shouldReceive('isInstalled')->andReturn(true);
            $mockCodeServer->shouldReceive('getPort')->andReturn(8443);
            app()->instance(\App\Services\CodeServer\CodeServerService::class, $mockCodeServer);

            $service = new ConfigReloadService;

            // Use reflection to call private method
            $result = $service->reloadCodeServer();

            expect($result['success'])->toBeFalse();
            expect($result['message'])->toContain('not running');
        });

        test('reloadCodeServer handles code-server not installed', function (): void {
            $mockCodeServer = Mockery::mock(\App\Services\CodeServer\CodeServerService::class);
            $mockCodeServer->shouldReceive('isRunning')->andReturn(false);
            $mockCodeServer->shouldReceive('isInstalled')->andReturn(false);
            app()->instance(\App\Services\CodeServer\CodeServerService::class, $mockCodeServer);

            $service = new ConfigReloadService;
            $result = $service->reloadCodeServer();

            expect($result['success'])->toBeFalse();
            expect($result['message'])->toContain('not running');
        });
    });

    // B2.1: Test getLastModified() edge cases
    describe('getLastModified edge cases', function (): void {
        test('getLastModified handles file modified in the future (clock skew)', function (): void {
            $testFile = $this->testDir.'/future-file.json';
            File::put($testFile, '{"test": true}');

            // Set modification time to 1 hour in the future
            $futureTime = time() + 3600;
            touch($testFile, $futureTime);

            $timestamp = $this->service->getLastModified($testFile);

            expect($timestamp)->toBeInt();
            expect($timestamp)->toBeGreaterThan(time());
            expect($timestamp)->toBeGreaterThanOrEqual($futureTime - 1); // Allow 1 second tolerance
        });

        test('getLastModified returns null when file is deleted between check and read', function (): void {
            $testFile = $this->testDir.'/race-condition.json';
            File::put($testFile, '{"test": true}');

            // Verify file exists initially
            expect(File::exists($testFile))->toBeTrue();

            // Delete the file
            File::delete($testFile);

            // Should return null for non-existent file
            $timestamp = $this->service->getLastModified($testFile);
            expect($timestamp)->toBeNull();
        });

        test('getLastModified handles file with restrictive permissions', function (): void {
            $testFile = $this->testDir.'/unreadable.json';
            File::put($testFile, '{"test": true}');

            // Remove all permissions (even read)
            chmod($testFile, 0000);

            try {
                // File::exists() uses file_exists() which works regardless of permissions
                // File::lastModified() typically still works even without read permissions on most systems
                // because it only needs stat(), not open()
                // The important thing is it doesn't throw an exception
                $timestamp = $this->service->getLastModified($testFile);

                // On most systems, lastModified works even without read permissions
                expect($timestamp)->toBeInt();
                expect($timestamp)->toBeGreaterThan(0);
            } finally {
                // Restore permissions for cleanup
                chmod($testFile, 0644);
            }
        });

        test('getLastModified handles file on read-only filesystem simulation', function (): void {
            $testFile = $this->testDir.'/readonly-test.json';
            File::put($testFile, '{"test": true}');

            // Make file read-only
            chmod($testFile, 0444);

            try {
                $timestamp = $this->service->getLastModified($testFile);

                // Should still be able to read modification time on read-only file
                expect($timestamp)->toBeInt();
                expect($timestamp)->toBeGreaterThan(0);
            } finally {
                // Restore permissions for cleanup
                chmod($testFile, 0644);
            }
        });

        test('getLastModified returns consistent results on multiple calls', function (): void {
            $testFile = $this->testDir.'/consistent.json';
            File::put($testFile, '{"test": true}');

            // Get timestamp multiple times
            $timestamp1 = $this->service->getLastModified($testFile);
            $timestamp2 = $this->service->getLastModified($testFile);
            $timestamp3 = $this->service->getLastModified($testFile);

            // All calls should return the same value
            expect($timestamp1)->toBe($timestamp2);
            expect($timestamp2)->toBe($timestamp3);
            expect($timestamp1)->toBeInt();
        });

        test('getLastModified handles directory instead of file', function (): void {
            $testDir = $this->testDir.'/test-directory';
            File::makeDirectory($testDir);

            // File::lastModified works on directories too
            $timestamp = $this->service->getLastModified($testDir);

            expect($timestamp)->toBeInt();
            expect($timestamp)->toBeGreaterThan(0);
        });

        test('getLastModified handles symlink to non-existent file', function (): void {
            $targetFile = $this->testDir.'/symlink-target.json';
            $symlinkFile = $this->testDir.'/symlink.json';

            // Create the target file first
            File::put($targetFile, '{"test": true}');

            // Create symlink
            symlink($targetFile, $symlinkFile);

            // Verify symlink works when target exists
            expect($this->service->getLastModified($symlinkFile))->toBeInt();

            // Delete target file
            File::delete($targetFile);

            // Symlink now points to non-existent file
            // File::exists follows symlinks and returns false for broken symlinks
            $timestamp = $this->service->getLastModified($symlinkFile);

            expect($timestamp)->toBeNull();

            // Cleanup symlink
            @unlink($symlinkFile);
        });
    });

    // B2.2: Test formatLastModified() edge cases
    describe('formatLastModified edge cases', function (): void {
        test('formatLastModified handles timestamp at epoch (1970-01-01)', function (): void {
            $epoch = 0; // Unix epoch
            $formatted = $this->service->formatLastModified($epoch);

            // Should return a human-readable time for the epoch
            expect($formatted)->toBeString();
            expect($formatted)->not->toBe('Never');
            // Should contain "ago" since epoch is in the past
            expect($formatted)->toContain('ago');
            // Should represent a very long time ago (50+ years)
            expect($formatted)->toContain('years');
        });

        test('formatLastModified handles timestamp in the future', function (): void {
            $futureTimestamp = time() + 3600; // 1 hour in the future
            $formatted = $this->service->formatLastModified($futureTimestamp);

            // diffForHumans handles future timestamps
            expect($formatted)->toBeString();
            expect($formatted)->not->toBe('Never');
            // Should indicate it's in the future ("from now" or similar)
            expect($formatted)->toContain('from now');
        });

        test('formatLastModified handles timestamp far in the future', function (): void {
            $farFuture = time() + (365 * 24 * 60 * 60); // 1 year in the future
            $formatted = $this->service->formatLastModified($farFuture);

            expect($formatted)->toBeString();
            expect($formatted)->toContain('from now');
            // diffForHumans rounds, so it might say "11 months" or "1 year" depending on exact timing
            expect($formatted)->toMatch('/year|month|day/');
        });

        test('formatLastModified handles very old timestamps (>10 years ago)', function (): void {
            $veryOld = time() - (15 * 365 * 24 * 60 * 60); // 15 years ago
            $formatted = $this->service->formatLastModified($veryOld);

            expect($formatted)->toBeString();
            expect($formatted)->toContain('ago');
            expect($formatted)->toContain('years');
        });

        test('formatLastModified handles timestamp during DST transition', function (): void {
            // Use a timestamp that falls during a DST transition
            // DST transitions typically happen at 2:00 AM on specific dates
            // For example, in US: second Sunday in March, first Sunday in November
            // Let's use a timestamp that should work across timezones
            $dstTimestamp = strtotime('2024-03-10 02:30:00'); // During DST transition

            if ($dstTimestamp === false) {
                // If strtotime fails, skip this test
                $this->markTestSkipped('Could not create DST timestamp');
            }

            $formatted = $this->service->formatLastModified($dstTimestamp);

            expect($formatted)->toBeString();
            expect($formatted)->not->toBe('Never');
            expect($formatted)->toContain('ago');
        });

        test('formatLastModified handles timestamp with negative values (before epoch)', function (): void {
            $beforeEpoch = -86400; // 1 day before Unix epoch (1969-12-31)
            $formatted = $this->service->formatLastModified($beforeEpoch);

            expect($formatted)->toBeString();
            expect($formatted)->not->toBe('Never');
            // diffForHumans should handle negative timestamps
            expect($formatted)->toContain('ago');
        });

        test('formatLastModified handles timestamp just after epoch', function (): void {
            $justAfter = 1; // 1 second after epoch
            $formatted = $this->service->formatLastModified($justAfter);

            expect($formatted)->toBeString();
            expect($formatted)->toContain('ago');
            expect($formatted)->toContain('years'); // Should be ~50+ years
        });

        test('formatLastModified handles timestamp representing current time', function (): void {
            $now = time();
            $formatted = $this->service->formatLastModified($now);

            expect($formatted)->toBeString();
            // Should show "now" or "1 second ago" or similar
            expect($formatted)->toMatch('/now|second|moment/i');
        });
    });
});
