<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Attendance policies: named sets of grace periods and overtime rate that
 * define how lateness, early departure and overtime are treated.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attendance_policies', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->text('description')->nullable();
            $table->unsignedSmallInteger('late_arrival_grace_minutes')->default(0);
            $table->unsignedSmallInteger('early_departure_grace_minutes')->default(0);
            $table->decimal('overtime_rate_per_hour', 10, 2)->default(0);
            $table->string('status', 20)->default('active'); // active | inactive
            $table->timestamps();

            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attendance_policies');
    }
};
