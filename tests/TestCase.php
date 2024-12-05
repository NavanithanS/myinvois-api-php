<?php

namespace Nava\MyInvois\Tests;

use Orchestra\Testbench\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function defineEnvironment($app)
    {
        $app['config']->set('myinvois.client_id', 'test_client_id');
        $app['config']->set('myinvois.client_secret', 'test_client_secret');
        // Set other necessary environment configurations
    }
}
