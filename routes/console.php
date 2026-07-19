<?php

use App\Modules\Leaves\Services\LeaveService;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('leave:sync-balances {year? : Leave balance year. Defaults to current year.} {--employee_id= : Sync one employee only.} {--salary_grade_id= : Sync one salary grade only.}', function (): int {
    $year = (int) ($this->argument('year') ?: now()->year);
    $filters = [];

    if ((int) $this->option('employee_id') > 0) {
        $filters['employee_id'] = (int) $this->option('employee_id');
    }

    if ((int) $this->option('salary_grade_id') > 0) {
        $filters['salary_grade_id'] = (int) $this->option('salary_grade_id');
    }

    $processed = app(LeaveService::class)->syncBalancesForYear($year, $filters);

    $this->info("Leave balances synced for {$year}. Processed records: {$processed}.");

    return 0;
})->purpose('Sync leave balances and earned leave credits for the selected year.');

/*
 | Leave balances live in each company's own database, so the sync has to run
 | once per tenant. Run from the central context it would hit the central
 | database, which has no `employees` table at all.
 |
 | A failing tenant must not stop the rest, so each is wrapped individually.
 */
Artisan::command('tenants:sync-leave-balances {year?}', function (): int {
    $year = $this->argument('year');
    $failed = [];

    App\Models\Company::query()->where('status', 'active')->each(function ($company) use ($year, &$failed): void {
        try {
            $company->run(fn () => Artisan::call('leave:sync-balances', array_filter(['year' => $year])));
            $this->line("  {$company->slug}: ok");
        } catch (\Throwable $e) {
            report($e);
            $failed[] = $company->slug;
            $this->warn("  {$company->slug}: {$e->getMessage()}");
        }
    });

    if ($failed !== []) {
        $this->error('Failed for: ' . implode(', ', $failed));

        return 1;
    }

    return 0;
})->purpose('Run leave:sync-balances inside every active tenant database.');

Schedule::command('tenants:sync-leave-balances')
    ->lastDayOfMonth('23:50')
    ->withoutOverlapping()
    ->onOneServer();

Schedule::command('tenants:refresh-stats')
    ->hourly()
    ->withoutOverlapping()
    ->onOneServer();
