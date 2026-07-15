<?php

use App\Http\Middleware\EnsureValidAttendanceApiToken;
use App\Modules\Attendance\Http\Controllers\Api\AttendanceIngestionController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function (): void {
    Route::post('/attendance/logs/bulk', [AttendanceIngestionController::class, 'bulkStore'])
        ->middleware([EnsureValidAttendanceApiToken::class, 'throttle:120,1']);
});
