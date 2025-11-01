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
        Schema::create('metrics', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('publish_job_id');
            $table->string('metric_type', 50); // impressions, clicks, likes, shares, comments, ctr
            $table->bigInteger('value'); // Metric value
            $table->timestamp('recorded_at'); // When metric was fetched
            $table->jsonb('metadata')->nullable(); // Additional context
            $table->timestamps();

            // Foreign keys
            $table->foreign('publish_job_id')->references('id')->on('publish_jobs')->onDelete('cascade');

            // Indexes
            $table->index('publish_job_id', 'metrics_publish_job_id_index');
            $table->index(['metric_type', 'recorded_at'], 'metrics_metric_type_recorded_at_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('metrics');
    }
};
