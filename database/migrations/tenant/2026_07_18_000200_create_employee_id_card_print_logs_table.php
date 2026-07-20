<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Full history of every generate / print / download / revoke event on a card,
        // so "who printed which card, when and how" is auditable.
        Schema::create('employee_id_card_print_logs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('employee_id_card_id')->constrained('employee_id_cards')->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
            $table->string('event', 20); // generated | printed | downloaded | revoked
            $table->string('format', 20)->nullable(); // html | pdf
            $table->foreignId('performed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent', 255)->nullable();
            $table->timestamps();

            $table->index(['employee_id_card_id', 'event']);
            $table->index(['employee_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_id_card_print_logs');
    }
};
