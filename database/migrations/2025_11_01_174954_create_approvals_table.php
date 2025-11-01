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
        Schema::create('approvals', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('content_draft_id');
            $table->unsignedBigInteger('user_id'); // Reviewer
            $table->string('status', 50); // approved, rejected, changes_requested
            $table->text('comments')->nullable(); // Review notes
            $table->json('changes')->nullable(); // Suggested edits
            $table->timestamp('reviewed_at'); // When reviewed
            $table->timestamps();

            // Foreign keys
            $table->foreign('content_draft_id')->references('id')->on('content_drafts')->onDelete('cascade');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');

            // Indexes
            $table->index('content_draft_id', 'approvals_content_draft_id_index');
            $table->index('user_id', 'approvals_user_id_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('approvals');
    }
};
