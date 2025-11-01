<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('social_connectors', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('brand_id');
            $table->string('platform', 50); // facebook, twitter, linkedin
            $table->string('account_name', 255); // Display name or handle
            $table->string('account_id', 255)->nullable(); // Platform-specific ID
            $table->text('encrypted_token'); // Encrypted access token (+ refresh if applicable)
            $table->timestamp('token_expires_at')->nullable(); // Token expiry
            $table->json('platform_settings')->nullable(); // Page ID, posting defaults
            $table->json('rate_limits')->nullable(); // Max posts per hour/day
            $table->timestamp('last_posted_at')->nullable();
            $table->boolean('active')->default(true);
            $table->timestamps();

            // Foreign keys
            $table->foreign('brand_id')->references('id')->on('brands')->onDelete('cascade');

            // Indexes
            $table->index('brand_id', 'social_connectors_brand_id_index');
            $table->index('platform', 'social_connectors_platform_index');

            // Unique constraints
            $table->unique(['brand_id', 'platform', 'account_id'], 'social_connectors_brand_platform_account_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('social_connectors');
    }
};
