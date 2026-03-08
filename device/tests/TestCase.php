<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Tests\Support\Concerns\HasTunnelFakes;

abstract class TestCase extends BaseTestCase
{
    use HasTunnelFakes;
}
