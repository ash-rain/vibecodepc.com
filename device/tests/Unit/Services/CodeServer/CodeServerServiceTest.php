<?php

declare(strict_types=1);

use App\Services\CodeServer\CodeServerService;
use Illuminate\Support\Facades\Process;

it('checks if code-server is installed', function () {
    Process::fake([
        'code-server --version*' => Process::result(output: '4.96.4 abc123 with Code 1.96.4'),
    ]);

    $service = new CodeServerService;

    expect($service->isInstalled())->toBeTrue();
});

it('reports not installed when version command fails', function () {
    Process::fake([
        'code-server --version*' => Process::result(exitCode: 1),
    ]);

    $service = new CodeServerService;

    expect($service->isInstalled())->toBeFalse();
});

it('checks if code-server is running', function () {
    Process::fake([
        '*' => Process::result(output: '12345'),
    ]);

    $service = new CodeServerService;

    expect($service->isRunning())->toBeTrue();
});

it('gets code-server version', function () {
    Process::fake([
        'code-server --version*' => Process::result(output: '4.96.4 abc123 with Code 1.96.4'),
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
        'code-server --version*' => Process::result(output: $output),
    ]);

    $service = new CodeServerService;

    expect($service->getVersion())->toBe('4.108.2 3c0b449c6e6e37b44a8a7938c0d8a3049926a64c with Code 1.108.2');
});

it('returns null version when not installed', function () {
    Process::fake([
        'code-server --version*' => Process::result(exitCode: 1),
    ]);

    $service = new CodeServerService;

    expect($service->getVersion())->toBeNull();
});

it('returns the correct url', function () {
    $service = new CodeServerService(port: 9000);

    expect($service->getUrl())->toBe('http://localhost:9000');
});

it('installs extensions and returns empty array on success', function () {
    Process::fake([
        'code-server --install-extension *' => Process::result(),
    ]);

    $service = new CodeServerService;
    $result = $service->installExtensions(['bradlc.vscode-tailwindcss', 'dbaeumer.vscode-eslint']);

    expect($result)->toBe([]);
});

it('returns failed extensions', function () {
    Process::fake([
        'code-server --install-extension \'bradlc.vscode-tailwindcss\'*' => Process::result(),
        'code-server --install-extension \'some.missing\'*' => Process::result(exitCode: 1, output: "Extension 'some.missing' not found."),
    ]);

    $service = new CodeServerService;
    $result = $service->installExtensions(['bradlc.vscode-tailwindcss', 'some.missing']);

    expect($result)->toBe(['some.missing']);
});

it('restarts code-server', function () {
    Process::fake([
        '*' => Process::result(),
    ]);

    $service = new CodeServerService;

    expect($service->restart())->toBeTrue();
});
