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
        Schema::create('website_connectors', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('brand_id');
            $table->string('name', 255);
            $table->string('driver', 50); // mysql, pgsql, sqlsrv
            $table->text('encrypted_credentials'); // Encrypted JSON of host, port, db, user, pass
            $table->string('table_name', 255); // Target table (e.g., wp_posts)
            $table->json('field_mapping'); // Map app fields to DB columns
            $table->json('status_workflow')->nullable(); // Map statuses (draft=0, published=1)
            $table->string('slug_policy', 50)->default('auto_generate'); // auto_generate, manual
            $table->string('timezone', 50)->default('UTC');
            $table->timestamp('last_tested_at')->nullable(); // Last successful connection test
            $table->boolean('active')->default(true);
            $table->timestamps();

            // Foreign keys
            $table->foreign('brand_id')->references('id')->on('brands')->onDelete('cascade');

            // Indexes
            $table->index('brand_id', 'website_connectors_brand_id_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('website_connectors');
    }
};
