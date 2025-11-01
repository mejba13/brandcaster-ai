<?php

namespace App\Services\TopicDiscovery\TrendSources;

use App\Models\Category;
use App\Services\TopicDiscovery\Contracts\TrendSourceInterface;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Support\Facades\Log;

/**
 * SerpAPI Trend Source
 *
 * Discovers trending topics using SerpAPI's Google News API
 */
class SerpApiTrendSource implements TrendSourceInterface
{
    protected Client $client;
    protected string $apiKey;
    protected string $baseUrl = 'https://serpapi.com/search.json';

    /**
     * Create a new SerpAPI trend source instance
     *
     * @param Client $client
     */
    public function __construct(Client $client)
    {
        $this->client = $client;
        $this->apiKey = config('services.serpapi.key', '');
    }

    /**
     * Discover trending topics for a category
     *
     * @param Category $category
     * @param int $limit Maximum number of topics to return
     * @return array Array of topic data [title, description, keywords, source_urls, confidence_score]
     */
    public function discover(Category $category, int $limit = 10): array
    {
        if (!$this->isAvailable()) {
            Log::warning('SerpAPI is not available - API key not configured');
            return [];
        }

        $query = $this->buildQuery($category);

        Log::info('Discovering topics via SerpAPI', [
            'category_id' => $category->id,
            'query' => $query,
            'limit' => $limit,
        ]);

        try {
            $response = $this->client->get($this->baseUrl, [
                'query' => [
                    'engine' => 'google_news',
                    'q' => $query,
                    'api_key' => $this->apiKey,
                    'num' => $limit,
                    'gl' => 'us',
                    'hl' => 'en',
                ],
                'timeout' => 30,
            ]);

            $data = json_decode($response->getBody()->getContents(), true);

            if (isset($data['error'])) {
                Log::error('SerpAPI returned an error', [
                    'error' => $data['error'],
                    'query' => $query,
                ]);
                return [];
            }

            $topics = $this->parseResults($data, $category);

            Log::info('Successfully discovered topics via SerpAPI', [
                'category_id' => $category->id,
                'count' => count($topics),
            ]);

            return $topics;

        } catch (GuzzleException $e) {
            Log::error('Failed to fetch topics from SerpAPI', [
                'category_id' => $category->id,
                'error' => $e->getMessage(),
                'query' => $query,
            ]);
            return [];
        } catch (\Exception $e) {
            Log::error('Unexpected error in SerpAPI discovery', [
                'category_id' => $category->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return [];
        }
    }

    /**
     * Get source name/identifier
     *
     * @return string
     */
    public function getName(): string
    {
        return 'serpapi';
    }

    /**
     * Check if source is available/configured
     *
     * @return bool
     */
    public function isAvailable(): bool
    {
        return !empty($this->apiKey);
    }

    /**
     * Get supported categories or keywords
     *
     * @return array
     */
    public function getSupportedKeywords(): array
    {
        return [
            'technology',
            'design',
            'business',
            'marketing',
            'security',
            'development',
            'ai',
            'machine learning',
            'web development',
            'mobile',
            'startup',
            'entrepreneurship',
            'productivity',
            'innovation',
            'trends',
        ];
    }

    /**
     * Build search query from category keywords
     *
     * @param Category $category
     * @return string
     */
    protected function buildQuery(Category $category): string
    {
        $keywords = $category->keywords ?? [];

        if (empty($keywords)) {
            $keywords = [$category->name];
        }

        // Take top 3 keywords to avoid overly complex queries
        $topKeywords = array_slice($keywords, 0, 3);

        return implode(' OR ', array_map(function ($keyword) {
            return '"' . addslashes($keyword) . '"';
        }, $topKeywords));
    }

    /**
     * Parse SerpAPI results into topic format
     *
     * @param array $data
     * @param Category $category
     * @return array
     */
    protected function parseResults(array $data, Category $category): array
    {
        $topics = [];
        $newsResults = $data['news_results'] ?? [];

        foreach ($newsResults as $result) {
            $topics[] = [
                'title' => $result['title'] ?? 'Untitled',
                'description' => $result['snippet'] ?? $result['summary'] ?? null,
                'keywords' => $this->extractKeywords($result, $category),
                'source_urls' => $this->extractSourceUrls($result),
                'confidence_score' => $this->calculateConfidenceScore($result, $category),
                'metadata' => [
                    'source' => $result['source'] ?? null,
                    'date' => $result['date'] ?? null,
                    'thumbnail' => $result['thumbnail'] ?? null,
                ],
            ];
        }

        return $topics;
    }

    /**
     * Extract keywords from result
     *
     * @param array $result
     * @param Category $category
     * @return array
     */
    protected function extractKeywords(array $result, Category $category): array
    {
        $keywords = [];

        // Add category keywords
        $keywords = array_merge($keywords, $category->keywords ?? []);

        // Extract keywords from title
        $title = $result['title'] ?? '';
        $titleWords = array_filter(explode(' ', strtolower($title)), function ($word) {
            return strlen($word) > 4; // Only words longer than 4 chars
        });

        $keywords = array_merge($keywords, array_slice($titleWords, 0, 5));

        return array_values(array_unique($keywords));
    }

    /**
     * Extract source URLs from result
     *
     * @param array $result
     * @return array
     */
    protected function extractSourceUrls(array $result): array
    {
        $urls = [];

        if (!empty($result['link'])) {
            $urls[] = $result['link'];
        }

        // Add related stories if available
        if (!empty($result['stories'])) {
            foreach ($result['stories'] as $story) {
                if (!empty($story['link'])) {
                    $urls[] = $story['link'];
                }
            }
        }

        return array_unique($urls);
    }

    /**
     * Calculate confidence score for a result
     *
     * @param array $result
     * @param Category $category
     * @return float
     */
    protected function calculateConfidenceScore(array $result, Category $category): float
    {
        $score = 0.5; // Base score

        // Boost for having a snippet/description
        if (!empty($result['snippet']) || !empty($result['summary'])) {
            $score += 0.1;
        }

        // Boost for recent content (if date is available)
        if (!empty($result['date'])) {
            $score += 0.1;
        }

        // Boost for having thumbnail
        if (!empty($result['thumbnail'])) {
            $score += 0.05;
        }

        // Boost for keyword match in title
        $title = strtolower($result['title'] ?? '');
        $categoryKeywords = $category->keywords ?? [];

        foreach ($categoryKeywords as $keyword) {
            if (str_contains($title, strtolower($keyword))) {
                $score += 0.1;
                break;
            }
        }

        // Boost for having related stories
        if (!empty($result['stories'])) {
            $score += 0.15;
        }

        return min(1.0, $score); // Cap at 1.0
    }
}
