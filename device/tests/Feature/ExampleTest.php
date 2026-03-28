<?php

use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('the application redirects to wizard when pairing is optional', function () {
    config(['vibecodepc.pairing.required' => false]);

    $response = $this->get('/');

    $response->assertRedirect('/wizard');
});

test('the application redirects to pairing when pairing is required', function () {
    config(['vibecodepc.pairing.required' => true]);

    $response = $this->get('/');

    $response->assertRedirect('/pairing');
});
