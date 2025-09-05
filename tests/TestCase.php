<?php

namespace Tests;

use EliteDevSquad\SidecarLaravel\Providers\SidecarServiceProvider;
use Illuminate\Contracts\Console\Kernel;
use LaraDumps\LaraDumps\LaraDumpsServiceProvider;
use Orchestra\Testbench\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        TestDatabase::up();
    }

    protected function getEnvironmentSetUp($app): void
    {
        $app['config']->set('database.default', 'testbench');
        $app['config']->set('app.key', 'base64:RygUQvaR926QuH4d5G6ZDf9ToJEEeO2p8qDSCq6emPk=');
        $app['config']->set('session.driver', 'array');

        $app['config']->set('database.connections.testbench', [
            'driver' => env('DB_DRIVER', 'sqlite'),
            'host' => env('DB_HOST', ''),
            'port' => env('DB_PORT'),
            'username' => env('DB_USERNAME'),
            'password' => env('DB_PASSWORD'),
            'database' => env('DB_DATABASE', ':memory:'),
            'prefix' => '',
        ]);
    }

    protected function getPackageProviders($app): array
    {
        return [
            SidecarServiceProvider::class,
            LaraDumpsServiceProvider::class,
        ];
    }

    public function afterApplicationCreated(callable $callback): void
    {
        parent::afterApplicationCreated($callback);

        $this->app->make(Kernel::class)
            ->registerCommand(new FakeTinkerCommand());
    }
}
