<?php

namespace App\Console\Commands;

use App\Models\Brand;
use App\Services\Publishing\PublishingEngine;
use Illuminate\Console\Command;

/**
 * Generate and Publish Content Command
 *
 * CLI command to trigger content generation and publishing for brands.
 * Can be run manually or scheduled via Laravel's task scheduler.
 */
class GenerateAndPublishContent extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'content:generate-and-publish
                            {--brand= : Brand slug to generate content for}
                            {--limit=1 : Number of posts to generate per brand}
                            {--category= : Specific category ID to generate for}
                            {--auto-approve : Auto-approve generated content}
                            {--schedule : Schedule content for optimal times}
                            {--immediate : Publish immediately without scheduling}
                            {--dry-run : Show what would be done without actually doing it}';

    /**
     * The console command description.
     */
    protected $description = 'Generate and publish content for brands';

    /**
     * Execute the console command.
     */
    public function handle(PublishingEngine $publishingEngine): int
    {
        if ($this->option('dry-run')) {
            $this->info('🔍 DRY RUN MODE - No content will be generated or published');
        }

        // Get brands to process
        if ($brandSlug = $this->option('brand')) {
            $brand = Brand::where('slug', $brandSlug)->first();

            if (!$brand) {
                $this->error("Brand not found: {$brandSlug}");
                return Command::FAILURE;
            }

            $brands = collect([$brand]);
        } else {
            $brands = Brand::active()->get();
        }

        if ($brands->isEmpty()) {
            $this->warn('No active brands found');
            return Command::SUCCESS;
        }

        $this->info("🚀 Processing {$brands->count()} brand(s)...\n");

        $totalStats = [
            'topics_processed' => 0,
            'content_generated' => 0,
            'scheduled' => 0,
            'published' => 0,
            'errors' => 0,
        ];

        foreach ($brands as $brand) {
            $this->info("📝 Processing: {$brand->name}");

            if ($this->option('dry-run')) {
                $this->line("  Would generate {$this->option('limit')} post(s)");
                continue;
            }

            // Build options
            $options = [
                'limit' => (int) $this->option('limit'),
                'auto_approve' => $this->option('auto-approve') ?: ($brand->settings['auto_approve'] ?? false),
                'schedule' => !$this->option('immediate'),
                'category_id' => $this->option('category'),
            ];

            try {
                // Generate content
                $stats = $publishingEngine->generateForBrand($brand, $options);

                // Update totals
                foreach ($stats as $key => $value) {
                    $totalStats[$key] += $value;
                }

                // Display stats
                $this->line("  ✅ Topics processed: {$stats['topics_processed']}");
                $this->line("  ✅ Content generated: {$stats['content_generated']}");

                if ($stats['errors'] > 0) {
                    $this->line("  ⚠️  Errors: {$stats['errors']}");
                }
            } catch (\Exception $e) {
                $this->error("  ❌ Error: {$e->getMessage()}");
                $totalStats['errors']++;
            }

            $this->newLine();
        }

        // Summary
        $this->info('📊 Summary:');
        $this->table(
            ['Metric', 'Count'],
            [
                ['Topics Processed', $totalStats['topics_processed']],
                ['Content Generated', $totalStats['content_generated']],
                ['Errors', $totalStats['errors']],
            ]
        );

        if ($this->option('schedule') && !$this->option('immediate')) {
            $this->info('⏰ Content has been queued for generation and will be scheduled for optimal posting times.');
        } elseif ($this->option('immediate')) {
            $this->info('🚀 Content has been queued for immediate publishing after generation.');
        }

        return Command::SUCCESS;
    }
}
