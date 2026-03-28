<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;

class ConfigReloadService
{
    /**
     * Configuration file key to service mapping.
     *
     * @var array<string, array<int, array{name: string, type: string, description: string}>>
     */
    private const CONFIG_SERVICE_MAP = [
        'boost' => [
            [
                'name' => 'Laravel Boost',
                'type' => 'mcp',
                'description' => 'MCP server for Laravel development',
            ],
        ],
        'opencode_global' => [
            [
                'name' => 'OpenCode CLI',
                'type' => 'cli',
                'description' => 'Global OpenCode configuration',
            ],
            [
                'name' => 'VS Code Extensions',
                'type' => 'vscode',
                'description' => 'OpenCode VS Code extensions',
            ],
        ],
        'opencode_project' => [
            [
                'name' => 'OpenCode CLI',
                'type' => 'cli',
                'description' => 'Project-level OpenCode configuration',
            ],
        ],
        'claude_global' => [
            [
                'name' => 'Claude Code',
                'type' => 'cli',
                'description' => 'Global Claude Code settings',
            ],
        ],
        'claude_project' => [
            [
                'name' => 'Claude Code',
                'type' => 'cli',
                'description' => 'Project-level Claude Code settings',
            ],
        ],
        'copilot_instructions' => [
            [
                'name' => 'GitHub Copilot',
                'type' => 'vscode',
                'description' => 'Copilot custom instructions',
            ],
        ],
    ];

    /**
     * Get the services that need reload for a given config key.
     *
     * @param  string  $configKey  The configuration file key
     * @return array<int, array{name: string, type: string, description: string}>
     */
    public function getAffectedServices(string $configKey): array
    {
        return self::CONFIG_SERVICE_MAP[$configKey] ?? [];
    }

    /**
     * Check if a config file requires manual reload vs auto-reload.
     *
     * @param  string  $configKey  The configuration file key
     * @return bool True if manual reload is recommended
     */
    public function requiresManualReload(string $configKey): bool
    {
        $services = $this->getAffectedServices($configKey);

        foreach ($services as $service) {
            if (in_array($service['type'], ['mcp', 'cli'], true)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Get reload instructions for a given config file.
     *
     * @param  string  $configKey  The configuration file key
     * @return string Instructions for reloading
     */
    public function getReloadInstructions(string $configKey): string
    {
        return match ($configKey) {
            'boost' => 'Laravel Boost configuration changes are detected automatically by the MCP server. Changes will take effect on the next interaction.',
            'opencode_global', 'opencode_project' => 'OpenCode configuration is hot-reloaded. Changes will take effect immediately for new conversations. Active conversations may need to be restarted.',
            'claude_global', 'claude_project' => 'Claude Code settings are applied on startup. Restart Claude Code to apply changes: "Cmd/Ctrl + Shift + P" -> "Claude: Restart"',
            'copilot_instructions' => 'Copilot instructions are hot-reloaded when files are saved. Changes will apply to the next AI interaction.',
            default => 'Configuration saved. Changes may require a restart of the related service.',
        };
    }

    /**
     * Check if code-server is running.
     *
     * @return bool True if code-server is running
     */
    public function isCodeServerRunning(): bool
    {
        $codeServerService = app(CodeServer\CodeServerService::class);

        return $codeServerService->isRunning();
    }

    /**
     * Reload code-server to apply VS Code extension configuration changes.
     *
     * @return array{success: bool, message: string}
     */
    public function reloadCodeServer(): array
    {
        try {
            $codeServerService = app(CodeServer\CodeServerService::class);

            if (! $codeServerService->isRunning()) {
                return [
                    'success' => false,
                    'message' => 'code-server is not running. Configuration will be applied on next start.',
                ];
            }

            $result = Process::run('pkill -SIGUSR1 code-server 2>/dev/null || true');

            Log::info('code-server reload triggered', ['result' => $result->successful()]);

            return [
                'success' => true,
                'message' => 'VS Code extensions will reload their configuration.',
            ];
        } catch (\Exception $e) {
            Log::error('Failed to reload code-server', ['error' => $e->getMessage()]);

            return [
                'success' => false,
                'message' => 'Failed to trigger reload: '.$e->getMessage(),
            ];
        }
    }

    /**
     * Get the last modified time of a config file.
     *
     * @param  string  $path  The file path
     * @return int|null Timestamp or null if file doesn't exist
     */
    public function getLastModified(string $path): ?int
    {
        if (! File::exists($path)) {
            return null;
        }

        return File::lastModified($path);
    }

    /**
     * Format last modified time for display.
     *
     * @param  int|null  $timestamp  Unix timestamp
     * @return string Formatted time string
     */
    public function formatLastModified(?int $timestamp): string
    {
        if ($timestamp === null) {
            return 'Never';
        }

        return now()->setTimestamp($timestamp)->diffForHumans();
    }

    /**
     * Get all reload status information for a config file.
     *
     * @param  string  $configKey  The configuration key
     * @param  string|null  $path  The file path (optional)
     * @return array<string, mixed>
     */
    public function getReloadStatus(string $configKey, ?string $path = null): array
    {
        $services = $this->getAffectedServices($configKey);
        $requiresManual = $this->requiresManualReload($configKey);
        $instructions = $this->getReloadInstructions($configKey);

        $status = [
            'services' => $services,
            'requires_manual_reload' => $requiresManual,
            'instructions' => $instructions,
            'last_modified' => null,
            'last_modified_formatted' => 'Never',
            'is_code_server_running' => $this->isCodeServerRunning(),
        ];

        if ($path !== null) {
            $lastModified = $this->getLastModified($path);
            $status['last_modified'] = $lastModified;
            $status['last_modified_formatted'] = $this->formatLastModified($lastModified);
        }

        return $status;
    }

    /**
     * Trigger reload for services affected by a config change.
     *
     * @param  string  $configKey  The configuration key that was changed
     * @return array<string, mixed> Results of reload operations
     */
    public function triggerReload(string $configKey): array
    {
        $results = [
            'config_key' => $configKey,
            'success' => true,
            'services' => [],
            'message' => '',
        ];

        $services = $this->getAffectedServices($configKey);

        foreach ($services as $service) {
            $serviceResult = [
                'name' => $service['name'],
                'type' => $service['type'],
                'reloaded' => false,
                'message' => '',
            ];

            switch ($service['type']) {
                case 'mcp':
                    $serviceResult['reloaded'] = true;
                    $serviceResult['message'] = 'MCP server will detect changes automatically';
                    break;

                case 'cli':
                    $serviceResult['reloaded'] = false;
                    $serviceResult['message'] = 'Manual restart required. Stop and restart the tool to apply changes.';
                    break;

                case 'vscode':
                    $reloadResult = $this->reloadCodeServer();
                    $serviceResult['reloaded'] = $reloadResult['success'];
                    $serviceResult['message'] = $reloadResult['message'];
                    break;
            }

            $results['services'][] = $serviceResult;
        }

        $results['success'] = collect($results['services'])->every(fn ($s) => $s['reloaded']);

        Log::info('Config reload triggered', [
            'config_key' => $configKey,
            'services_count' => count($services),
            'results' => $results['services'],
        ]);

        return $results;
    }
}
