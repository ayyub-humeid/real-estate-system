<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Foundation\Testing\RefreshDatabaseState;
use RuntimeException;

abstract class TestCase extends BaseTestCase
{
    public function createApplication()
    {
        $app = parent::createApplication();

        $connection = $app['config']->get('database.default');
        $database = $app['config']->get("database.connections.{$connection}.database");

        $allowedTestDatabases = [
            'mysql' => 'realState_test',
            'pgsql' => 'realstate_pg_test',
        ];

        if (($allowedTestDatabases[$connection] ?? null) !== $database) {
            throw new RuntimeException(
                "Tests may only run against the configured MySQL or PostgreSQL test database; the configured connection is [{$connection}] and database is [{$database}]."
            );
        }

        if (! $app['db']->connection($connection)->getSchemaBuilder()->hasTable('migrations')) {
            throw new RuntimeException(
                "The [{$database}] test database is not migrated. Run the matching test migration command before running tests."
            );
        }

        // The test database is rebuilt explicitly before PHPUnit. Laravel's
        // normal MySQL db:wipe path drops the migrations repository and is not
        // safe for this application's mixed historical migration set. Keep
        // RefreshDatabase transaction isolation without running db:wipe inside
        // the PHPUnit lifecycle.
        RefreshDatabaseState::$migrated = true;

        return $app;
    }
}
