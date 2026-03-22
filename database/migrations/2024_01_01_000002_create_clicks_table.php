<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('clicks', function (Blueprint $table) {
            $table->id();

            $table->foreignId('url_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->string('ip_address', 45);        // IPv6 max = 39, but padded
            $table->string('country_code', 2)->nullable();
            $table->string('country_name', 100)->nullable();
            $table->string('city', 100)->nullable();

            // device_type: desktop | mobile | tablet | bot
            $table->string('device_type', 20)->nullable()->index();
            $table->string('browser', 50)->nullable();
            $table->string('os', 50)->nullable();

            $table->string('referrer', 2048)->nullable();
            $table->string('referrer_host', 255)->nullable();
            $table->string('user_agent', 512)->nullable();

            $table->boolean('is_unique')->default(false)->index();

            // No updated_at — clicks are immutable append-only records
            $table->timestamp('clicked_at')->useCurrent()->index();

            // Composite indexes for the analytics queries in AnalyticsService
            $table->index(['url_id', 'clicked_at']);
            $table->index(['url_id', 'is_unique']);
            $table->index(['url_id', 'country_name']);
            $table->index(['url_id', 'device_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('clicks');
    }
};
