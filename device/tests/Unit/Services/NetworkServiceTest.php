<?php

use App\Services\NetworkService;

it('getLocalIp returns a string', function () {
    $service = new NetworkService;

    $ip = $service->getLocalIp();

    expect($ip)->toBeString()
        ->and($ip)->not->toBeEmpty();
});

it('hasInternetConnectivity returns a boolean', function () {
    $service = new NetworkService;

    $result = $service->hasInternetConnectivity();

    expect($result)->toBeBool();
});

it('hasEthernet returns a boolean', function () {
    $service = new NetworkService;

    $result = $service->hasEthernet();

    expect($result)->toBeBool();
});

it('hasWifi returns a boolean', function () {
    $service = new NetworkService;

    $result = $service->hasWifi();

    expect($result)->toBeBool();
});
