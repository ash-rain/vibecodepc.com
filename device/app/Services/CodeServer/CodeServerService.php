<?php

declare(strict_types=1);

namespace App\Services\CodeServer;

use Illuminate\Support\Facades\Process;

class CodeServerService
{
    public function __construct(
        private readonly int $port = 8443,
        private readonly string $configPath = '/home/vibecodepc/.config/code-server/config.yaml',
    ) {}

    public function isInstalled(): bool
    {
        $result = Process::run('which code-server');

        return $result->successful();
    }

    public function isRunning(): bool
    {
        $result = Process::run('systemctl is-active code-server@vibecodepc');

        return $result->successful() && str_contains(trim($result->output()), 'active');
    }

    public function getVersion(): ?string
    {
        $result = Process::run('code-server --version');

        if (! $result->successful()) {
            return null;
        }

        $lines = explode("\n", trim($result->output()));

        return $lines[0] ?? null;
    }

    /** @param array<int, string> $extensions */
    public function installExtensions(array $extensions): bool
    {
        $allSucceeded = true;

        foreach ($extensions as $extension) {
            $result = Process::timeout(120)->run(
                sprintf('code-server --install-extension %s', escapeshellarg($extension)),
            );

            if (! $result->successful()) {
                $allSucceeded = false;
            }
        }

        return $allSucceeded;
    }

    public function setTheme(string $theme): bool
    {
        $settingsPath = '/home/vibecodepc/.local/share/code-server/User/settings.json';

        $result = Process::run(sprintf('cat %s 2>/dev/null', escapeshellarg($settingsPath)));

        $settings = $result->successful() ? json_decode($result->output(), true) ?? [] : [];
        $settings['workbench.colorTheme'] = $theme;

        $result = Process::run(sprintf(
            'mkdir -p %s && echo %s > %s',
            escapeshellarg(dirname($settingsPath)),
            escapeshellarg(json_encode($settings, JSON_PRETTY_PRINT)),
            escapeshellarg($settingsPath),
        ));

        return $result->successful();
    }

    public function setPassword(string $password): bool
    {
        $result = Process::run(sprintf(
            "sed -i 's/^password:.*/password: %s/' %s",
            escapeshellarg($password),
            escapeshellarg($this->configPath),
        ));

        return $result->successful();
    }

    public function getUrl(): string
    {
        return "http://localhost:{$this->port}";
    }

    public function restart(): bool
    {
        $result = Process::run('sudo systemctl restart code-server@vibecodepc');

        return $result->successful();
    }
}
