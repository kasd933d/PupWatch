<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('alert_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('incident_id')->nullable()->constrained()->nullOnDelete();
            // Test alerts are sent from the channel screen, without a monitor.
            $table->foreignId('monitor_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('notification_channel_id')->constrained()->cascadeOnDelete();
            $table->string('event', 16); // down | recovered | unstable | test
            $table->string('status', 16); // sent | failed
            $table->string('error', 255)->nullable();
            $table->timestamp('sent_at');

            $table->index(['monitor_id', 'sent_at']);
        });

        Schema::create('telegram_link_tokens', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('token', 32)->unique();
            $table->timestamp('expires_at');
            $table->timestamp('used_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('telegram_link_tokens');
        Schema::dropIfExists('alert_logs');
    }
};
