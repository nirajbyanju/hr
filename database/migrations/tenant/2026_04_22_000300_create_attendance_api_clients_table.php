<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attendance_api_clients', function (Blueprint $table) {
            $table->id();
            $table->string('name', 120);
            $table->string('token_hash', 64)->unique();
            $table->boolean('is_active')->default(true);
            $table->text('allowed_ips')->nullable();
            $table->timestamp('last_used_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['is_active', 'name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attendance_api_clients');
    }
};
