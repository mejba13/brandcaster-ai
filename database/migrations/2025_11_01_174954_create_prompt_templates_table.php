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
        Schema::create('prompt_templates', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('brand_id')->nullable(); // NULL = global template
            $table->string('name', 255); // Template name
            $table->string('type', 50); // brief, outline, draft, variant
            $table->string('platform', 50)->nullable(); // website, facebook, twitter, linkedin
            $table->text('template'); // Prompt with variables {brand_voice}, {topic}, etc.
            $table->integer('version')->default(1); // Version number
            $table->boolean('active')->default(true);
            $table->timestamps();

            // Foreign keys
            $table->foreign('brand_id')->references('id')->on('brands')->onDelete('cascade');

            // Indexes
            $table->index(['brand_id', 'type', 'platform'], 'prompt_templates_brand_id_type_platform_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('prompt_templates');
    }
};
