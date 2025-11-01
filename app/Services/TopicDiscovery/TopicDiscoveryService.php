<?php

namespace App\Services\TopicDiscovery;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Topic;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Topic Discovery Service
 *
 * Orchestrates topic discovery from multiple sources, scores, deduplicates,
 * and stores trending topics for content generation.
 */
class TopicDiscoveryService
{
    protected TrendSourceRegistry $sourceRegistry;
    protected TopicScorer $scorer;
    protected TopicDeduplicator $deduplicator;

    public function __construct(
        TrendSourceRegistry $sourceRegistry,
        TopicScorer $scorer,
        TopicDeduplicator $deduplicator
    ) {
        $this->sourceRegistry = $sourceRegistry;
        $this->scorer = $scorer;
        $this->deduplicator = $deduplicator;
    }

    /**
     * Discover topics for a specific brand
     *
     * @param Brand $brand
     * @param int $topicsPerCategory
     * @return int Number of topics discovered
     */
    public function discoverForBrand(Brand $brand, int $topicsPerCategory = 10): int
    {
        $totalDiscovered = 0;

        foreach ($brand->categories()->active()->get() as $category) {
            $discovered = $this->discoverForCategory($category, $topicsPerCategory);
            $totalDiscovered += $discovered;
        }

        Log::info('Completed topic discovery for brand', [
            'brand_id' => $brand->id,
            'total_discovered' => $totalDiscovered,
        ]);

        return $totalDiscovered;
    }

    /**
     * Discover topics for a specific category
     *
     * @param Category $category
     * @param int $limit
     * @return int Number of topics discovered
     */
    public function discoverForCategory(Category $category, int $limit = 10): int
    {
        Log::info('Starting topic discovery for category', [
            'category_id' => $category->id,
            'category_name' => $category->name,
            'limit' => $limit,
        ]);

        // Get all available sources
        $sources = $this->sourceRegistry->getAvailableSources();

        if (empty($sources)) {
            Log::warning('No trend sources available');
            return 0;
        }

        $allTopics = [];

        // Discover from each source
        foreach ($sources as $source) {
            try {
                $topics = $source->discover($category, $limit);
                $allTopics = array_merge($allTopics, $topics);

                Log::info('Discovered topics from source', [
                    'source' => $source->getName(),
                    'count' => count($topics),
                ]);
            } catch (\Exception $e) {
                Log::error('Failed to discover topics from source', [
                    'source' => $source->getName(),
                    'error' => $e->getMessage(),
                ]);
            }
        }

        if (empty($allTopics)) {
            Log::info('No topics discovered for category', [
                'category_id' => $category->id,
            ]);
            return 0;
        }

        // Score topics
        $scoredTopics = $this->scorer->score($allTopics, $category);

        // Deduplicate against existing topics
        $uniqueTopics = $this->deduplicator->deduplicate($scoredTopics, $category);

        // Sort by score and take top N
        usort($uniqueTopics, fn($a, $b) => $b['confidence_score'] <=> $a['confidence_score']);
        $topTopics = array_slice($uniqueTopics, 0, $limit);

        // Store in database
        $stored = $this->storeTopics($topTopics, $category);

        Log::info('Completed topic discovery for category', [
            'category_id' => $category->id,
            'discovered' => count($allTopics),
            'unique' => count($uniqueTopics),
            'stored' => $stored,
        ]);

        return $stored;
    }

    /**
     * Store topics in database
     *
     * @param array $topics
     * @param Category $category
     * @return int Number of topics stored
     */
    protected function storeTopics(array $topics, Category $category): int
    {
        $stored = 0;

        DB::transaction(function () use ($topics, $category, &$stored) {
            foreach ($topics as $topicData) {
                try {
                    Topic::create([
                        'brand_id' => $category->brand_id,
                        'category_id' => $category->id,
                        'title' => $topicData['title'],
                        'description' => $topicData['description'] ?? null,
                        'keywords' => $topicData['keywords'] ?? [],
                        'source_urls' => $topicData['source_urls'] ?? [],
                        'confidence_score' => $topicData['confidence_score'] ?? 0.5,
                        'trending_at' => now(),
                        'status' => Topic::DISCOVERED,
                    ]);

                    $stored++;
                } catch (\Exception $e) {
                    Log::error('Failed to store topic', [
                        'title' => $topicData['title'],
                        'error' => $e->getMessage(),
                    ]);
                }
            }
        });

        return $stored;
    }

    /**
     * Clean up old/expired topics
     *
     * @param int $daysOld Topics older than this will be marked as expired
     * @return int Number of topics expired
     */
    public function expireOldTopics(int $daysOld = 7): int
    {
        $expired = Topic::where('status', Topic::DISCOVERED)
            ->where('trending_at', '<', now()->subDays($daysOld))
            ->update(['status' => Topic::EXPIRED]);

        Log::info('Expired old topics', [
            'count' => $expired,
            'days_old' => $daysOld,
        ]);

        return $expired;
    }

    /**
     * Get next available topic for content generation
     *
     * @param Brand $brand
     * @param Category|null $category
     * @return Topic|null
     */
    public function getNextTopic(Brand $brand, ?Category $category = null): ?Topic
    {
        $query = Topic::where('brand_id', $brand->id)
            ->where('status', Topic::DISCOVERED)
            ->where('trending_at', '>=', now()->subDays(3)) // Fresh topics only
            ->orderBy('confidence_score', 'desc')
            ->orderBy('trending_at', 'desc');

        if ($category) {
            $query->where('category_id', $category->id);
        }

        return $query->first();
    }

    /**
     * Get discovery statistics for a brand
     *
     * @param Brand $brand
     * @return array
     */
    public function getStatistics(Brand $brand): array
    {
        return [
            'total_topics' => Topic::where('brand_id', $brand->id)->count(),
            'discovered' => Topic::where('brand_id', $brand->id)->discovered()->count(),
            'queued' => Topic::where('brand_id', $brand->id)->queued()->count(),
            'used' => Topic::where('brand_id', $brand->id)->used()->count(),
            'expired' => Topic::where('brand_id', $brand->id)->where('status', Topic::EXPIRED)->count(),
            'avg_confidence' => Topic::where('brand_id', $brand->id)
                ->discovered()
                ->avg('confidence_score'),
            'latest_discovery' => Topic::where('brand_id', $brand->id)
                ->orderBy('created_at', 'desc')
                ->first()?->created_at,
        ];
    }
}
