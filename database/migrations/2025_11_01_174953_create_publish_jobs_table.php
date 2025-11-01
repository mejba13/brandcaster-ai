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
        Schema::create('publish_jobs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('content_variant_id');
            $table->uuid('connector_id'); // ID of WebsiteConnector or SocialConnector
            $table->string('connector_type', 100); // WebsiteConnector, SocialConnector
            $table->string('idempotency_key', 255)->unique(); // Prevent duplicate publishes
            $table->timestamp('scheduled_at'); // When to publish
            $table->timestamp('published_at')->nullable(); // Actual publish time
            $table->string('status', 50)->default('pending'); // pending, processing, published, failed
            $table->jsonb('result')->nullable(); // Response from API/DB, errors
            $table->integer('attempt_count')->default(0); // Retry attempts
            $table->timestamps();

            // Foreign keys
            $table->foreign('content_variant_id')->references('id')->on('content_variants')->onDelete('cascade');

            // Indexes
            $table->index(['status', 'scheduled_at'], 'publish_jobs_status_scheduled_at_index');
            $table->index('content_variant_id', 'publish_jobs_content_variant_id_index');
            $table->unique('idempotency_key', 'publish_jobs_idempotency_key_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('publish_jobs');
    }
};
