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
        Schema::create('topics', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('brand_id');
            $table->uuid('category_id');
            $table->string('title', 500); // Topic headline
            $table->text('description')->nullable(); // Brief description
            $table->jsonb('keywords'); // Related keywords
            $table->jsonb('source_urls'); // Sources (articles, tweets)
            $table->decimal('confidence_score', 5, 4)->default(0.0); // Relevance score 0-1
            $table->timestamp('trending_at'); // When topic started trending
            $table->timestamp('used_at')->nullable(); // When used for content
            $table->string('status', 50)->default('discovered'); // discovered, queued, used, expired
            $table->timestamps();

            // Foreign keys
            $table->foreign('brand_id')->references('id')->on('brands')->onDelete('cascade');
            $table->foreign('category_id')->references('id')->on('categories')->onDelete('cascade');

            // Indexes
            $table->index(['brand_id', 'status'], 'topics_brand_id_status_index');
            $table->index('category_id', 'topics_category_id_index');
            $table->index('trending_at', 'topics_trending_at_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('topics');
    }
};
