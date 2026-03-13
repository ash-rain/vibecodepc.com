<?php

declare(strict_types=1);

use App\Services\CodeServer\CodeServerService;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;

it('checks if code-server is installed', function () {
    Process::fake([
        'bash -lc*code-server --version*' => Process::result(output: '4.96.4'),
    ]);

    $service = new CodeServerService;

    expect($service->isInstalled())->toBeTrue();
});

it('reports not installed when version command fails', function () {
    Process::fake([
        'bash -lc*code-server --version*' => Process::result(exitCode: 1),
    ]);

    $service = new CodeServerService;

    expect($service->isInstalled())->toBeFalse();
});

it('checks if code-server is running', function () {
    Process::fake(function ($process) {
        if (str_contains($process->command, 'lsof')) {
            return Process::result(output: '12345');
        }

        return Process::result();
    });

    $service = new CodeServerService(port: 8443);

    expect($service->isRunning())->toBeTrue();
});

it('gets code-server version', function () {
    Process::fake([
        'bash -lc*code-server --version*' => Process::result(output: '4.96.4 abc123 with Code 1.96.4'),
    ]);

    $service = new CodeServerService;

    expect($service->getVersion())->toBe('4.96.4 abc123 with Code 1.96.4');
});

it('parses version from output with debug lines', function () {
    $output = <<<'EOL'
        [2026-02-22T03:14:44.483Z] debug parsed command line {"args":{"version":true}}
        [2026-02-22T03:14:44.492Z] debug parsed config {"args":{"bind-addr":"127.0.0.1:8080"}}
        4.108.2 3c0b449c6e6e37b44a8a7938c0d8a3049926a64c with Code 1.108.2
        [2026-02-22T03:14:44.496Z] debug parent:82600 disposing {}
        EOL;

    Process::fake([
        'bash -lc*code-server --version*' => Process::result(output: $output),
    ]);

    $service = new CodeServerService;

    expect($service->getVersion())->toBe('4.108.2 3c0b449c6e6e37b44a8a7938c0d8a3049926a64c with Code 1.108.2');
});

it('returns null version when not installed', function () {
    Process::fake([
        'bash -lc*code-server --version*' => Process::result(exitCode: 1),
    ]);

    $service = new CodeServerService;

    expect($service->getVersion())->toBeNull();
});

it('returns url without token when no config', function () {
    Process::fake([
        'cat*' => Process::result(exitCode: 1),
    ]);

    $service = new CodeServerService(port: 9000, configPath: '/nonexistent/config.yaml');

    expect($service->getUrl())->toBe('http://localhost:9000');
});

it('auto-detects port and password from config file', function () {
    $configPath = storage_path('app/test-code-server/config.yaml');
    File::ensureDirectoryExists(dirname($configPath));
    File::put($configPath, "bind-addr: 127.0.0.1:8080\nauth: password\npassword: secret123\ncert: false\n");

    $service = new CodeServerService(configPath: $configPath);

    expect($service->getPort())->toBe(8080)
        ->and($service->getPassword())->toBe('secret123')
        ->and($service->getUrl())->toBe('http://localhost:8080');

    File::deleteDirectory(dirname($configPath));
});

it('falls back to 8443 when config file is missing', function () {
    Process::fake([
        'cat*' => Process::result(exitCode: 1),
    ]);

    $service = new CodeServerService(configPath: '/nonexistent/config.yaml');

    expect($service->getPort())->toBe(8443);
});

it('installs extensions and returns empty array on success', function () {
    Process::fake([
        'bash -lc*code-server --install-extension*' => Process::result(),
    ]);

    $service = new CodeServerService;
    $result = $service->installExtensions(['bradlc.vscode-tailwindcss', 'dbaeumer.vscode-eslint']);

    expect($result)->toBe([]);
});

it('returns failed extensions', function () {
    Process::fake([
        '*bradlc.vscode-tailwindcss*' => Process::result(),
        '*some.missing*' => Process::result(exitCode: 1, output: "Extension 'some.missing' not found."),
    ]);

    $service = new CodeServerService;
    $result = $service->installExtensions(['bradlc.vscode-tailwindcss', 'some.missing']);

    expect($result)->toBe(['some.missing']);
});

it('starts code-server via systemd', function () {
    $configPath = storage_path('app/test-code-server-systemd/config.yaml');
    File::ensureDirectoryExists(dirname($configPath));
    File::put($configPath, "bind-addr: 127.0.0.1:8443\nauth: none\ncert: false\n");

    $lsofCalls = 0;
    Process::fake(function ($process) use (&$lsofCalls, $configPath) {
        if (str_contains($process->command, 'lsof')) {
            $lsofCalls++;

            return $lsofCalls > 1
                ? Process::result(output: '12345')
                : Process::result(exitCode: 1);
        }
        if (str_contains($process->command, 'code-server --version')) {
            return Process::result(output: '4.108.2');
        }
        if (str_contains($process->command, 'cat') && str_contains($process->command, 'config.yaml')) {
            return Process::result(output: File::get($configPath));
        }
        if (str_contains($process->command, 'systemctl start')) {
            return Process::result();
        }

        return Process::result();
    });

    $service = new CodeServerService(port: 8443, configPath: $configPath);

    expect($service->start())->toBeNull();

    File::deleteDirectory(dirname($configPath));
});

it('starts code-server directly when systemd fails', function () {
    $configPath = storage_path('app/test-code-server-direct/config.yaml');
    File::ensureDirectoryExists(dirname($configPath));
    File::put($configPath, "bind-addr: 127.0.0.1:8443\nauth: none\ncert: false\n");

    $lsofCalls = 0;
    Process::fake(function ($process) use (&$lsofCalls, $configPath) {
        if (str_contains($process->command, 'lsof')) {
            $lsofCalls++;

            return $lsofCalls > 1
                ? Process::result(output: '12345')
                : Process::result(exitCode: 1);
        }
        if (str_contains($process->command, 'code-server --version')) {
            return Process::result(output: '4.108.2');
        }
        if (str_contains($process->command, 'cat') && str_contains($process->command, 'config.yaml')) {
            return Process::result(output: File::get($configPath));
        }
        if (str_contains($process->command, 'systemctl start')) {
            return Process::result(exitCode: 1);
        }
        if (str_contains($process->command, 'nohup') && str_contains($process->command, 'code-server')) {
            return Process::result(output: '12345');
        }

        return Process::result();
    });

    $service = new CodeServerService(port: 8443, configPath: $configPath);

    expect($service->start())->toBeNull();

    File::deleteDirectory(dirname($configPath));
});

it('returns error when code-server is not installed', function () {
    Process::fake(function ($process) {
        if (str_contains($process->command, 'lsof')) {
            return Process::result(exitCode: 1);
        }
        if (str_contains($process->command, 'code-server --version')) {
            return Process::result(exitCode: 1);
        }

        return Process::result();
    });

    $service = new CodeServerService(port: 8443);

    expect($service->start())->toBe('code-server is not installed.');
});

it('disables auth in config file', function () {
    $configPath = storage_path('app/test-code-server/config.yaml');
    File::ensureDirectoryExists(dirname($configPath));
    File::put($configPath, "bind-addr: 127.0.0.1:8080\nauth: password\npassword: secret123\ncert: false\n");

    $service = new CodeServerService(configPath: $configPath);

    expect($service->disableAuth())->toBeTrue();
    expect(File::get($configPath))->toContain('auth: none')
        ->not->toContain('auth: password');

    File::deleteDirectory(dirname($configPath));
});

it('skips disableAuth when already set to none', function () {
    $configPath = storage_path('app/test-code-server/config.yaml');
    File::ensureDirectoryExists(dirname($configPath));
    File::put($configPath, "bind-addr: 127.0.0.1:8080\nauth: none\npassword: secret123\ncert: false\n");

    $service = new CodeServerService(configPath: $configPath);

    expect($service->disableAuth())->toBeTrue();

    File::deleteDirectory(dirname($configPath));
});

it('returns false when config file is missing for disableAuth', function () {
    Process::fake([
        'cat*' => Process::result(exitCode: 1),
    ]);

    $service = new CodeServerService(configPath: '/nonexistent/config.yaml');

    expect($service->disableAuth())->toBeFalse();
});

it('calls disableAuth and passes --auth none when starting directly', function () {
    $lsofCalls = 0;
    $commands = [];
    Process::fake(function ($process) use (&$lsofCalls, &$commands) {
        $commands[] = $process->command;

        if (str_contains($process->command, 'lsof')) {
            $lsofCalls++;

            return $lsofCalls > 2
                ? Process::result(output: '12345')
                : Process::result(exitCode: 1);
        }
        if (str_contains($process->command, 'code-server --version')) {
            return Process::result(output: '4.108.2');
        }
        if (str_contains($process->command, 'systemctl start')) {
            return Process::result(exitCode: 1);
        }
        if (str_contains($process->command, 'nohup') && str_contains($process->command, 'code-server')) {
            return Process::result(output: '12345');
        }
        if (str_contains($process->command, 'cat') && str_contains($process->command, 'config.yaml')) {
            return Process::result(output: "bind-addr: 127.0.0.1:8443\nauth: password\npassword: test\ncert: false\n");
        }

        return Process::result();
    });

    $configPath = storage_path('app/test-code-server-start/config.yaml');
    File::ensureDirectoryExists(dirname($configPath));
    File::put($configPath, "bind-addr: 127.0.0.1:8443\nauth: password\npassword: test\ncert: false\n");

    $service = new CodeServerService(port: 8443, configPath: $configPath);

    expect($service->start())->toBeNull();

    // Verify auth was disabled in config
    expect(File::get($configPath))->toContain('auth: none');

    // Verify --auth none was passed in the direct launch command
    $nohupCommand = collect($commands)->first(fn ($cmd) => str_contains($cmd, 'nohup'));
    expect($nohupCommand)->toContain('--auth none');

    File::deleteDirectory(dirname($configPath));
});

it('stops code-server via systemd', function () {
    $lsofCalls = 0;
    Process::fake(function ($process) use (&$lsofCalls) {
        if (str_contains($process->command, 'lsof')) {
            $lsofCalls++;

            // First call: isRunning() before stop — running
            // Second call: isRunning() in poll loop — stopped
            return $lsofCalls <= 1
                ? Process::result(output: '12345')
                : Process::result(exitCode: 1);
        }
        if (str_contains($process->command, 'systemctl stop')) {
            return Process::result();
        }

        return Process::result();
    });

    $service = new CodeServerService(port: 8443);

    expect($service->stop())->toBeNull();
});

it('stops code-server by killing port process when systemd fails', function () {
    $lsofCalls = 0;
    Process::fake(function ($process) use (&$lsofCalls) {
        if (str_contains($process->command, 'lsof')) {
            $lsofCalls++;

            // First call: isRunning() — running
            // Second call: killByPort() finds PID
            // Third call: isRunning() in poll — stopped
            return $lsofCalls <= 2
                ? Process::result(output: '12345')
                : Process::result(exitCode: 1);
        }
        if (str_contains($process->command, 'systemctl stop')) {
            return Process::result(exitCode: 1);
        }
        if (str_contains($process->command, 'kill')) {
            return Process::result();
        }

        return Process::result();
    });

    $service = new CodeServerService(port: 8443);

    expect($service->stop())->toBeNull();
});

// Edge case tests for config write failures

it('returns false when setTheme mkdir/echo fails', function () {
    Process::fake([
        'cat*' => Process::result(exitCode: 1),
        'mkdir*' => Process::result(exitCode: 1),
    ]);

    $service = new CodeServerService(settingsPath: '/test/settings.json');

    expect($service->setTheme('Dark+'))->toBeFalse();
});

it('returns false when setPassword sed command fails', function () {
    Process::fake([
        'sed*' => Process::result(exitCode: 1),
    ]);

    $service = new CodeServerService(configPath: '/test/config.yaml');

    expect($service->setPassword('newpassword'))->toBeFalse();
});

it('returns false when mergeSettings mkdir/echo fails', function () {
    Process::fake([
        'cat*' => Process::result(output: '{}'),
        'mkdir*' => Process::result(exitCode: 1),
    ]);

    $service = new CodeServerService(settingsPath: '/test/settings.json');

    expect($service->mergeSettings(['editor.fontSize' => 14]))->toBeFalse();
});

// Edge case tests for port conflicts

it('returns error when systemd starts but port does not respond', function () {
    $configPath = storage_path('app/test-code-server-port-fail/config.yaml');
    File::ensureDirectoryExists(dirname($configPath));
    File::put($configPath, "bind-addr: 127.0.0.1:8443\nauth: none\ncert: false\n");

    Process::fake(function ($process) use ($configPath) {
        if (str_contains($process->command, 'lsof')) {
            // Always return exit code 1 - port never responds
            return Process::result(exitCode: 1);
        }
        if (str_contains($process->command, 'code-server --version')) {
            return Process::result(output: '4.108.2');
        }
        if (str_contains($process->command, 'cat') && str_contains($process->command, 'config.yaml')) {
            return Process::result(output: File::get($configPath));
        }
        if (str_contains($process->command, 'systemctl start')) {
            // Systemd reports success but port doesn't respond
            return Process::result();
        }

        return Process::result();
    });

    $service = new CodeServerService(port: 8443, configPath: $configPath);
    $result = $service->start();

    expect($result)->toContain('Service started but code-server is not responding');

    File::deleteDirectory(dirname($configPath));
});

it('returns error when direct launch fails due to port conflict', function () {
    $configPath = storage_path('app/test-code-server-port-conflict/config.yaml');
    File::ensureDirectoryExists(dirname($configPath));
    File::put($configPath, "bind-addr: 127.0.0.1:8443\nauth: none\ncert: false\n");

    Process::fake(function ($process) use ($configPath) {
        if (str_contains($process->command, 'lsof')) {
            // Port check always fails - nothing running
            return Process::result(exitCode: 1);
        }
        if (str_contains($process->command, 'code-server --version')) {
            return Process::result(output: '4.108.2');
        }
        if (str_contains($process->command, 'cat') && str_contains($process->command, 'config.yaml')) {
            return Process::result(output: File::get($configPath));
        }
        if (str_contains($process->command, 'systemctl start')) {
            return Process::result(exitCode: 1);
        }
        if (str_contains($process->command, 'nohup') && str_contains($process->command, 'code-server')) {
            // Direct launch fails (e.g., port already in use)
            return Process::result(exitCode: 1, errorOutput: 'bind: address already in use');
        }

        return Process::result();
    });

    $service = new CodeServerService(port: 8443, configPath: $configPath);
    $result = $service->start();

    expect($result)->toContain('Failed to start code-server');
    expect($result)->toContain('bind: address already in use');

    File::deleteDirectory(dirname($configPath));
});

it('returns error when code-server starts but never becomes responsive', function () {
    $configPath = storage_path('app/test-code-server-unresponsive/config.yaml');
    File::ensureDirectoryExists(dirname($configPath));
    File::put($configPath, "bind-addr: 127.0.0.1:8443\nauth: none\ncert: false\n");

    Process::fake(function ($process) use ($configPath) {
        if (str_contains($process->command, 'lsof')) {
            // Always return exit code 1 - service never responds
            return Process::result(exitCode: 1);
        }
        if (str_contains($process->command, 'code-server --version')) {
            return Process::result(output: '4.108.2');
        }
        if (str_contains($process->command, 'cat') && str_contains($process->command, 'config.yaml')) {
            return Process::result(output: File::get($configPath));
        }
        if (str_contains($process->command, 'systemctl start')) {
            return Process::result(exitCode: 1);
        }
        if (str_contains($process->command, 'nohup') && str_contains($process->command, 'code-server')) {
            // Launch succeeds but service doesn't respond
            return Process::result(output: '12345');
        }
        if (str_contains($process->command, 'tail')) {
            return Process::result(output: '[ERROR] main: listen tcp 127.0.0.1:8443: bind: address already in use');
        }

        return Process::result();
    });

    $service = new CodeServerService(port: 8443, configPath: $configPath);
    $result = $service->start();

    expect($result)->toContain('code-server started but not responding on port 8443');
    expect($result)->toContain('address already in use');

    File::deleteDirectory(dirname($configPath));
});

// Edge case tests for permission errors

it('handles config file with extra whitespace in auth line', function () {
    $configPath = storage_path('app/test-code-server-auth-whitespace/config.yaml');
    File::ensureDirectoryExists(dirname($configPath));
    // Config with extra spaces around auth
    File::put($configPath, "bind-addr: 127.0.0.1:8080\nauth:   password  \npassword: secret123\ncert: false\n");

    $service = new CodeServerService(configPath: $configPath);

    $result = $service->disableAuth();

    // The regex should handle extra whitespace and replace auth value
    expect($result)->toBeTrue();
    expect(File::get($configPath))->toContain('auth: none');

    File::deleteDirectory(dirname($configPath));
});

it('handles permission errors when reading settings file', function () {
    $settingsPath = storage_path('app/test-code-server-settings/settings.json');
    File::ensureDirectoryExists(dirname($settingsPath));
    File::put($settingsPath, '{"editor.fontSize": 14}');

    // Make file unreadable
    chmod($settingsPath, 0000);

    Process::fake([
        sprintf('cat %s 2>/dev/null', escapeshellarg($settingsPath)) => Process::result(exitCode: 1),
    ]);

    $service = new CodeServerService(settingsPath: $settingsPath);

    // Should return empty array when file cannot be read
    expect($service->readSettings())->toBe([]);

    // Restore permissions for cleanup
    chmod($settingsPath, 0644);
    File::deleteDirectory(dirname($settingsPath));
});

it('returns empty settings array when settings file contains invalid JSON', function () {
    $settingsPath = storage_path('app/test-code-server-bad-json/settings.json');
    File::ensureDirectoryExists(dirname($settingsPath));
    File::put($settingsPath, '{invalid json content}');

    Process::fake([
        sprintf('cat %s 2>/dev/null', escapeshellarg($settingsPath)) => Process::result(
            output: '{invalid json content}'
        ),
    ]);

    $service = new CodeServerService(settingsPath: $settingsPath);

    // Should return empty array when JSON is invalid
    expect($service->readSettings())->toBe([]);

    File::deleteDirectory(dirname($settingsPath));
});

it('returns empty array when listExtensions times out', function () {
    Process::fake([
        'bash -lc*code-server --list-extensions*' => Process::result(exitCode: 1),
    ]);

    $service = new CodeServerService;

    expect($service->listExtensions())->toBe([]);
});

it('handles concurrent extension install failures gracefully', function () {
    Process::fake([
        '*bradlc.vscode-tailwindcss*' => Process::result(exitCode: 1, output: 'connect ECONNREFUSED'),
        '*dbaeumer.vscode-eslint*' => Process::result(exitCode: 1, output: 'connect ECONNREFUSED'),
        '*ms-vscode.vscode-typescript*' => Process::result(exitCode: 1, output: 'connect ECONNREFUSED'),
    ]);

    $service = new CodeServerService;
    $extensions = ['bradlc.vscode-tailwindcss', 'dbaeumer.vscode-eslint', 'ms-vscode.vscode-typescript'];
    $result = $service->installExtensions($extensions);

    // All extensions should be reported as failed
    expect($result)->toHaveCount(3);
    expect($result)->toContain('bradlc.vscode-tailwindcss');
    expect($result)->toContain('dbaeumer.vscode-eslint');
    expect($result)->toContain('ms-vscode.vscode-typescript');
});

it('handles extension uninstall when extension is not installed', function () {
    Process::fake([
        'bash -lc*code-server --uninstall-extension*' => Process::result(
            exitCode: 1,
            output: "Extension 'some.nonexistent' is not installed."
        ),
    ]);

    $service = new CodeServerService;

    expect($service->uninstallExtension('some.nonexistent'))->toBeFalse();
});

it('returns error when stop fails to kill process after multiple attempts', function () {
    Process::fake(function ($process) {
        if (str_contains($process->command, 'lsof')) {
            // Always reports process is running
            return Process::result(output: '12345');
        }
        if (str_contains($process->command, 'systemctl stop')) {
            return Process::result(exitCode: 1);
        }
        if (str_contains($process->command, 'kill')) {
            // Kill commands always fail
            return Process::result(exitCode: 1);
        }

        return Process::result();
    });

    $service = new CodeServerService(port: 8443);

    // After multiple attempts, should return error
    expect($service->stop())->toBe('Failed to stop code-server.');
});

it('handles malformed config file gracefully', function () {
    $configPath = storage_path('app/test-code-server-malformed/config.yaml');
    File::ensureDirectoryExists(dirname($configPath));
    // Write malformed config that won't match expected patterns
    File::put($configPath, "this is not\na valid config\nfile format\n");

    Process::fake([
        sprintf('cat %s 2>/dev/null', escapeshellarg($configPath)) => Process::result(
            output: "this is not\na valid config\nfile format\n"
        ),
    ]);

    $service = new CodeServerService(configPath: $configPath);

    // Should use default port when config is malformed
    expect($service->getPort())->toBe(8443);
    // Should return null password when config is malformed
    expect($service->getPassword())->toBeNull();

    File::deleteDirectory(dirname($configPath));
});

it('handles missing config file path gracefully', function () {
    Process::fake([
        'cat*' => Process::result(exitCode: 1),
    ]);

    $service = new CodeServerService(configPath: '');

    // Should handle empty config path gracefully
    expect($service->getPort())->toBe(8443);
    expect($service->getPassword())->toBeNull();
});
