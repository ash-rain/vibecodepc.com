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
});
