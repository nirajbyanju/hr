<?php

namespace Database\Seeders;

use App\Models\TaskTag;
use Illuminate\Database\Seeder;

class TaskTagSeeder extends Seeder
{
    /**
     * Seed a predictable, reusable set of 100 task tags.
     */
    public function run(): void
    {
        $colors = ['#0d6efd', '#6f42c1', '#198754', '#dc3545', '#fd7e14', '#0dcaf0', '#6c757d', '#ffc107'];

        for ($number = 1; $number <= 100; $number++) {
            TaskTag::withTrashed()->updateOrCreate(
                ['name' => sprintf('Task Tag %03d', $number)],
                [
                    'color' => $colors[($number - 1) % count($colors)],
                    'is_active' => true,
                    'deleted_at' => null,
                ]
            );
        }

        $this->command?->info('100 task tags are ready.');
    }
}
