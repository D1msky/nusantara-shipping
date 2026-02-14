<?php

namespace Nusantara\Tests;

use Nusantara\NusantaraServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    protected function setUp(): void
    {
        parent::setUp();
    }

    protected function getPackageProviders($app): array
    {
        return [NusantaraServiceProvider::class];
    }

    protected function getPackageAliases($app): array
    {
        return [
            'Nusantara' => \Nusantara\NusantaraFacade::class,
        ];
    }

    protected function getEnvironmentSetUp($app): void
    {
        $app['config']->set('nusantara.driver', 'file');
        $app['config']->set('nusantara.cache_ttl', 0);
        $dataPath = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'data';
        $app['config']->set('nusantara.data_path', is_dir($dataPath) ? realpath($dataPath) : $dataPath);
    }
}
