<?php

use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('the application redirects to pairing by default', function () {
    $response = $this->get('/');

    $response->assertRedirect('/pairing');
});
