<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ClearAllTables extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'db:clear-all {--tables= : Comma-separated list of tables to operate on (overrides auto-discovery)} {--exclude= : Comma-separated list of tables to exclude} {--force : Force without confirmation}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Truncate all database tables and reset their auto-increment to 1 (excludes migrations by default)';

    public function handle()
    {
        $defaultDriver = config('database.default');
        $driver = config("database.connections.{$defaultDriver}.driver");

        $excludeOption = $this->option('exclude') ?: '';
        $excludes = array_filter(array_map('trim', explode(',', $excludeOption)));
        $excludes[] = 'migrations';  $excludes[] = 'images';  $excludes[] = 'notification_settings';
        $excludes[] = 'admins';  $excludes[] = 'plans';  $excludes[] = 'shops'; 
        $excludes[] = 'admin_settings'; $excludes[] = 'amazon_schemas';
        $excludes[] = 'amazon_products';
        $excludes[] = 'categories';

        $excludes = array_unique($excludes);

        if (!$this->option('force')) {
            $this->info('This will truncate all tables in the current database and reset AUTO_INCREMENT to 1.');
            if (!$this->confirm('Do you really wish to continue?')) {
                $this->warn('Aborted.');
                return 1;
            }
        }

        $tablesOption = $this->option('tables') ?: '';
        if (!empty($tablesOption)) {
            $tables = array_filter(array_map('trim', explode(',', $tablesOption)));
            if (empty($tables)) {
                $this->error('No valid table names provided in --tables option.');
                return 1;
            }
        } else {
            try {
                $connection = Schema::getConnection();

                if (method_exists($connection, 'getDoctrineSchemaManager')) {
                    $tables = $connection->getDoctrineSchemaManager()->listTableNames();
                } else {
                    if ($driver === 'mysql') {
                        $rows = DB::select('SHOW TABLES');
                        $tables = [];
                        foreach ($rows as $row) {
                            $tables[] = array_values((array) $row)[0];
                        }
                    } elseif ($driver === 'sqlite') {
                        $rows = DB::select("SELECT name FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%'");
                        $tables = [];
                        foreach ($rows as $row) {
                            $tables[] = $row->name;
                        }
                    } elseif ($driver === 'pgsql') {
                        $rows = DB::select("SELECT tablename FROM pg_tables WHERE schemaname='public'");
                        $tables = [];
                        foreach ($rows as $row) {
                            $tables[] = $row->tablename;
                        }
                    } else {
                        throw new \RuntimeException('Unsupported driver for auto table discovery: ' . $driver);
                    }
                }
            } catch (\Throwable $e) {
                $this->error('Failed to list tables: ' . $e->getMessage());
                return 1;
            }
        }

        if (empty($tables)) {
            $this->info('No tables found.');
            return 0;
        }

        $this->output->progressStart(count($tables));

        // Disable foreign key checks where applicable
        try {
            if ($driver === 'mysql') {
                DB::statement('SET FOREIGN_KEY_CHECKS=0;');
            } elseif ($driver === 'sqlite') {
                DB::statement('PRAGMA foreign_keys = OFF;');
            } elseif ($driver === 'pgsql') {
                DB::statement('SET session_replication_role = replica;');
            }
        } catch (\Throwable $e) {
            // ignore if driver doesn't support
        }

        $skipped = [];
        $failed = [];

        foreach ($tables as $table) {
            $this->output->progressAdvance();

            if (in_array($table, $excludes, true)) {
                $skipped[] = $table;
                continue;
            }

            try {
                DB::table($table)->truncate();
            } catch (\Throwable $e) {
                // fallback to raw truncate for drivers that need it
                try {
                    DB::statement("TRUNCATE TABLE \"{$table}\" CASCADE;");
                } catch (\Throwable $e2) {
                    $failed[$table] = $e2->getMessage();
                }
            }
        }

        // Re-enable foreign key checks
        try {
            if ($driver === 'mysql') {
                DB::statement('SET FOREIGN_KEY_CHECKS=1;');
            } elseif ($driver === 'sqlite') {
                DB::statement('PRAGMA foreign_keys = ON;');
            } elseif ($driver === 'pgsql') {
                DB::statement('SET session_replication_role = DEFAULT;');
            }
        } catch (\Throwable $e) {
            // ignore
        }

        $this->output->progressFinish();

        $this->info('Done.');

        if (!empty($skipped)) {
            $this->line('Skipped: ' . implode(', ', $skipped));
        }

        if (!empty($failed)) {
            $this->error('Failed to truncate some tables:');
            foreach ($failed as $t => $msg) {
                $this->line(" - {$t}: {$msg}");
            }
            return 1;
        }

        return 0;
    }
}
