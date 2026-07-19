<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('task_activity_log', function (Blueprint $table) {
            $table->id();
            $table->foreignId('task_id')->constrained('tasks')->cascadeOnDelete();
            $table->foreignId('causer_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('event', 60);
            $table->string('description', 500);
            $table->nullableMorphs('subject');
            $table->json('meta')->nullable();
            $table->timestamp('occurred_at');
            $table->timestamps();

            $table->index(['task_id', 'occurred_at']);
            $table->index('event');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('task_activity_log');
    }
};
