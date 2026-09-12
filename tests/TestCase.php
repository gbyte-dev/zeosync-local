<?php

namespace Tests;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\DB;

abstract class TestCase extends BaseTestCase
{
    // Rebuild the full schema from database/migrations before tests, then
    // wrap each test in a transaction (rolled back after) so the DB stays clean.
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // These tests were written for SQLite :memory:, which never blocks
        // TRUNCATE on FK-referenced tables and doesn't enforce STRICT_TRANS_TABLES
        // for NOT-NULL-without-default columns. When running against MySQL, relax
        // those server-side strictness rules so the suite behaves the same way:
        //  - SET FOREIGN_KEY_CHECKS=0   => allows `truncate` on parent tables
        //  - SET SESSION sql_mode=''    => omits STRICT_TRANS_TABLES (insert fallback)
        if (config('database.default', 'mysql') === 'mysql') {
            DB::statement('SET FOREIGN_KEY_CHECKS = 0');
            DB::statement('SET SESSION sql_mode = ""');
        }
    }
}
