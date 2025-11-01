<?php

namespace App\Jobs;

use App\Models\Metric;
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
 * Fetch Social Metrics Job
 *
 * Fetches and stores engagement metrics from social media platforms
 * for published content. Runs periodically to track performance over time.
 */
class FetchSocialMetricsJob implements ShouldQueue
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
    public $backoff = 300;

    /**
     * The publish job to fetch metrics for.
     *
     * @var PublishJob
     */
    protected PublishJob $publishJob;

    /**
     * Create a new job instance.
     *
     * @param PublishJob $publishJob
     */
    public function __construct(PublishJob $publishJob)
    {
        $this->publishJob = $publishJob;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle(): void
    {
        Log::info('Starting social metrics fetch job', [
            'publish_job_id' => $this->publishJob->id,
            'variant_id' => $this->publishJob->content_variant_id,
            'connector_id' => $this->publishJob->connector_id,
            'attempt' => $this->attempts(),
        ]);

        try {
            // Verify the job was published successfully
            if ($this->publishJob->status !== PublishJob::STATUS_PUBLISHED) {
                Log::warning('Skipping metrics fetch - job not published', [
                    'publish_job_id' => $this->publishJob->id,
                    'status' => $this->publishJob->status,
                ]);
                return;
            }

            // Get the connector
            $connector = $this->publishJob->connector;

            if (!$connector) {
                throw new Exception('Connector not found for publish job');
            }

            // Verify connector is active
            if (!$connector->active) {
                Log::warning('Skipping metrics fetch - connector inactive', [
                    'publish_job_id' => $this->publishJob->id,
                    'connector_id' => $this->publishJob->connector_id,
                ]);
                return;
            }

            // Get the post ID from publish job result
            $postId = $this->publishJob->result['post_id'] ?? null;

            if (!$postId) {
                throw new Exception('Post ID not found in publish job result');
            }

            // Get appropriate publisher for the platform
            $publisher = $this->getPublisher($connector->platform);

            // Check if token needs refresh
            if ($connector->isTokenExpired()) {
                Log::info('Token expired, attempting refresh before metrics fetch', [
                    'connector_id' => $connector->id,
                    'platform' => $connector->platform,
                ]);

                $publisher->refreshToken($connector);

                // Reload connector to get fresh token
                $connector->refresh();
            }

            // Fetch metrics from platform
            Log::info('Fetching metrics from social platform', [
                'publish_job_id' => $this->publishJob->id,
                'platform' => $connector->platform,
                'post_id' => $postId,
            ]);

            $metrics = $publisher->getMetrics($postId, $connector);

            if (empty($metrics)) {
                Log::warning('No metrics returned from platform', [
                    'publish_job_id' => $this->publishJob->id,
                    'platform' => $connector->platform,
                    'post_id' => $postId,
                ]);
                return;
            }

            // Store metrics in database
            $this->storeMetrics($metrics);

            Log::info('Successfully fetched and stored social metrics', [
                'publish_job_id' => $this->publishJob->id,
                'platform' => $connector->platform,
                'post_id' => $postId,
                'metrics_count' => count($metrics),
                'metrics' => $metrics,
            ]);
        } catch (Exception $e) {
            Log::error('Failed to fetch social metrics', [
                'publish_job_id' => $this->publishJob->id,
                'connector_id' => $this->publishJob->connector_id,
                'attempt' => $this->attempts(),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            // Only log critical error if we've exhausted retries
            if ($this->attempts() >= $this->tries) {
                Log::critical('Metrics fetch job exhausted all retries', [
                    'publish_job_id' => $this->publishJob->id,
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
        Log::error('Metrics fetch job failed permanently', [
            'publish_job_id' => $this->publishJob->id,
            'connector_id' => $this->publishJob->connector_id,
            'error' => $exception->getMessage(),
            'trace' => $exception->getTraceAsString(),
        ]);
    }

    /**
     * Store metrics in the database.
     *
     * @param array $metrics
     * @return void
     */
    protected function storeMetrics(array $metrics): void
    {
        DB::transaction(function () use ($metrics) {
            $recordedAt = now();

            foreach ($metrics as $metricType => $value) {
                // Map platform-specific metric names to our standard types
                $standardType = $this->mapMetricType($metricType);

                if (!$standardType) {
                    Log::debug('Skipping unknown metric type', [
                        'metric_type' => $metricType,
                        'value' => $value,
                    ]);
                    continue;
                }

                // Create metric record
                Metric::create([
                    'publish_job_id' => $this->publishJob->id,
                    'metric_type' => $standardType,
                    'value' => (int) $value,
                    'recorded_at' => $recordedAt,
                    'metadata' => [
                        'platform' => $this->publishJob->connector->platform,
                        'original_metric_name' => $metricType,
                    ],
                ]);

                Log::debug('Created metric record', [
                    'publish_job_id' => $this->publishJob->id,
                    'metric_type' => $standardType,
                    'value' => $value,
                ]);
            }
        });
    }

    /**
     * Map platform-specific metric names to standard metric types.
     *
     * @param string $metricType
     * @return string|null
     */
    protected function mapMetricType(string $metricType): ?string
    {
        $mapping = [
            // Standard names
            'impressions' => Metric::TYPE_IMPRESSIONS,
            'clicks' => Metric::TYPE_CLICKS,
            'likes' => Metric::TYPE_LIKES,
            'shares' => Metric::TYPE_SHARES,
            'comments' => Metric::TYPE_COMMENTS,
            'reach' => Metric::TYPE_REACH,
            'engagement' => Metric::TYPE_ENGAGEMENT,
            'views' => Metric::TYPE_VIEWS,

            // Facebook-specific
            'reactions' => Metric::TYPE_LIKES,

            // Twitter-specific
            'retweets' => Metric::TYPE_SHARES,
            'replies' => Metric::TYPE_COMMENTS,
            'favorites' => Metric::TYPE_LIKES,
            'quote_tweets' => Metric::TYPE_SHARES,

            // LinkedIn-specific
            'numLikes' => Metric::TYPE_LIKES,
            'numShares' => Metric::TYPE_SHARES,
            'numComments' => Metric::TYPE_COMMENTS,
            'numViews' => Metric::TYPE_VIEWS,
        ];

        return $mapping[strtolower($metricType)] ?? null;
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
        $connector = $this->publishJob->connector;

        return [
            'metrics',
            'social',
            "platform:{$connector->platform}",
            "publish_job:{$this->publishJob->id}",
        ];
    }

    /**
     * Calculate the number of seconds to wait before retrying the job.
     *
     * @return int
     */
    public function backoff(): int
    {
        // Exponential backoff: 5 minutes, 10 minutes, 20 minutes
        return $this->backoff * pow(2, $this->attempts() - 1);
    }
}
