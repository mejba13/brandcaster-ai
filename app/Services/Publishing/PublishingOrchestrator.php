<?php

namespace App\Services\Publishing;

use App\Models\Brand;
use App\Models\ContentDraft;
use App\Models\ContentVariant;
use App\Models\PublishJob;
use App\Models\SocialConnector;
use App\Models\WebsiteConnector;
use App\Services\DatabaseConnector\DatabaseConnectorService;
use App\Services\Social\FacebookPublisher;
use App\Services\Social\LinkedInPublisher;
use App\Services\Social\TwitterPublisher;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Publishing Orchestrator
 *
 * Handles the actual publishing of content to multiple platforms:
 * - Website (via DatabaseConnector)
 * - Social media platforms (Facebook, Twitter, LinkedIn)
 *
 * Manages PublishJob records and handles failures gracefully.
 */
class PublishingOrchestrator
{
    protected DatabaseConnectorService $dbConnector;
    protected FacebookPublisher $facebookPublisher;
    protected TwitterPublisher $twitterPublisher;
    protected LinkedInPublisher $linkedInPublisher;

    public function __construct(
        DatabaseConnectorService $dbConnector,
        FacebookPublisher $facebookPublisher,
        TwitterPublisher $twitterPublisher,
        LinkedInPublisher $linkedInPublisher
    ) {
        $this->dbConnector = $dbConnector;
        $this->facebookPublisher = $facebookPublisher;
        $this->twitterPublisher = $twitterPublisher;
        $this->linkedInPublisher = $linkedInPublisher;
    }

    /**
     * Publish a content draft to all configured platforms
     *
     * @param ContentDraft $draft
     * @param array $options
     * @return array Results
     */
    public function publish(ContentDraft $draft, array $options = []): array
    {
        $options = array_merge([
            'publish_to_website' => true,
            'publish_to_social' => true,
            'platforms' => ['facebook', 'twitter', 'linkedin'],
        ], $options);

        Log::info('Starting content publishing', [
            'draft_id' => $draft->id,
            'brand_id' => $draft->brand_id,
            'options' => $options,
        ]);

        $results = [
            'success' => true,
            'website' => null,
            'social' => [],
            'errors' => [],
        ];

        DB::beginTransaction();

        try {
            // Publish to website
            if ($options['publish_to_website']) {
                $results['website'] = $this->publishToWebsite($draft);

                if (!$results['website']['success']) {
                    $results['success'] = false;
                    $results['errors'][] = $results['website']['error'] ?? 'Website publish failed';
                }
            }

            // Publish to social media
            if ($options['publish_to_social']) {
                $results['social'] = $this->publishToSocial($draft, $options['platforms']);

                // Check if all social publishes failed
                $allSocialFailed = !empty($results['social']) &&
                    collect($results['social'])->every(fn($r) => !$r['success']);

                if ($allSocialFailed) {
                    $results['success'] = false;
                }
            }

            // Update draft status
            if ($results['success']) {
                $draft->update([
                    'status' => ContentDraft::STATUS_PUBLISHED,
                    'published_at' => now(),
                ]);
            }

            DB::commit();

            Log::info('Content publishing completed', [
                'draft_id' => $draft->id,
                'results' => $results,
            ]);
        } catch (\Exception $e) {
            DB::rollBack();

            $results['success'] = false;
            $results['errors'][] = $e->getMessage();

            Log::error('Content publishing failed', [
                'draft_id' => $draft->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
        }

        return $results;
    }

    /**
     * Publish content to website database
     *
     * @param ContentDraft $draft
     * @return array
     */
    protected function publishToWebsite(ContentDraft $draft): array
    {
        $brand = $draft->brand;

        // Get website connector for this brand
        $connector = WebsiteConnector::where('brand_id', $brand->id)
            ->where('active', true)
            ->first();

        if (!$connector) {
            return [
                'success' => false,
                'error' => 'No active website connector configured',
            ];
        }

        // Get the website variant
        $variant = ContentVariant::where('content_draft_id', $draft->id)
            ->where('platform', 'website')
            ->first();

        if (!$variant) {
            return [
                'success' => false,
                'error' => 'No website variant found',
            ];
        }

        // Create PublishJob record
        $publishJob = PublishJob::create([
            'content_draft_id' => $draft->id,
            'content_variant_id' => $variant->id,
            'platform' => 'website',
            'connector_id' => $connector->id,
            'status' => PublishJob::STATUS_PROCESSING,
            'scheduled_at' => now(),
        ]);

        try {
            // Prepare content data
            $contentData = [
                'title' => $draft->title,
                'body' => $variant->content,
                'excerpt' => $variant->metadata['excerpt'] ?? null,
                'meta_description' => $draft->seo_metadata['meta_description'] ?? null,
                'meta_keywords' => $draft->seo_metadata['keywords'] ?? [],
                'slug' => $draft->seo_metadata['slug'] ?? null,
                'author_id' => $connector->field_mapping['author_id'] ?? null,
                'category_id' => $connector->field_mapping['category_id'] ?? null,
                'tags' => $draft->keywords ?? [],
            ];

            // Publish to database
            $result = $this->dbConnector->publish($connector, $contentData);

            if ($result['success']) {
                $publishJob->update([
                    'status' => PublishJob::STATUS_PUBLISHED,
                    'published_at' => now(),
                    'result' => $result,
                    'external_id' => $result['inserted_id'] ?? null,
                ]);

                return [
                    'success' => true,
                    'publish_job_id' => $publishJob->id,
                    'external_id' => $result['inserted_id'] ?? null,
                ];
            } else {
                throw new \Exception($result['error'] ?? 'Database publish failed');
            }
        } catch (\Exception $e) {
            $publishJob->update([
                'status' => PublishJob::STATUS_FAILED,
                'error_message' => $e->getMessage(),
            ]);

            Log::error('Website publish failed', [
                'draft_id' => $draft->id,
                'connector_id' => $connector->id,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
                'publish_job_id' => $publishJob->id,
            ];
        }
    }

    /**
     * Publish content to social media platforms
     *
     * @param ContentDraft $draft
     * @param array $platforms
     * @return array Results per platform
     */
    protected function publishToSocial(ContentDraft $draft, array $platforms): array
    {
        $results = [];

        foreach ($platforms as $platform) {
            try {
                $result = $this->publishToPlatform($draft, $platform);
                $results[$platform] = $result;
            } catch (\Exception $e) {
                $results[$platform] = [
                    'success' => false,
                    'error' => $e->getMessage(),
                ];

                Log::error('Social publish failed', [
                    'draft_id' => $draft->id,
                    'platform' => $platform,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $results;
    }

    /**
     * Publish to a specific social media platform
     *
     * @param ContentDraft $draft
     * @param string $platform
     * @return array
     */
    protected function publishToPlatform(ContentDraft $draft, string $platform): array
    {
        $brand = $draft->brand;

        // Get connector for this platform
        $connector = SocialConnector::where('brand_id', $brand->id)
            ->where('platform', $platform)
            ->where('active', true)
            ->first();

        if (!$connector) {
            return [
                'success' => false,
                'error' => "No active {$platform} connector configured",
            ];
        }

        // Get the variant for this platform
        $variant = ContentVariant::where('content_draft_id', $draft->id)
            ->where('platform', $platform)
            ->first();

        if (!$variant) {
            return [
                'success' => false,
                'error' => "No {$platform} variant found",
            ];
        }

        // Create PublishJob record
        $publishJob = PublishJob::create([
            'content_draft_id' => $draft->id,
            'content_variant_id' => $variant->id,
            'platform' => $platform,
            'connector_id' => $connector->id,
            'status' => PublishJob::STATUS_PROCESSING,
            'scheduled_at' => now(),
        ]);

        try {
            // Get the appropriate publisher
            $publisher = $this->getPublisher($platform);

            if (!$publisher) {
                throw new \Exception("Publisher not found for platform: {$platform}");
            }

            // Check rate limits
            if (!$publisher->canPost($connector)) {
                throw new \Exception("Rate limit exceeded for {$platform}");
            }

            // Refresh token if needed
            if ($connector->isTokenExpiringSoon()) {
                $publisher->refreshToken($connector);
            }

            // Publish
            $result = $publisher->publish($variant, $connector);

            $publishJob->update([
                'status' => PublishJob::STATUS_PUBLISHED,
                'published_at' => now(),
                'result' => $result,
                'external_id' => $result['post_id'] ?? null,
            ]);

            return [
                'success' => true,
                'publish_job_id' => $publishJob->id,
                'post_id' => $result['post_id'] ?? null,
                'url' => $result['url'] ?? null,
            ];
        } catch (\Exception $e) {
            $publishJob->update([
                'status' => PublishJob::STATUS_FAILED,
                'error_message' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
                'publish_job_id' => $publishJob->id,
            ];
        }
    }

    /**
     * Get publisher instance for platform
     *
     * @param string $platform
     * @return FacebookPublisher|TwitterPublisher|LinkedInPublisher|null
     */
    protected function getPublisher(string $platform)
    {
        return match ($platform) {
            'facebook' => $this->facebookPublisher,
            'twitter' => $this->twitterPublisher,
            'linkedin' => $this->linkedInPublisher,
            default => null,
        };
    }

    /**
     * Retry failed publish job
     *
     * @param PublishJob $publishJob
     * @return array
     */
    public function retryPublish(PublishJob $publishJob): array
    {
        if ($publishJob->status !== PublishJob::STATUS_FAILED) {
            return [
                'success' => false,
                'error' => 'Only failed jobs can be retried',
            ];
        }

        $draft = $publishJob->contentDraft;
        $platform = $publishJob->platform;

        Log::info('Retrying failed publish job', [
            'publish_job_id' => $publishJob->id,
            'draft_id' => $draft->id,
            'platform' => $platform,
        ]);

        // Reset status
        $publishJob->update([
            'status' => PublishJob::STATUS_PROCESSING,
            'error_message' => null,
        ]);

        try {
            if ($platform === 'website') {
                $result = $this->publishToWebsite($draft);
            } else {
                $result = $this->publishToPlatform($draft, $platform);
            }

            return $result;
        } catch (\Exception $e) {
            $publishJob->update([
                'status' => PublishJob::STATUS_FAILED,
                'error_message' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Cancel a scheduled publish job
     *
     * @param PublishJob $publishJob
     * @return bool
     */
    public function cancelPublish(PublishJob $publishJob): bool
    {
        if ($publishJob->status !== PublishJob::STATUS_PENDING) {
            return false;
        }

        $publishJob->update([
            'status' => PublishJob::STATUS_CANCELLED,
        ]);

        Log::info('Cancelled scheduled publish job', [
            'publish_job_id' => $publishJob->id,
        ]);

        return true;
    }

    /**
     * Get publishing statistics
     *
     * @param Brand $brand
     * @param int $days
     * @return array
     */
    public function getPublishingStats(Brand $brand, int $days = 30): array
    {
        $since = now()->subDays($days);

        $stats = [
            'total_published' => 0,
            'by_platform' => [],
            'success_rate' => 0,
            'failed_jobs' => 0,
        ];

        $jobs = PublishJob::whereHas('contentDraft', function ($query) use ($brand) {
            $query->where('brand_id', $brand->id);
        })
            ->where('created_at', '>=', $since)
            ->get();

        $stats['total_published'] = $jobs->where('status', PublishJob::STATUS_PUBLISHED)->count();
        $stats['failed_jobs'] = $jobs->where('status', PublishJob::STATUS_FAILED)->count();

        // By platform
        $platforms = ['website', 'facebook', 'twitter', 'linkedin'];
        foreach ($platforms as $platform) {
            $platformJobs = $jobs->where('platform', $platform);
            $published = $platformJobs->where('status', PublishJob::STATUS_PUBLISHED)->count();
            $failed = $platformJobs->where('status', PublishJob::STATUS_FAILED)->count();

            $stats['by_platform'][$platform] = [
                'published' => $published,
                'failed' => $failed,
                'success_rate' => $platformJobs->count() > 0
                    ? round(($published / $platformJobs->count()) * 100, 2)
                    : 0,
            ];
        }

        // Overall success rate
        $total = $jobs->count();
        $stats['success_rate'] = $total > 0
            ? round(($stats['total_published'] / $total) * 100, 2)
            : 0;

        return $stats;
    }

    /**
     * Validate publishing configuration for a brand
     *
     * @param Brand $brand
     * @return array Validation results
     */
    public function validateConfiguration(Brand $brand): array
    {
        $issues = [];

        // Check website connector
        $websiteConnector = WebsiteConnector::where('brand_id', $brand->id)
            ->where('active', true)
            ->first();

        if (!$websiteConnector) {
            $issues[] = [
                'type' => 'error',
                'category' => 'website',
                'message' => 'No active website connector configured',
            ];
        } elseif (!$websiteConnector->last_tested_at) {
            $issues[] = [
                'type' => 'warning',
                'category' => 'website',
                'message' => 'Website connector has not been tested',
            ];
        }

        // Check social connectors
        $socialPlatforms = ['facebook', 'twitter', 'linkedin'];
        foreach ($socialPlatforms as $platform) {
            $connector = SocialConnector::where('brand_id', $brand->id)
                ->where('platform', $platform)
                ->where('active', true)
                ->first();

            if (!$connector) {
                $issues[] = [
                    'type' => 'warning',
                    'category' => 'social',
                    'message' => ucfirst($platform) . ' not connected',
                ];
            } elseif ($connector->isTokenExpired()) {
                $issues[] = [
                    'type' => 'error',
                    'category' => 'social',
                    'message' => ucfirst($platform) . ' token expired - re-authentication required',
                ];
            }
        }

        return [
            'valid' => empty(collect($issues)->where('type', 'error')->toArray()),
            'issues' => $issues,
        ];
    }
}
