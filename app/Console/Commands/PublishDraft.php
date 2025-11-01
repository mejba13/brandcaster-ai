<?php

namespace App\Console\Commands;

use App\Models\ContentDraft;
use App\Services\Publishing\PublishingEngine;
use Illuminate\Console\Command;

/**
 * Publish Draft Command
 *
 * Manually publish approved content drafts via CLI.
 * Useful for testing and manual intervention.
 */
class PublishDraft extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'content:publish
                            {draft_id? : ID of draft to publish}
                            {--all-approved : Publish all approved drafts}
                            {--brand= : Publish approved drafts for specific brand}
                            {--immediate : Publish immediately instead of scheduling}
                            {--website-only : Publish to website only}
                            {--social-only : Publish to social media only}
                            {--platform= : Specific platform (facebook, twitter, linkedin)}
                            {--dry-run : Show what would be published}';

    /**
     * The console command description.
     */
    protected $description = 'Publish approved content drafts';

    /**
     * Execute the console command.
     */
    public function handle(PublishingEngine $publishingEngine): int
    {
        // Get drafts to publish
        $drafts = $this->getDraftsToPublish();

        if ($drafts->isEmpty()) {
            $this->warn('No drafts to publish');
            return Command::SUCCESS;
        }

        $this->info("📤 Publishing {$drafts->count()} draft(s)...\n");

        // Build options
        $options = [
            'schedule' => !$this->option('immediate'),
            'publish_to_website' => !$this->option('social-only'),
            'publish_to_social' => !$this->option('website-only'),
        ];

        if ($platform = $this->option('platform')) {
            $options['platforms'] = [$platform];
        }

        $stats = [
            'published' => 0,
            'scheduled' => 0,
            'failed' => 0,
        ];

        foreach ($drafts as $draft) {
            $this->line("Processing: {$draft->title}");

            if ($this->option('dry-run')) {
                $this->line("  Would publish to:");
                if ($options['publish_to_website']) {
                    $this->line("    - Website");
                }
                if ($options['publish_to_social']) {
                    $platforms = $options['platforms'] ?? ['facebook', 'twitter', 'linkedin'];
                    foreach ($platforms as $platform) {
                        $this->line("    - " . ucfirst($platform));
                    }
                }
                continue;
            }

            try {
                $result = $publishingEngine->publishDraft($draft, $options);

                if ($result['success']) {
                    if ($result['scheduled'] ?? false) {
                        $this->info("  ✅ Scheduled for {$result['publish_at']}");
                        $stats['scheduled']++;
                    } else {
                        $this->info("  ✅ Published successfully");
                        $stats['published']++;
                    }
                } else {
                    $this->error("  ❌ Failed: " . ($result['error'] ?? 'Unknown error'));
                    $stats['failed']++;
                }
            } catch (\Exception $e) {
                $this->error("  ❌ Error: {$e->getMessage()}");
                $stats['failed']++;
            }

            $this->newLine();
        }

        // Summary
        if (!$this->option('dry-run')) {
            $this->info('📊 Summary:');
            $this->table(
                ['Status', 'Count'],
                [
                    ['Published', $stats['published']],
                    ['Scheduled', $stats['scheduled']],
                    ['Failed', $stats['failed']],
                ]
            );
        }

        return Command::SUCCESS;
    }

    /**
     * Get drafts to publish based on options
     */
    protected function getDraftsToPublish()
    {
        // Single draft
        if ($draftId = $this->argument('draft_id')) {
            $draft = ContentDraft::find($draftId);

            if (!$draft) {
                $this->error("Draft not found: {$draftId}");
                return collect();
            }

            if ($draft->status !== ContentDraft::APPROVED) {
                $this->error("Draft is not approved (status: {$draft->status})");
                return collect();
            }

            return collect([$draft]);
        }

        // All approved drafts
        if ($this->option('all-approved')) {
            $query = ContentDraft::where('status', ContentDraft::APPROVED);

            if ($brandSlug = $this->option('brand')) {
                $query->whereHas('brand', function ($q) use ($brandSlug) {
                    $q->where('slug', $brandSlug);
                });
            }

            return $query->get();
        }

        // Interactive selection
        $drafts = ContentDraft::where('status', ContentDraft::APPROVED)
            ->with('brand')
            ->orderBy('created_at', 'desc')
            ->limit(20)
            ->get();

        if ($drafts->isEmpty()) {
            return $drafts;
        }

        $choices = $drafts->map(function ($draft, $index) {
            return sprintf(
                "[%d] %s (%s) - %s",
                $index + 1,
                substr($draft->title, 0, 50),
                $draft->brand->name,
                $draft->created_at->diffForHumans()
            );
        })->toArray();

        $choice = $this->choice(
            'Select draft to publish',
            array_merge(['Cancel', 'Publish all'], $choices),
            0
        );

        if ($choice === 'Cancel') {
            return collect();
        }

        if ($choice === 'Publish all') {
            return $drafts;
        }

        // Extract index from choice
        preg_match('/\[(\d+)\]/', $choice, $matches);
        $index = (int) $matches[1] - 1;

        return collect([$drafts[$index]]);
    }
}
