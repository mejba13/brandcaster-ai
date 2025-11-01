<?php

namespace App\Services\TopicDiscovery;

use App\Models\Category;
use App\Models\Topic;
use Illuminate\Support\Str;

/**
 * Topic Deduplicator
 *
 * Removes duplicate topics based on title similarity and
 * checks against existing topics in the database.
 */
class TopicDeduplicator
{
    /**
     * Deduplicate topics
     *
     * @param array $topics
     * @param Category $category
     * @return array Unique topics
     */
    public function deduplicate(array $topics, Category $category): array
    {
        // First, deduplicate within the input array
        $unique = $this->deduplicateArray($topics);

        // Then, filter out topics that already exist in database
        $unique = $this->filterExisting($unique, $category);

        return $unique;
    }

    /**
     * Deduplicate within an array of topics
     *
     * @param array $topics
     * @return array
     */
    protected function deduplicateArray(array $topics): array
    {
        $unique = [];
        $seenTitles = [];

        foreach ($topics as $topic) {
            $normalizedTitle = $this->normalizeTitle($topic['title'] ?? '');

            // Check for exact match
            if (in_array($normalizedTitle, $seenTitles)) {
                continue;
            }

            // Check for similar titles
            $isSimilar = false;
            foreach ($seenTitles as $seenTitle) {
                if ($this->areSimilar($normalizedTitle, $seenTitle)) {
                    $isSimilar = true;
                    break;
                }
            }

            if (!$isSimilar) {
                $unique[] = $topic;
                $seenTitles[] = $normalizedTitle;
            }
        }

        return $unique;
    }

    /**
     * Filter out topics that already exist in database
     *
     * @param array $topics
     * @param Category $category
     * @return array
     */
    protected function filterExisting(array $topics, Category $category): array
    {
        // Get recent topics from the last 30 days
        $existingTopics = Topic::where('brand_id', $category->brand_id)
            ->where('created_at', '>=', now()->subDays(30))
            ->pluck('title')
            ->map(fn($title) => $this->normalizeTitle($title))
            ->toArray();

        return array_filter($topics, function ($topic) use ($existingTopics) {
            $normalizedTitle = $this->normalizeTitle($topic['title'] ?? '');

            foreach ($existingTopics as $existingTitle) {
                if ($this->areSimilar($normalizedTitle, $existingTitle)) {
                    return false; // Duplicate found
                }
            }

            return true; // Unique
        });
    }

    /**
     * Normalize title for comparison
     *
     * @param string $title
     * @return string
     */
    protected function normalizeTitle(string $title): string
    {
        // Convert to lowercase
        $normalized = strtolower($title);

        // Remove punctuation
        $normalized = preg_replace('/[^\w\s]/', '', $normalized);

        // Remove extra whitespace
        $normalized = preg_replace('/\s+/', ' ', $normalized);

        return trim($normalized);
    }

    /**
     * Check if two titles are similar
     *
     * @param string $title1
     * @param string $title2
     * @param float $threshold Similarity threshold (0-1)
     * @return bool
     */
    protected function areSimilar(string $title1, string $title2, float $threshold = 0.85): bool
    {
        // Exact match
        if ($title1 === $title2) {
            return true;
        }

        // Calculate similarity using Levenshtein distance
        $similarity = $this->calculateSimilarity($title1, $title2);

        return $similarity >= $threshold;
    }

    /**
     * Calculate similarity between two strings
     *
     * @param string $str1
     * @param string $str2
     * @return float Similarity score (0-1)
     */
    protected function calculateSimilarity(string $str1, string $str2): float
    {
        // Use similar_text for better performance on longer strings
        similar_text($str1, $str2, $percent);
        return $percent / 100;
    }

    /**
     * Check if topic contains common words from category keywords
     *
     * @param array $topic
     * @param Category $category
     * @return bool
     */
    protected function hasKeywordOverlap(array $topic, Category $category): bool
    {
        $topicText = strtolower(($topic['title'] ?? '') . ' ' . ($topic['description'] ?? ''));
        $categoryKeywords = array_map('strtolower', $category->keywords);

        foreach ($categoryKeywords as $keyword) {
            if (str_contains($topicText, $keyword)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Get duplicate count for debugging
     *
     * @param array $topics
     * @return int
     */
    public function getDuplicateCount(array $topics): int
    {
        $originalCount = count($topics);
        $uniqueCount = count($this->deduplicateArray($topics));

        return $originalCount - $uniqueCount;
    }
}
