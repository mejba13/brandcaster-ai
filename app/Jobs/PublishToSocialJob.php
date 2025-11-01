<?php

namespace App\Jobs;

use App\Models\ContentVariant;
use App\Models\PublishJob;
use App\Models\SocialConnector;
use App\Services\Social\Contracts\SocialPublisherInterface;
use App\Services\Social\FacebookPublisher;
use App\Services\Social\LinkedInPublisher;
use App\Services\Social\TwitterPublisher;
use Exception;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Publish To Social Job
 *
 * Handles publishing content variants to social media platforms.
 * Includes rate limiting, token refresh, and retry logic.
 */
class PublishToSocialJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The number of times the job may be attempted.
     *
     * @var int
     */
    public $tries = 3;

    /**
     * The number of seconds to wait before retrying the job.
     *
     * @var int
     */
    public $backoff = 60;

    /**
     * The content variant to publish.
     *
     * @var ContentVariant
     */
    protected ContentVariant $variant;

    /**
     * The social connector to use for publishing.
     *
     * @var SocialConnector
     */
    protected SocialConnector $connector;

    /**
     * The publish job record.
     *
     * @var PublishJob|null
     */
    protected ?PublishJob $publishJob = null;

    /**
     * Create a new job instance.
     *
     * @param ContentVariant $variant
     * @param SocialConnector $connector
     */
    public function __construct(ContentVariant $variant, SocialConnector $connector)
    {
        $this->variant = $variant;
        $this->connector = $connector;
    }

    /**
     * Execute the job.
     *
     * @return void
     * @throws Exception
     */
    public function handle(): void
    {
        Log::info('Starting social media publishing job', [
            'variant_id' => $this->variant->id,
            'connector_id' => $this->connector->id,
            'platform' => $this->connector->platform,
            'attempt' => $this->attempts(),
        ]);

        try {
            // Create or get publish job record
            $this->publishJob = $this->getOrCreatePublishJob();

            // Increment attempt count
            $this->publishJob->incrementAttempts();

            // Verify connector is active
            if (!$this->connector->active) {
                throw new Exception('Social connector is not active');
            }

            // Get appropriate publisher for the platform
            $publisher = $this->getPublisher($this->connector->platform);

            // Check if token needs refresh
            if ($this->connector->isTokenExpired()) {
                Log::info('Token expired, attempting refresh', [
                    'connector_id' => $this->connector->id,
                    'platform' => $this->connector->platform,
                ]);

                $publisher->refreshToken($this->connector);

                // Reload connector to get fresh token
                $this->connector->refresh();
            }

            // Check rate limits
            if (!$publisher->canPost($this->connector)) {
                Log::warning('Rate limit reached, requeueing job', [
                    'connector_id' => $this->connector->id,
                    'platform' => $this->connector->platform,
                ]);

                // Requeue the job for later (15 minutes)
                $this->release(900);
                return;
            }

            // Mark publish job as processing
            $this->publishJob->markAsProcessing();

            // Publish the content
            Log::info('Publishing content to social media', [
                'variant_id' => $this->variant->id,
                'connector_id' => $this->connector->id,
                'platform' => $this->connector->platform,
            ]);

            $result = $publisher->publish($this->variant, $this->connector);

            // Mark publish job as published
            $this->publishJob->markAsPublished($result);

            // Update variant status
            $this->variant->update(['status' => ContentVariant::STATUS_PUBLISHED]);

            Log::info('Successfully published content to social media', [
                'variant_id' => $this->variant->id,
                'connector_id' => $this->connector->id,
                'platform' => $this->connector->platform,
                'post_id' => $result['post_id'] ?? null,
                'url' => $result['url'] ?? null,
            ]);

            // Schedule metrics fetching job for 1 hour later
            FetchSocialMetricsJob::dispatch($this->publishJob)
                ->delay(now()->addHour());
        } catch (Exception $e) {
            Log::error('Failed to publish content to social media', [
                'variant_id' => $this->variant->id,
                'connector_id' => $this->connector->id,
                'platform' => $this->connector->platform,
                'attempt' => $this->attempts(),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            // Mark as failed if we've exhausted retries
            if ($this->attempts() >= $this->tries) {
                if ($this->publishJob) {
                    $this->publishJob->markAsFailed([
                        'error' => $e->getMessage(),
                        'attempts' => $this->attempts(),
                        'failed_at' => now()->toIso8601String(),
                    ]);
                }

                $this->variant->update(['status' => ContentVariant::STATUS_FAILED]);

                Log::critical('Publishing job exhausted all retries', [
                    'variant_id' => $this->variant->id,
                    'connector_id' => $this->connector->id,
                    'platform' => $this->connector->platform,
                    'attempts' => $this->attempts(),
                ]);
            }

            // Re-throw to trigger retry
            throw $e;
        }
    }

    /**
     * Handle a job failure.
     *
     * @param Exception $exception
     * @return void
     */
    public function failed(Exception $exception): void
    {
        Log::error('Publishing job failed permanently', [
            'variant_id' => $this->variant->id,
            'connector_id' => $this->connector->id,
            'platform' => $this->connector->platform,
            'error' => $exception->getMessage(),
            'trace' => $exception->getTraceAsString(),
        ]);

        if ($this->publishJob) {
            $this->publishJob->markAsFailed([
                'error' => $exception->getMessage(),
                'attempts' => $this->attempts(),
                'failed_at' => now()->toIso8601String(),
            ]);
        }

        $this->variant->update(['status' => ContentVariant::STATUS_FAILED]);
    }

    /**
     * Get or create a publish job record.
     *
     * @return PublishJob
     */
    protected function getOrCreatePublishJob(): PublishJob
    {
        return DB::transaction(function () {
            // Generate idempotency key
            $idempotencyKey = md5($this->variant->id . $this->connector->id . $this->connector->platform);

            return PublishJob::firstOrCreate(
                [
                    'idempotency_key' => $idempotencyKey,
                ],
                [
                    'content_variant_id' => $this->variant->id,
                    'connector_id' => $this->connector->id,
                    'connector_type' => SocialConnector::class,
                    'scheduled_at' => $this->variant->scheduled_for ?? now(),
                    'status' => PublishJob::STATUS_PENDING,
                    'attempt_count' => 0,
                ]
            );
        });
    }

    /**
     * Get the appropriate publisher for the platform.
     *
     * @param string $platform
     * @return SocialPublisherInterface
     * @throws Exception
     */
    protected function getPublisher(string $platform): SocialPublisherInterface
    {
        return match ($platform) {
            SocialConnector::PLATFORM_FACEBOOK => new FacebookPublisher(),
            SocialConnector::PLATFORM_TWITTER => new TwitterPublisher(),
            SocialConnector::PLATFORM_LINKEDIN => new LinkedInPublisher(),
            default => throw new Exception("Unsupported platform: {$platform}"),
        };
    }

    /**
     * Get the tags for the job.
     *
     * @return array
     */
    public function tags(): array
    {
        return [
            'publishing',
            'social',
            "platform:{$this->connector->platform}",
            "variant:{$this->variant->id}",
            "connector:{$this->connector->id}",
        ];
    }
}
