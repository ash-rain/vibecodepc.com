<?php

declare(strict_types=1);

namespace App\Services\CodeServer;

use Illuminate\Support\Facades\Process;
use VibecodePC\Common\Enums\AiProvider;

class CodeServerService
{
    private ?array $parsedConfig = null;

    public function __construct(
        private readonly ?int $port = null,
        private readonly string $configPath = '',
        private readonly string $settingsPath = '',
    ) {}

    public function isInstalled(): bool
    {
        return $this->getVersion() !== null;
    }

    public function getPort(): int
    {
        if ($this->port !== null) {
            return $this->port;
        }

        $config = $this->parseConfig();

        return $config['port'] ?? 8443;
    }

    public function getPassword(): ?string
    {
        $config = $this->parseConfig();

        return $config['password'] ?? null;
    }

    /**
     * @return array{port?: int, password?: string}
     */
    private function parseConfig(): array
    {
        if ($this->parsedConfig !== null) {
            return $this->parsedConfig;
        }

        $this->parsedConfig = [];

        $result = Process::run(sprintf('cat %s 2>/dev/null', escapeshellarg($this->configPath)));

        if (! $result->successful()) {
            return $this->parsedConfig;
        }

        $output = $result->output();

        if (preg_match('/^bind-addr:\s*[\w.:]+:(\d+)/m', $output, $matches)) {
            $this->parsedConfig['port'] = (int) $matches[1];
        }

        if (preg_match('/^password:\s*(.+)$/m', $output, $matches)) {
            $this->parsedConfig['password'] = trim($matches[1]);
        }

        return $this->parsedConfig;
    }

    public function isRunning(): bool
    {
        $port = $this->getPort();

        $result = Process::run(sprintf(
            '/usr/sbin/lsof -iTCP:%d -sTCP:LISTEN -t 2>/dev/null || lsof -iTCP:%d -sTCP:LISTEN -t 2>/dev/null || ss -tlnp sport = :%d 2>/dev/null | grep -q LISTEN || curl -sf -o /dev/null http://127.0.0.1:%d/healthz 2>/dev/null',
            $port,
            $port,
            $port,
            $port,
        ));

        return $result->successful();
    }

    public function getVersion(): ?string
    {
        $result = Process::run($this->shell('code-server --version 2>/dev/null'));

        if (! $result->successful()) {
            return null;
        }

        foreach (explode("\n", trim($result->output())) as $line) {
            if (preg_match('/^\d+\.\d+\.\d+/', $line)) {
                return $line;
            }
        }

        return null;
    }

    /**
     * @return array<int, array{id: string, version: string}>
     */
    public function listExtensions(): array
    {
        $result = Process::timeout(30)->run(
            $this->shell('code-server --list-extensions --show-versions 2>/dev/null'),
        );

        if (! $result->successful()) {
            return [];
        }

        $extensions = [];

        foreach (explode("\n", trim($result->output())) as $line) {
            $line = trim($line);

            if ($line === '' || ! str_contains($line, '@')) {
                continue;
            }

            [$id, $version] = explode('@', $line, 2);
            $extensions[] = ['id' => $id, 'version' => $version];
        }

        return $extensions;
    }

    public function uninstallExtension(string $id): bool
    {
        $result = Process::timeout(60)->run(
            $this->shell(sprintf('code-server --uninstall-extension %s 2>&1', escapeshellarg($id))),
        );

        return $result->successful();
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
                $this->shell(sprintf('code-server --install-extension %s 2>&1', escapeshellarg($extension))),
            );

            if (! $result->successful() && ! str_contains($result->output(), 'already installed')) {
                $failed[] = $extension;
            }
        }

        return $failed;
    }

    public function setTheme(string $theme): bool
    {
        $settingsPath = $this->settingsPath;

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
        return "http://localhost:{$this->getPort()}";
    }

    /**
     * Ensure code-server config has auth disabled since the device dashboard handles access control.
     */
    public function disableAuth(): bool
    {
        $result = Process::run(sprintf('cat %s 2>/dev/null', escapeshellarg($this->configPath)));

        if (! $result->successful()) {
            return false;
        }

        $config = $result->output();

        if (preg_match('/^auth:\s*none$/m', $config)) {
            return true;
        }

        $config = preg_replace('/^auth:\s*.+$/m', 'auth: none', $config);

        return file_put_contents($this->configPath, $config) !== false;
    }

    /**
     * Start code-server. Returns null on success, or an error message on failure.
     */
    public function start(): ?string
    {
        if ($this->isRunning()) {
            return null;
        }

        if (! $this->isInstalled()) {
            return 'code-server is not installed.';
        }

        // Disable auth since the device dashboard handles access control
        $this->disableAuth();

        // Try systemd first (production RPi), then direct launch (dev/macOS)
        $result = Process::run('sudo systemctl start code-server@vibecodepc 2>&1');

        if ($result->successful()) {
            sleep(1);

            return $this->isRunning() ? null : 'Service started but code-server is not responding on port '.$this->getPort().'.';
        }

        // Direct launch as background process with auth disabled as belt-and-suspenders
        $port = $this->getPort();
        $result = Process::run($this->shell(sprintf(
            'nohup code-server --auth none --bind-addr 127.0.0.1:%d > /tmp/code-server.log 2>&1 & echo $!',
            $port,
        )));

        if (! $result->successful()) {
            return 'Failed to start code-server: '.$result->errorOutput();
        }

        // Wait for it to become responsive
        for ($i = 0; $i < 10; $i++) {
            usleep(500_000);

            if ($this->isRunning()) {
                return null;
            }
        }

        $logResult = Process::run('tail -5 /tmp/code-server.log 2>/dev/null');
        $logTail = trim($logResult->output());

        return 'code-server started but not responding on port '.$port.($logTail ? ".\n".$logTail : '.');
    }

    /**
     * Stop code-server. Returns null on success, or an error message on failure.
     */
    public function stop(): ?string
    {
        if (! $this->isRunning()) {
            return null;
        }

        // Try systemd first (production RPi)
        $result = Process::run('sudo systemctl stop code-server@vibecodepc 2>/dev/null');

        if (! $result->successful()) {
            // Kill the process listening on the port directly
            $this->killByPort($this->getPort());
        }

        // Wait for shutdown with polling
        for ($i = 0; $i < 6; $i++) {
            usleep(500_000);

            if (! $this->isRunning()) {
                return null;
            }
        }

        // Force kill if SIGTERM wasn't enough
        $this->killByPort($this->getPort(), force: true);

        usleep(500_000);

        return $this->isRunning() ? 'Failed to stop code-server.' : null;
    }

    /**
     * Kill process(es) listening on the given port.
     */
    private function killByPort(int $port, bool $force = false): void
    {
        $result = Process::run(sprintf(
            '/usr/sbin/lsof -iTCP:%d -sTCP:LISTEN -t 2>/dev/null || lsof -iTCP:%d -sTCP:LISTEN -t 2>/dev/null',
            $port,
            $port,
        ));

        if (! $result->successful()) {
            // Fallback: pgrep + kill. The [c] trick prevents matching the shell running this command.
            $signal = $force ? '-9' : '';
            Process::run(sprintf('pgrep -f "[c]ode-server" | xargs kill %s 2>/dev/null', $signal));

            return;
        }

        $signal = $force ? '-9' : '';
        $pids = array_filter(array_map('trim', explode("\n", trim($result->output()))));

        foreach ($pids as $pid) {
            if (ctype_digit($pid)) {
                Process::run(sprintf('kill %s %s 2>/dev/null', $signal, $pid));
            }
        }
    }

    /**
     * Restart code-server. Returns null on success, or an error message on failure.
     */
    public function restart(): ?string
    {
        $stopError = $this->stop();

        if ($stopError !== null) {
            return $stopError;
        }

        return $this->start();
    }

    /**
     * Configure the Cline AI coding extension with a provider and API key.
     *
     * Writes the provider selection and model to code-server's globalState
     * database, and attempts to store the API key via the system keyring.
     */
    public function configureCline(AiProvider $provider, string $apiKey, ?string $baseUrl = null): bool
    {
        $extensionId = 'saoudrizwan.claude-dev';

        $clineProvider = match ($provider) {
            AiProvider::Anthropic => 'anthropic',
            AiProvider::OpenAI => 'openai',
            AiProvider::OpenRouter => 'openrouter',
            AiProvider::Custom => 'openai-compatible',
            default => null,
        };

        if ($clineProvider === null) {
            return false;
        }

        $state = [
            'apiProvider' => $clineProvider,
            'apiModelId' => match ($clineProvider) {
                'anthropic' => 'claude-sonnet-4-20250514',
                'openai' => 'gpt-4o',
                'openrouter' => 'anthropic/claude-sonnet-4',
                default => '',
            },
        ];

        if ($baseUrl && $clineProvider === 'openai-compatible') {
            $state['openAiCompatibleBaseUrl'] = $baseUrl;
        }

        $globalStateOk = $this->setExtensionGlobalState($extensionId, $state);
        $secretOk = $this->setExtensionSecret($extensionId, 'apiKey', $apiKey);

        return $globalStateOk || $secretOk;
    }

    /**
     * Merge key-value pairs into an extension's globalState in code-server's state database.
     */
    public function setExtensionGlobalState(string $extensionId, array $state): bool
    {
        $stateDbPath = $this->getStateDbPath();

        if ($stateDbPath === null) {
            return false;
        }

        try {
            $pdo = new \PDO("sqlite:{$stateDbPath}");
            $pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);

            $pdo->exec('CREATE TABLE IF NOT EXISTS ItemTable (key TEXT UNIQUE ON CONFLICT REPLACE, value BLOB)');

            $key = 'memento/'.strtolower($extensionId);

            $stmt = $pdo->prepare('SELECT value FROM ItemTable WHERE key = :key');
            $stmt->execute([':key' => $key]);
            $row = $stmt->fetch(\PDO::FETCH_ASSOC);

            $existing = $row ? (json_decode($row['value'], true) ?? []) : [];
            $merged = array_merge($existing, $state);

            $stmt = $pdo->prepare('INSERT OR REPLACE INTO ItemTable (key, value) VALUES (:key, :value)');
            $stmt->execute([
                ':key' => $key,
                ':value' => json_encode($merged, JSON_UNESCAPED_SLASHES),
            ]);

            return true;
        } catch (\Exception) {
            return false;
        }
    }

    /**
     * Attempt to store a secret for an extension via the system keyring (secret-tool).
     *
     * Requires gnome-keyring + dbus on the device. Fails gracefully if unavailable.
     */
    public function setExtensionSecret(string $extensionId, string $secretKey, string $value): bool
    {
        $service = 'code-server/'.strtolower($extensionId);

        $result = Process::input($value)->run(sprintf(
            'secret-tool store --label=%s service %s account %s 2>/dev/null',
            escapeshellarg("VS Code Secret: {$extensionId}/{$secretKey}"),
            escapeshellarg($service),
            escapeshellarg($secretKey),
        ));

        return $result->successful();
    }

    /**
     * Read code-server settings (User/settings.json) as an array.
     */
    public function readSettings(): array
    {
        $result = Process::run(sprintf('cat %s 2>/dev/null', escapeshellarg($this->settingsPath)));

        return $result->successful() ? (json_decode($result->output(), true) ?? []) : [];
    }

    /**
     * Merge key-value pairs into code-server's User/settings.json.
     */
    public function mergeSettings(array $settings): bool
    {
        $current = $this->readSettings();
        $merged = array_merge($current, $settings);

        $result = Process::run(sprintf(
            'mkdir -p %s && echo %s > %s',
            escapeshellarg(dirname($this->settingsPath)),
            escapeshellarg(json_encode($merged, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)),
            escapeshellarg($this->settingsPath),
        ));

        return $result->successful();
    }

    /**
     * Get the path to code-server's globalState database.
     */
    private function getStateDbPath(): ?string
    {
        $path = dirname($this->settingsPath).'/globalStorage/state.vscdb';

        return file_exists($path) ? $path : null;
    }

    /**
     * Wrap a command in a login shell so binaries like code-server are found in PATH.
     */
    private function shell(string $command): string
    {
        return sprintf('bash -lc %s', escapeshellarg($command));
    }
}
