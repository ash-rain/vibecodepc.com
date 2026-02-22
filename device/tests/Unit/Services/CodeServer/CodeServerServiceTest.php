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
        ->and($service->getUrl())->toBe('http://localhost:8080?tkn=secret123');

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
    $lsofCalls = 0;
    Process::fake(function ($process) use (&$lsofCalls) {
        if (str_contains($process->command, 'lsof')) {
            $lsofCalls++;

            return $lsofCalls > 1
                ? Process::result(output: '12345')
                : Process::result(exitCode: 1);
        }
        if (str_contains($process->command, 'code-server --version')) {
            return Process::result(output: '4.108.2');
        }
        if (str_contains($process->command, 'systemctl start')) {
            return Process::result();
        }

        return Process::result();
    });

    $service = new CodeServerService(port: 8443);

    expect($service->start())->toBeNull();
});

it('starts code-server directly when systemd fails', function () {
    $lsofCalls = 0;
    Process::fake(function ($process) use (&$lsofCalls) {
        if (str_contains($process->command, 'lsof')) {
            $lsofCalls++;

            return $lsofCalls > 1
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

        return Process::result();
    });

    $service = new CodeServerService(port: 8443);

    expect($service->start())->toBeNull();
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

it('stops code-server', function () {
    $lsofCalls = 0;
    Process::fake(function ($process) use (&$lsofCalls) {
        if (str_contains($process->command, 'lsof')) {
            $lsofCalls++;

            return $lsofCalls > 1
                ? Process::result(exitCode: 1)
                : Process::result(output: '12345');
        }
        if (str_contains($process->command, 'systemctl stop')) {
            return Process::result();
        }

        return Process::result();
    });

    $service = new CodeServerService(port: 8443);

    expect($service->stop())->toBeNull();
});
