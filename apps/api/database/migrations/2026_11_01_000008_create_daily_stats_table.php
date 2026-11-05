<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('daily_stats', function (Blueprint $table) {
            $table->id();
            $table->foreignId('monitor_id')->constrained()->cascadeOnDelete();
            $table->date('date');
            $table->unsignedInteger('total_checks')->default(0);
            $table->unsignedInteger('successful_checks')->default(0);
            $table->unsignedInteger('avg_response_ms')->nullable();
            $table->unsignedInteger('min_response_ms')->nullable();
            $table->unsignedInteger('max_response_ms')->nullable();
            $table->unsignedInteger('p95_response_ms')->nullable();
            $table->unsignedInteger('downtime_seconds')->default(0);

            $table->unique(['monitor_id', 'date']);
            $table->index('date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('daily_stats');
    }
};
