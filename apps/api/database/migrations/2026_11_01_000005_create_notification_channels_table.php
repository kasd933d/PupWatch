<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notification_channels', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('type', 16); // telegram | discord | email
            $table->string('name', 100);
            $table->text('config'); // encrypted JSON cast
            $table->string('status', 20)->default('active'); // active | needs_attention
            $table->timestamp('last_used_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'type']);
        });

        Schema::create('monitor_channel', function (Blueprint $table) {
            $table->id();
            $table->foreignId('monitor_id')->constrained()->cascadeOnDelete();
            $table->foreignId('notification_channel_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['monitor_id', 'notification_channel_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('monitor_channel');
        Schema::dropIfExists('notification_channels');
    }
};
