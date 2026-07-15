<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class PermissionSqlSeeder extends Seeder
{
    /**
     * Seed permissions from raw SQL file.
     */
    public function run(): void
    {
        $path = database_path('sql/permissions_seed.sql');
        if (! is_file($path)) {
            throw new RuntimeException("Permissions SQL file not found at: {$path}");
        }

        $sql = file_get_contents($path);
        if ($sql === false || trim($sql) === '') {
            throw new RuntimeException("Permissions SQL file is empty or unreadable: {$path}");
        }

        DB::unprepared($sql);
    }
}
