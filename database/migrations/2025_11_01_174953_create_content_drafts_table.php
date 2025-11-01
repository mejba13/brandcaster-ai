<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('content_drafts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('brand_id');
            $table->uuid('topic_id')->nullable(); // Source topic
            $table->uuid('created_by')->nullable(); // User who created (NULL if AI-generated)
            $table->string('title', 500); // Content title
            $table->text('strategy_brief')->nullable(); // AI-generated brief
            $table->jsonb('outline')->nullable(); // Structured outline
            $table->text('body'); // Main content (Markdown or HTML)
            $table->jsonb('seo_metadata'); // Meta desc, keywords, OG tags
            $table->string('status', 50)->default('draft'); // draft, pending_review, approved, rejected, published
            $table->timestamp('approved_at')->nullable();
            $table->uuid('approved_by')->nullable();
            $table->timestamps();
            $table->softDeletes(); // Soft delete

            // Foreign keys
            $table->foreign('brand_id')->references('id')->on('brands')->onDelete('cascade');
            $table->foreign('topic_id')->references('id')->on('topics')->onDelete('set null');
            $table->foreign('created_by')->references('id')->on('users')->onDelete('set null');
            $table->foreign('approved_by')->references('id')->on('users')->onDelete('set null');

            // Indexes
            $table->index(['brand_id', 'status'], 'content_drafts_brand_id_status_index');
            $table->index('topic_id', 'content_drafts_topic_id_index');
            $table->index('created_at', 'content_drafts_created_at_index');

            // Full-text index (PostgreSQL GIN index)
            DB::statement('CREATE INDEX content_drafts_fulltext_index ON content_drafts USING GIN (to_tsvector(\'english\', title || \' \' || body))');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('content_drafts');
    }
};
