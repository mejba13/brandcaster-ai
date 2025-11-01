<?php

namespace App\Console\Commands;

use App\Models\Brand;
use App\Services\Publishing\PublishingEngine;
use Illuminate\Console\Command;

/**
 * Schedule Content Command
 *
 * Creates a content schedule for upcoming days by:
 * 1. Selecting high-quality topics
 * 2. Queuing content generation
 * 3. Scheduling publishing at optimal times
 */
class ScheduleContent extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'content:schedule
                            {--brand= : Brand slug to schedule content for}
                            {--days=7 : Number of days ahead to schedule}
                            {--dry-run : Show schedule without creating it}';

    /**
     * The console command description.
     */
    protected $description = 'Create content schedule for upcoming days';

    /**
     * Execute the console command.
     */
    public function handle(PublishingEngine $publishingEngine): int
    {
        $daysAhead = (int) $this->option('days');

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

        $this->info("📅 Creating {$daysAhead}-day content schedule...\n");

        foreach ($brands as $brand) {
            $this->info("Brand: {$brand->name}");

            try {
                if ($this->option('dry-run')) {
                    $postsPerDay = $brand->settings['posts_per_day'] ?? 1;
                    $totalPosts = $postsPerDay * $daysAhead;

                    $this->line("  Would schedule {$totalPosts} posts ({$postsPerDay}/day for {$daysAhead} days)");
                } else {
                    // Create the schedule
                    $schedule = $publishingEngine->scheduleContent($brand, $daysAhead);

                    $this->line("  ✅ Scheduled {$schedule->count()} posts");

                    // Show preview
                    if ($this->output->isVerbose()) {
                        $this->newLine();
                        $this->table(
                            ['Day', 'Topic', 'Publish At'],
                            collect($schedule)->take(10)->map(fn($item) => [
                                $item['day_offset'] == 0 ? 'Today' : "+{$item['day_offset']} days",
                                substr($item['topic_title'], 0, 50),
                                $item['publish_at'],
                            ])->toArray()
                        );

                        if (count($schedule) > 10) {
                            $this->line("  ... and " . (count($schedule) - 10) . " more");
                        }
                    }
                }
            } catch (\Exception $e) {
                $this->error("  ❌ Error: {$e->getMessage()}");
            }

            $this->newLine();
        }

        if (!$this->option('dry-run')) {
            $this->info('✅ Content scheduling complete!');
            $this->info('💡 Content will be generated 2 hours before scheduled publish time.');
        }

        return Command::SUCCESS;
    }
}
