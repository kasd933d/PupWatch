<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('monitors', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name', 100);
            $table->string('url', 2048);
            $table->string('host', 255);
            $table->string('method', 8)->default('GET');
            $table->unsignedInteger('interval_seconds')->default(300);
            $table->unsignedInteger('timeout_seconds')->default(10);
            $table->string('expected_status', 100)->default('200-399');
            $table->string('keyword', 255)->nullable();
            $table->string('keyword_mode', 20)->nullable();
            $table->boolean('follow_redirects')->default(true);
            $table->text('custom_headers')->nullable(); // encrypted JSON cast
            $table->unsignedTinyInteger('alert_after_failures')->default(2);
            $table->boolean('is_paused')->default(false);
            $table->string('current_status', 20)->default('pending');
            $table->unsignedSmallInteger('consecutive_failures')->default(0);
            $table->timestamp('last_checked_at')->nullable();
            $table->unsignedInteger('last_response_ms')->nullable();
            $table->timestamp('next_check_at')->nullable();
            $table->timestamp('claimed_at')->nullable();
            $table->timestamps();

            $table->index(['is_paused', 'next_check_at']);
            $table->index(['user_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('monitors');
    }
};
