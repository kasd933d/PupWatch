<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('status_pages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('title', 100);
            $table->string('slug', 80)->unique();
            $table->text('description')->nullable();
            $table->boolean('show_response_times')->default(false);
            $table->boolean('show_error_details')->default(false);
            $table->boolean('is_indexable')->default(true);
            $table->string('accent_color', 7)->nullable();
            $table->string('logo_url', 2048)->nullable();
            $table->timestamps();
        });

        Schema::create('status_page_monitor', function (Blueprint $table) {
            $table->id();
            $table->foreignId('status_page_id')->constrained()->cascadeOnDelete();
            $table->foreignId('monitor_id')->constrained()->cascadeOnDelete();
            // PRD §4.8 — optional public display name for client-facing pages.
            $table->string('display_name', 100)->nullable();
            $table->unsignedTinyInteger('sort_order')->default(0);

            $table->unique(['status_page_id', 'monitor_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('status_page_monitor');
        Schema::dropIfExists('status_pages');
    }
};
