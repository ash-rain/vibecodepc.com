<?php

declare(strict_types=1);

use App\Services\CodeServer\CodeServerService;
use Illuminate\Support\Facades\Process;

it('checks if code-server is installed', function () {
    Process::fake([
        'which code-server' => Process::result(output: '/usr/bin/code-server'),
    ]);

    $service = new CodeServerService;

    expect($service->isInstalled())->toBeTrue();
});

it('reports not installed when which fails', function () {
    Process::fake([
        'which code-server' => Process::result(exitCode: 1),
    ]);

    $service = new CodeServerService;

    expect($service->isInstalled())->toBeFalse();
});

it('checks if code-server is running', function () {
    Process::fake([
        'systemctl is-active code-server@vibecodepc' => Process::result(output: 'active'),
    ]);

    $service = new CodeServerService;

    expect($service->isRunning())->toBeTrue();
});

it('gets code-server version', function () {
    Process::fake([
        'code-server --version' => Process::result(output: "4.96.4\n1234567\nwith Code 1.96.4"),
    ]);

    $service = new CodeServerService;

    expect($service->getVersion())->toBe('4.96.4');
});

it('returns null version when not installed', function () {
    Process::fake([
        'code-server --version' => Process::result(exitCode: 1),
    ]);

    $service = new CodeServerService;

    expect($service->getVersion())->toBeNull();
});

it('returns the correct url', function () {
    $service = new CodeServerService(port: 9000);

    expect($service->getUrl())->toBe('http://localhost:9000');
});

it('installs extensions', function () {
    Process::fake([
        'code-server --install-extension *' => Process::result(),
    ]);

    $service = new CodeServerService;
    $result = $service->installExtensions(['GitHub.copilot', 'bradlc.vscode-tailwindcss']);

    expect($result)->toBeTrue();
});

it('restarts code-server', function () {
    Process::fake([
        'sudo systemctl restart code-server@vibecodepc' => Process::result(),
    ]);

    $service = new CodeServerService;

    expect($service->restart())->toBeTrue();
});
