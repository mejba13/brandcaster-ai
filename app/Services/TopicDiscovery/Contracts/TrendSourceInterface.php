<?php

namespace App\Services\TopicDiscovery\Contracts;

use App\Models\Category;

/**
 * Trend Source Interface
 *
 * Contract for trend discovery sources (SerpAPI, RSS, News APIs, etc.)
 */
interface TrendSourceInterface
{
    /**
     * Discover trending topics for a category
     *
     * @param Category $category
     * @param int $limit Maximum number of topics to return
     * @return array Array of topic data [title, description, keywords, source_urls, confidence_score]
     */
    public function discover(Category $category, int $limit = 10): array;

    /**
     * Get source name/identifier
     *
     * @return string
     */
    public function getName(): string;

    /**
     * Check if source is available/configured
     *
     * @return bool
     */
    public function isAvailable(): bool;

    /**
     * Get supported categories or keywords
     *
     * @return array
     */
    public function getSupportedKeywords(): array;
}
