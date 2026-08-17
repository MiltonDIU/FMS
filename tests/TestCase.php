<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\DB;
use RuntimeException;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        /*
         * This machine's PHP has no SQLite driver, so the suite cannot use the
         * in-memory database phpunit.xml asks for and falls back to MySQL.
         *
         * The fallback used to name the development database outright. That put
         * every test one `RefreshDatabase` away from dropping real data, and on
         * 17 Aug 2026 it did exactly that. So the fallback now derives a
         * separate database and refuses to run if it ever resolves to the one
         * the application itself uses.
         *
         * Create it once with:
         *     mysql -e "CREATE DATABASE IF NOT EXISTS project_fms_test"
         *     DB_DATABASE=project_fms_test php artisan migrate
         */
        if (! extension_loaded('pdo_sqlite') && ! extension_loaded('sqlite3')) {
            $this->useSeparateMysqlTestDatabase();
        }

        // Wrap each test so nothing it writes survives.
        DB::beginTransaction();
    }

    protected function tearDown(): void
    {
        DB::rollBack();

        parent::tearDown();
    }

    /**
     * Point the suite at a test-only MySQL database, never the live one.
     */
    protected function useSeparateMysqlTestDatabase(): void
    {
        $live = (string) env('DB_DATABASE', '');
        $test = (string) env('TEST_DB_DATABASE', $live !== '' ? $live . '_test' : 'testing');

        if ($test === '' || $test === $live) {
            throw new RuntimeException(
                "Refusing to run tests against \"{$test}\": that is the application's own database. "
                . 'Set TEST_DB_DATABASE to a separate database.'
            );
        }

        config([
            'database.default' => 'mysql',
            'database.connections.mysql.database' => $test,
        ]);

        DB::purge('mysql');
        DB::reconnect('mysql');
    }
}
