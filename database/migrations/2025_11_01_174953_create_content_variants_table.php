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
        Schema::create('content_variants', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('content_draft_id');
            $table->string('platform', 50); // website, facebook, twitter, linkedin
            $table->string('title', 500)->nullable(); // Platform-specific title
            $table->text('content'); // Formatted content for platform
            $table->jsonb('formatting')->nullable(); // Hashtags, mentions, emojis
            $table->jsonb('metadata')->nullable(); // Platform-specific fields
            $table->timestamp('scheduled_for')->nullable(); // When to publish
            $table->string('status', 50)->default('pending'); // pending, scheduled, published, failed
            $table->timestamps();

            // Foreign keys
            $table->foreign('content_draft_id')->references('id')->on('content_drafts')->onDelete('cascade');

            // Indexes
            $table->index('content_draft_id', 'content_variants_content_draft_id_index');
            $table->index(['platform', 'status'], 'content_variants_platform_status_index');
            $table->index('scheduled_for', 'content_variants_scheduled_for_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('content_variants');
    }
};
