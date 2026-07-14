<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        if (!extension_loaded('pdo_sqlite') && !extension_loaded('sqlite3')) {
            config([
                'database.default' => 'mysql',
                'database.connections.mysql.database' => 'project_fms',
            ]);
            \Illuminate\Support\Facades\DB::purge('mysql');
            \Illuminate\Support\Facades\DB::reconnect('mysql');
        }

        // Begin transaction to prevent test data from persisting in MySQL
        \Illuminate\Support\Facades\DB::beginTransaction();
    }

    protected function tearDown(): void
    {
        // Rollback transaction to clean database
        \Illuminate\Support\Facades\DB::rollBack();

        parent::tearDown();
    }
}
