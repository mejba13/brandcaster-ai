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
            $table->unsignedBigInteger('impressions')->default(0);
            $table->unsignedBigInteger('clicks')->default(0);
            $table->unsignedBigInteger('likes')->default(0);
            $table->unsignedBigInteger('shares')->default(0);
            $table->unsignedBigInteger('comments')->default(0);
            $table->unsignedBigInteger('saves')->default(0);
            $table->date('metric_date'); // Date of the metrics
            $table->json('metadata')->nullable(); // Additional context
            $table->timestamps();

            // Foreign keys
            $table->foreign('publish_job_id')->references('id')->on('publish_jobs')->onDelete('cascade');

            // Indexes
            $table->index('publish_job_id', 'metrics_publish_job_id_index');
            $table->index('metric_date', 'metrics_metric_date_index');
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
