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
         * The suite runs on MySQL, against a copy of the development data.
         *
         * Not SQLite: the tests read real records (a published teacher, a
         * paper with authors) rather than building them, several migrations
         * are MySQL-only (a user-variable backfill, ENUM changes on
         * teacher_versions), and an empty in-memory database answered every
         * test with "no such table". It used to be chosen only when SQLite was
         * missing; once SQLite was installed the suite quietly moved onto an
         * empty database and 203 of 220 tests failed.
         *
         * The database is a separate one, never the application's own. Naming
         * the development database outright put every test one
         * `RefreshDatabase` away from dropping real data, and on 17 Aug 2026 it
         * did exactly that — so it is derived, and the suite refuses to run if
         * it ever resolves to the live name.
         *
         * Create or refresh it with:  composer test:db
         */
        $this->useSeparateMysqlTestDatabase();

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
