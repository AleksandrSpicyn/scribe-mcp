<?php

declare(strict_types=1);

namespace AleksandrSpicyn\ScribeMcp\Tests;

use AleksandrSpicyn\ScribeMcp\ScribeMcpServiceProvider;
use Laravel\Mcp\Server\McpServiceProvider;
use Orchestra\Testbench\TestCase as OrchestraTestCase;
use Spatie\LaravelData\LaravelDataServiceProvider;

abstract class TestCase extends OrchestraTestCase
{
    protected function getPackageProviders($app): array
    {
        return [McpServiceProvider::class, LaravelDataServiceProvider::class, ScribeMcpServiceProvider::class];
    }
}
