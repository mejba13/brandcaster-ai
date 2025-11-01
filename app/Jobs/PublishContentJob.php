<?php

namespace App\Jobs;

use App\Models\ContentDraft;
use App\Services\Publishing\PublishingOrchestrator;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Publish Content Job
 *
 * Final step in the content pipeline - publishes approved content
 * to website and social media platforms.
 *
 * Can be scheduled for future publishing or executed immediately.
 */
class PublishContentJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public ContentDraft $draft;
    public array $options;

    /**
     * The number of times the job may be attempted.
     */
    public int $tries = 3;

    /**
     * The number of seconds to wait before retrying the job.
     */
    public int $backoff = 600; // 10 minutes

    /**
     * The number of seconds the job can run before timing out.
     */
    public int $timeout = 300; // 5 minutes

    /**
     * Create a new job instance.
     */
    public function __construct(ContentDraft $draft, array $options = [])
    {
        $this->draft = $draft;
        $this->options = array_merge([
            'publish_to_website' => true,
            'publish_to_social' => true,
            'platforms' => ['facebook', 'twitter', 'linkedin'],
        ], $options);
    }

    /**
     * Execute the job.
     */
    public function handle(PublishingOrchestrator $orchestrator): void
    {
        Log::info('Publishing content draft', [
            'draft_id' => $this->draft->id,
            'brand_id' => $this->draft->brand_id,
            'title' => $this->draft->title,
            'options' => $this->options,
        ]);

        try {
            // Check if draft is approved
            if ($this->draft->status !== ContentDraft::STATUS_APPROVED) {
                Log::warning('Attempted to publish non-approved draft', [
                    'draft_id' => $this->draft->id,
                    'status' => $this->draft->status,
                ]);
                return;
            }

            // Check if already published
            if ($this->draft->status === ContentDraft::STATUS_PUBLISHED) {
                Log::warning('Draft already published', [
                    'draft_id' => $this->draft->id,
                ]);
                return;
            }

            // Publish to all platforms
            $result = $orchestrator->publish($this->draft, $this->options);

            if ($result['success']) {
                Log::info('Content published successfully', [
                    'draft_id' => $this->draft->id,
                    'results' => $result,
                ]);

                // Mark topic as used if exists
                if ($this->draft->topic) {
                    $this->draft->topic->markAsUsed();
                }

                // Schedule metrics collection
                FetchSocialMetricsJob::dispatch($this->draft)
                    ->delay(now()->addHour());
            } else {
                Log::warning('Content published with some failures', [
                    'draft_id' => $this->draft->id,
                    'errors' => $result['errors'],
                ]);

                // If complete failure, throw exception to trigger retry
                if (empty($result['website']['success'] ?? false) &&
                    collect($result['social'] ?? [])->every(fn($r) => !$r['success'])) {
                    throw new \Exception('All publishing attempts failed: ' . implode(', ', $result['errors']));
                }
            }
        } catch (\Exception $e) {
            Log::error('Content publishing failed', [
                'draft_id' => $this->draft->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            throw $e;
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('PublishContentJob failed permanently', [
            'draft_id' => $this->draft->id,
            'brand_id' => $this->draft->brand_id,
            'title' => $this->draft->title,
            'error' => $exception->getMessage(),
        ]);

        // Update draft with failure information
        $this->draft->update([
            'metadata' => array_merge($this->draft->metadata ?? [], [
                'publish_error' => $exception->getMessage(),
                'publish_failed_at' => now()->toDateTimeString(),
            ]),
        ]);

        // Notify brand admins about the failure
        // This would be handled by event listeners in production
    }

    /**
     * Get the tags that should be assigned to the job.
     */
    public function tags(): array
    {
        return [
            'draft:' . $this->draft->id,
            'brand:' . $this->draft->brand_id,
            'publishing',
        ];
    }

    /**
     * Determine the time at which the job should timeout.
     */
    public function retryUntil(): \DateTime
    {
        // Keep trying for 24 hours
        return now()->addDay();
    }
}
