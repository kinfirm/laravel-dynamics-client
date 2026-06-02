<?php

namespace JustBetter\DynamicsClient\Tests;

use JustBetter\DynamicsClient\ServiceProvider;
use Orchestra\Testbench\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /** Ensures the property exists regardless of the resolved testbench-core/laravel pairing. */
    public static $latestResponse;

    protected function getPackageProviders($app): array
    {
        return [
            ServiceProvider::class,
        ];
    }
}
