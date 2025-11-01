<?php

namespace App\Services\Publishing;

use App\Jobs\GenerateContentBriefJob;
use App\Jobs\ProcessTopicForPublishingJob;
use App\Jobs\PublishContentJob;
use App\Models\Brand;
use App\Models\Category;
use App\Models\ContentDraft;
use App\Models\Topic;
use App\Services\TopicDiscovery\TopicDiscoveryService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Publishing Engine
 *
 * Orchestrates the entire content generation and publishing pipeline.
 * Manages workflow from topic discovery through to multi-platform publishing.
 */
class PublishingEngine
{
    protected TopicDiscoveryService $topicDiscovery;
    protected ContentScheduler $scheduler;
    protected PublishingOrchestrator $orchestrator;

    public function __construct(
        TopicDiscoveryService $topicDiscovery,
        ContentScheduler $scheduler,
        PublishingOrchestrator $orchestrator
    ) {
        $this->topicDiscovery = $topicDiscovery;
        $this->scheduler = $scheduler;
        $this->orchestrator = $orchestrator;
    }

    /**
     * Generate and publish content for a brand
     *
     * @param Brand $brand
     * @param array $options
     * @return array Statistics
     */
    public function generateForBrand(Brand $brand, array $options = []): array
    {
        $options = array_merge([
            'limit' => $brand->settings['posts_per_day'] ?? 1,
            'auto_approve' => $brand->settings['auto_approve'] ?? false,
            'schedule' => true,
            'category_id' => null,
        ], $options);

        Log::info('Starting content generation for brand', [
            'brand_id' => $brand->id,
            'options' => $options,
        ]);

        $stats = [
            'topics_processed' => 0,
            'content_generated' => 0,
            'scheduled' => 0,
            'published' => 0,
            'errors' => 0,
        ];

        // Get available topics
        $topics = $this->getAvailableTopics($brand, $options);

        if ($topics->isEmpty()) {
            Log::info('No available topics for brand', ['brand_id' => $brand->id]);
            return $stats;
        }

        // Take only the limit
        $topics = $topics->take($options['limit']);

        foreach ($topics as $topic) {
            try {
                // Mark topic as queued
                $topic->update(['status' => Topic::QUEUED]);

                // Dispatch content generation workflow
                GenerateContentBriefJob::dispatch($topic);

                $stats['topics_processed']++;
                $stats['content_generated']++;

                Log::info('Dispatched content generation for topic', [
                    'topic_id' => $topic->id,
                    'topic_title' => $topic->title,
                ]);
            } catch (\Exception $e) {
                $stats['errors']++;
                Log::error('Failed to process topic', [
                    'topic_id' => $topic->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $stats;
    }

    /**
     * Generate content from a specific topic
     *
     * @param Topic $topic
     * @param bool $autoApprove
     * @return ContentDraft|null
     */
    public function generateFromTopic(Topic $topic, bool $autoApprove = false): ?ContentDraft
    {
        if ($topic->status === Topic::USED) {
            throw new \InvalidArgumentException('Topic has already been used');
        }

        // Mark as queued
        $topic->update(['status' => Topic::QUEUED]);

        // Dispatch the workflow
        GenerateContentBriefJob::dispatch($topic);

        Log::info('Initiated content generation from topic', [
            'topic_id' => $topic->id,
            'auto_approve' => $autoApprove,
        ]);

        // Return null since generation is async
        // The calling code should listen for the ContentDraftCreated event
        return null;
    }

    /**
     * Publish approved content
     *
     * @param ContentDraft $draft
     * @param array $options
     * @return array Results
     */
    public function publishDraft(ContentDraft $draft, array $options = []): array
    {
        if ($draft->status !== ContentDraft::APPROVED) {
            throw new \InvalidArgumentException('Draft must be approved before publishing');
        }

        $options = array_merge([
            'schedule' => true,
            'publish_to_website' => true,
            'publish_to_social' => true,
            'platforms' => ['facebook', 'twitter', 'linkedin'],
            'publish_at' => null,
        ], $options);

        Log::info('Publishing content draft', [
            'draft_id' => $draft->id,
            'options' => $options,
        ]);

        // Determine publish time
        $publishAt = $options['publish_at']
            ? $options['publish_at']
            : ($options['schedule']
                ? $this->scheduler->getNextAvailableSlot($draft->brand)
                : now());

        // Queue publishing job
        if ($options['schedule'] && $publishAt->isFuture()) {
            PublishContentJob::dispatch($draft, $options)
                ->delay($publishAt);

            $result = [
                'success' => true,
                'scheduled' => true,
                'publish_at' => $publishAt->toDateTimeString(),
            ];
        } else {
            // Publish immediately
            $result = $this->orchestrator->publish($draft, $options);
        }

        Log::info('Content publish initiated', [
            'draft_id' => $draft->id,
            'result' => $result,
        ]);

        return $result;
    }

    /**
     * Bulk publish multiple drafts
     *
     * @param array $draftIds
     * @param array $options
     * @return array Statistics
     */
    public function bulkPublish(array $draftIds, array $options = []): array
    {
        $stats = [
            'total' => count($draftIds),
            'scheduled' => 0,
            'published' => 0,
            'errors' => 0,
        ];

        foreach ($draftIds as $draftId) {
            try {
                $draft = ContentDraft::findOrFail($draftId);

                if ($draft->status !== ContentDraft::APPROVED) {
                    $stats['errors']++;
                    continue;
                }

                $result = $this->publishDraft($draft, $options);

                if ($result['success']) {
                    if ($result['scheduled'] ?? false) {
                        $stats['scheduled']++;
                    } else {
                        $stats['published']++;
                    }
                }
            } catch (\Exception $e) {
                $stats['errors']++;
                Log::error('Bulk publish error', [
                    'draft_id' => $draftId,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $stats;
    }

    /**
     * Schedule content generation for a brand
     *
     * @param Brand $brand
     * @param int $daysAhead
     * @return array Schedule
     */
    public function scheduleContent(Brand $brand, int $daysAhead = 7): array
    {
        $postsPerDay = $brand->settings['posts_per_day'] ?? 1;
        $totalPosts = $postsPerDay * $daysAhead;

        // Get available topics
        $topics = $this->getAvailableTopics($brand, [])
            ->take($totalPosts);

        $schedule = [];

        foreach ($topics as $index => $topic) {
            // Calculate which day and time slot
            $dayOffset = floor($index / $postsPerDay);
            $slotInDay = $index % $postsPerDay;

            $publishAt = $this->scheduler->calculateSlot(
                $brand,
                now()->addDays($dayOffset),
                $slotInDay
            );

            $schedule[] = [
                'topic_id' => $topic->id,
                'topic_title' => $topic->title,
                'publish_at' => $publishAt->toDateTimeString(),
                'day_offset' => $dayOffset,
            ];

            // Queue the generation
            ProcessTopicForPublishingJob::dispatch($topic, [
                'auto_approve' => $brand->settings['auto_approve'] ?? false,
                'publish_at' => $publishAt,
            ])->delay($publishAt->copy()->subHours(2)); // Generate 2 hours before publish
        }

        Log::info('Content schedule created', [
            'brand_id' => $brand->id,
            'total_posts' => count($schedule),
            'days_ahead' => $daysAhead,
        ]);

        return $schedule;
    }

    /**
     * Auto-approve pending drafts based on confidence score
     *
     * @param Brand $brand
     * @param float $threshold
     * @return int Number of auto-approved drafts
     */
    public function autoApprove(Brand $brand, float $threshold = 0.8): int
    {
        $drafts = ContentDraft::where('brand_id', $brand->id)
            ->where('status', ContentDraft::PENDING_REVIEW)
            ->where('confidence_score', '>=', $threshold)
            ->get();

        $approved = 0;

        foreach ($drafts as $draft) {
            try {
                $draft->update([
                    'status' => ContentDraft::APPROVED,
                    'approved_by' => null, // System approval
                    'approved_at' => now(),
                ]);

                $approved++;

                Log::info('Auto-approved draft', [
                    'draft_id' => $draft->id,
                    'confidence_score' => $draft->confidence_score,
                ]);
            } catch (\Exception $e) {
                Log::error('Auto-approve failed', [
                    'draft_id' => $draft->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $approved;
    }

    /**
     * Get available topics for content generation
     *
     * @param Brand $brand
     * @param array $options
     * @return \Illuminate\Database\Eloquent\Collection
     */
    protected function getAvailableTopics(Brand $brand, array $options = [])
    {
        $query = Topic::where('brand_id', $brand->id)
            ->where('status', Topic::DISCOVERED)
            ->where('trending_at', '>=', now()->subDays(3)) // Fresh topics only
            ->where('confidence_score', '>=', 0.6) // Minimum quality threshold
            ->orderBy('confidence_score', 'desc')
            ->orderBy('trending_at', 'desc');

        if (isset($options['category_id'])) {
            $query->where('category_id', $options['category_id']);
        }

        return $query->get();
    }

    /**
     * Get publishing statistics for a brand
     *
     * @param Brand $brand
     * @param int $days
     * @return array
     */
    public function getStatistics(Brand $brand, int $days = 30): array
    {
        $since = now()->subDays($days);

        return [
            'content_generated' => ContentDraft::where('brand_id', $brand->id)
                ->where('created_at', '>=', $since)
                ->count(),
            'content_approved' => ContentDraft::where('brand_id', $brand->id)
                ->where('status', ContentDraft::APPROVED)
                ->where('approved_at', '>=', $since)
                ->count(),
            'content_published' => ContentDraft::where('brand_id', $brand->id)
                ->where('status', ContentDraft::PUBLISHED)
                ->whereHas('publishJobs', function ($query) use ($since) {
                    $query->where('published_at', '>=', $since);
                })
                ->count(),
            'topics_discovered' => Topic::where('brand_id', $brand->id)
                ->where('created_at', '>=', $since)
                ->count(),
            'topics_used' => Topic::where('brand_id', $brand->id)
                ->where('status', Topic::USED)
                ->where('updated_at', '>=', $since)
                ->count(),
            'avg_confidence_score' => ContentDraft::where('brand_id', $brand->id)
                ->where('created_at', '>=', $since)
                ->avg('confidence_score'),
            'avg_generation_time' => DB::table('content_drafts')
                ->where('brand_id', $brand->id)
                ->where('created_at', '>=', $since)
                ->whereNotNull('generated_at')
                ->selectRaw('AVG(EXTRACT(EPOCH FROM (generated_at - created_at))) as avg_seconds')
                ->value('avg_seconds'),
        ];
    }

    /**
     * Clean up old/failed content
     *
     * @param int $daysOld
     * @return array Statistics
     */
    public function cleanup(int $daysOld = 30): array
    {
        $deletedDrafts = ContentDraft::where('status', ContentDraft::REJECTED)
            ->where('created_at', '<', now()->subDays($daysOld))
            ->delete();

        $expiredTopics = Topic::where('status', Topic::EXPIRED)
            ->where('trending_at', '<', now()->subDays($daysOld))
            ->delete();

        return [
            'deleted_drafts' => $deletedDrafts,
            'deleted_topics' => $expiredTopics,
        ];
    }
}
