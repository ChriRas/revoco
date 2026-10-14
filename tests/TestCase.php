<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Never read the developer's real downloaded network data; tests that need data call useNetworkDirectory().
        config(['revoco.network.path' => sys_get_temp_dir().'/revoco-network-absent']);
    }
}
