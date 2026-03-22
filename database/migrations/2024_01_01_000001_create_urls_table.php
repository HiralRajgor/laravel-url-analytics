<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('urls', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')
                ->nullable()
                ->constrained()
                ->nullOnDelete();

            $table->string('original_url', 2048);
            $table->string('short_code', 64)->unique();
            $table->string('title', 255)->nullable();
            $table->string('custom_slug', 64)->nullable()->index();

            $table->unsignedBigInteger('click_count')->default(0);

            $table->boolean('is_active')->default(true)->index();
            $table->timestamp('expires_at')->nullable()->index();

            $table->timestamps();

            // Composite index for the most common admin query: user's active URLs
            $table->index(['user_id', 'is_active', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('urls');
    }
};
