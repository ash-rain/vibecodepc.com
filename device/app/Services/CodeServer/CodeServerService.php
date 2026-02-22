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
        return $this->getVersion() !== null;
    }

    public function isRunning(): bool
    {
        $result = Process::run(sprintf(
            'lsof -iTCP:%d -sTCP:LISTEN -t 2>/dev/null || ss -tlnp sport = :%d 2>/dev/null | grep -q LISTEN',
            $this->port,
            $this->port,
        ));

        return $result->successful();
    }

    public function getVersion(): ?string
    {
        $result = Process::run('code-server --version 2>/dev/null');

        if (! $result->successful()) {
            return null;
        }

        // code-server outputs debug lines (starting with "[") before the version
        foreach (explode("\n", trim($result->output())) as $line) {
            if (preg_match('/^\d+\.\d+\.\d+/', $line)) {
                return $line;
            }
        }

        return null;
    }

    /**
     * @param  array<int, string>  $extensions
     * @return array<int, string> List of extensions that failed to install (empty = all succeeded).
     */
    public function installExtensions(array $extensions): array
    {
        $failed = [];

        foreach ($extensions as $extension) {
            $result = Process::timeout(120)->run(
                sprintf('code-server --install-extension %s 2>&1', escapeshellarg($extension)),
            );

            if (! $result->successful() && ! str_contains($result->output(), 'already installed')) {
                $failed[] = $extension;
            }
        }

        return $failed;
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
        $result = Process::run('sudo systemctl restart code-server@vibecodepc 2>/dev/null || true');

        return $result->successful();
    }
}
