<?php

namespace App\Jobs;

use App\Models\Topic;
use App\Services\Publishing\PublishingEngine;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Process Topic For Publishing Job
 *
 * Initiates the full workflow for a topic:
 * 1. Generate content brief
 * 2. Generate content outline
 * 3. Generate content draft
 * 4. Moderate content
 * 5. Generate platform variants
 * 6. (Optional) Auto-approve if configured
 * 7. (Optional) Schedule/publish if auto-approved
 */
class ProcessTopicForPublishingJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public Topic $topic;
    public array $options;

    /**
     * The number of times the job may be attempted.
     */
    public int $tries = 3;

    /**
     * The number of seconds to wait before retrying the job.
     */
    public int $backoff = 300; // 5 minutes

    /**
     * The number of seconds the job can run before timing out.
     */
    public int $timeout = 600; // 10 minutes

    /**
     * Create a new job instance.
     */
    public function __construct(Topic $topic, array $options = [])
    {
        $this->topic = $topic;
        $this->options = array_merge([
            'auto_approve' => false,
            'auto_publish' => false,
            'publish_at' => null,
        ], $options);
    }

    /**
     * Execute the job.
     */
    public function handle(PublishingEngine $publishingEngine): void
    {
        Log::info('Processing topic for publishing', [
            'topic_id' => $this->topic->id,
            'topic_title' => $this->topic->title,
            'options' => $this->options,
        ]);

        try {
            // Check if topic is still available
            if ($this->topic->status !== Topic::DISCOVERED && $this->topic->status !== Topic::QUEUED) {
                Log::warning('Topic is not available for processing', [
                    'topic_id' => $this->topic->id,
                    'status' => $this->topic->status,
                ]);
                return;
            }

            // Mark as queued
            $this->topic->update(['status' => Topic::QUEUED]);

            // Initiate content generation
            // Note: generateFromTopic dispatches GenerateContentBriefJob
            // which chains to other jobs automatically
            $publishingEngine->generateFromTopic(
                $this->topic,
                $this->options['auto_approve'] ?? false
            );

            Log::info('Topic processing initiated', [
                'topic_id' => $this->topic->id,
            ]);

            // If auto_publish is enabled, we'll need to listen for
            // ContentDraftApproved event to trigger publishing
            // This is handled by event listeners in production
        } catch (\Exception $e) {
            Log::error('Failed to process topic for publishing', [
                'topic_id' => $this->topic->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            // Reset topic status on failure
            $this->topic->update(['status' => Topic::DISCOVERED]);

            throw $e;
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('ProcessTopicForPublishingJob failed permanently', [
            'topic_id' => $this->topic->id,
            'error' => $exception->getMessage(),
        ]);

        // Reset topic status so it can be retried later
        $this->topic->update(['status' => Topic::DISCOVERED]);
    }

    /**
     * Get the tags that should be assigned to the job.
     */
    public function tags(): array
    {
        return [
            'topic:' . $this->topic->id,
            'brand:' . $this->topic->brand_id,
            'publishing',
        ];
    }
}
