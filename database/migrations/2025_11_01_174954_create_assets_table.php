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
        Schema::create('assets', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('content_draft_id')->nullable();
            $table->string('type', 50); // image, video, document
            $table->string('file_path', 500); // S3 path or URL
            $table->string('url', 500); // Public URL
            $table->json('metadata')->nullable(); // Dimensions, size, format
            $table->text('alt_text')->nullable(); // Accessibility text
            $table->timestamps();

            // Foreign keys
            $table->foreign('content_draft_id')->references('id')->on('content_drafts')->onDelete('set null');

            // Indexes
            $table->index('content_draft_id', 'assets_content_draft_id_index');
            $table->index('type', 'assets_type_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('assets');
    }
};
