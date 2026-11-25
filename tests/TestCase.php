<?php

namespace Tests;

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use LogicException;

abstract class TestCase extends BaseTestCase
{
    public function createApplication(): Application
    {
        $app = parent::createApplication();
        $connection = $app['db']->connection();
        $database = $connection->getDatabaseName();
        $isolated = ($connection->getDriverName() === 'sqlite' && $database === ':memory:')
            || (getenv('CASHFLOW_VERIFY_MYSQL') === '1' && $connection->getDriverName() === 'mysql'
                && preg_match('/\Acashflow_stage1_test_[a-z0-9_]+\z/', $database));

        if (! $app->environment('testing') || ! $isolated) {
            throw new LogicException('Feature tests require SQLite memory or an opted-in, dedicated MySQL test schema.');
        }

        return $app;
    }
}
