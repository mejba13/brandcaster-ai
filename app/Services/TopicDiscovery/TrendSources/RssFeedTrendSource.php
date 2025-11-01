<?php

namespace App\Services\TopicDiscovery\TrendSources;

use App\Models\Category;
use App\Services\TopicDiscovery\Contracts\TrendSourceInterface;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Support\Facades\Log;
use SimpleXMLElement;

/**
 * RSS Feed Trend Source
 *
 * Discovers trending topics by parsing RSS feeds
 */
class RssFeedTrendSource implements TrendSourceInterface
{
    protected Client $client;

    /**
     * Default RSS feeds for common topics
     *
     * @var array
     */
    protected array $defaultFeeds = [
        'technology' => [
            'https://techcrunch.com/feed/',
            'https://www.theverge.com/rss/index.xml',
            'https://www.wired.com/feed/rss',
        ],
        'design' => [
            'https://www.smashingmagazine.com/feed/',
            'https://www.designboom.com/feed/',
            'https://www.creativebloq.com/feed',
        ],
        'business' => [
            'https://www.entrepreneur.com/latest.rss',
            'https://feeds.harvard.edu/business',
            'https://www.inc.com/rss',
        ],
        'security' => [
            'https://feeds.feedburner.com/TheHackersNews',
            'https://www.bleepingcomputer.com/feed/',
            'https://krebsonsecurity.com/feed/',
        ],
        'development' => [
            'https://dev.to/feed',
            'https://www.freecodecamp.org/news/rss/',
            'https://css-tricks.com/feed/',
        ],
        'ai' => [
            'https://www.artificialintelligence-news.com/feed/',
            'https://machinelearningmastery.com/feed/',
        ],
        'marketing' => [
            'https://neilpatel.com/feed/',
            'https://www.socialmediaexaminer.com/feed/',
        ],
    ];

    /**
     * Create a new RSS feed trend source instance
     *
     * @param Client $client
     */
    public function __construct(Client $client)
    {
        $this->client = $client;
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
        $feedUrls = $this->getFeedUrls($category);

        if (empty($feedUrls)) {
            Log::warning('No RSS feed URLs configured for category', [
                'category_id' => $category->id,
                'category_name' => $category->name,
            ]);
            return [];
        }

        Log::info('Discovering topics via RSS feeds', [
            'category_id' => $category->id,
            'feed_count' => count($feedUrls),
            'limit' => $limit,
        ]);

        $allTopics = [];

        foreach ($feedUrls as $feedUrl) {
            try {
                $topics = $this->parseFeed($feedUrl, $category);
                $allTopics = array_merge($allTopics, $topics);

                Log::info('Successfully parsed RSS feed', [
                    'feed_url' => $feedUrl,
                    'topics_found' => count($topics),
                ]);

            } catch (\Exception $e) {
                Log::error('Failed to parse RSS feed', [
                    'feed_url' => $feedUrl,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        // Sort by publication date (newest first) and limit
        usort($allTopics, function ($a, $b) {
            $dateA = $a['metadata']['pub_date'] ?? null;
            $dateB = $b['metadata']['pub_date'] ?? null;

            if (!$dateA || !$dateB) {
                return 0;
            }

            return strtotime($dateB) <=> strtotime($dateA);
        });

        $limitedTopics = array_slice($allTopics, 0, $limit);

        Log::info('Completed RSS feed discovery', [
            'category_id' => $category->id,
            'total_topics' => count($allTopics),
            'returned_topics' => count($limitedTopics),
        ]);

        return $limitedTopics;
    }

    /**
     * Get source name/identifier
     *
     * @return string
     */
    public function getName(): string
    {
        return 'rss';
    }

    /**
     * Check if source is available/configured
     *
     * @return bool
     */
    public function isAvailable(): bool
    {
        return true; // RSS is always available
    }

    /**
     * Get supported categories or keywords
     *
     * @return array
     */
    public function getSupportedKeywords(): array
    {
        return array_keys($this->defaultFeeds);
    }

    /**
     * Get feed URLs for a category
     *
     * @param Category $category
     * @return array
     */
    protected function getFeedUrls(Category $category): array
    {
        // First check if category has custom trend sources configured
        $trendSources = $category->trend_sources ?? [];

        if (!empty($trendSources['rss_feeds'])) {
            return $trendSources['rss_feeds'];
        }

        // Otherwise, use default feeds based on category keywords
        $feeds = [];
        $categoryKeywords = $category->keywords ?? [$category->slug];

        foreach ($categoryKeywords as $keyword) {
            $normalizedKeyword = strtolower(trim($keyword));

            // Check for exact match
            if (isset($this->defaultFeeds[$normalizedKeyword])) {
                $feeds = array_merge($feeds, $this->defaultFeeds[$normalizedKeyword]);
                continue;
            }

            // Check for partial match
            foreach ($this->defaultFeeds as $key => $urls) {
                if (str_contains($normalizedKeyword, $key) || str_contains($key, $normalizedKeyword)) {
                    $feeds = array_merge($feeds, $urls);
                }
            }
        }

        // If no matches found, use technology feeds as default
        if (empty($feeds)) {
            $feeds = $this->defaultFeeds['technology'] ?? [];
        }

        return array_unique($feeds);
    }

    /**
     * Parse a single RSS feed
     *
     * @param string $feedUrl
     * @param Category $category
     * @return array
     * @throws \Exception
     */
    protected function parseFeed(string $feedUrl, Category $category): array
    {
        try {
            $response = $this->client->get($feedUrl, [
                'timeout' => 30,
                'headers' => [
                    'User-Agent' => 'BrandCaster-AI/1.0',
                ],
            ]);

            $xmlContent = $response->getBody()->getContents();

            // Disable XML errors and use internal error handling
            libxml_use_internal_errors(true);

            $xml = new SimpleXMLElement($xmlContent);

            // Clear XML errors
            libxml_clear_errors();

            return $this->parseXml($xml, $feedUrl, $category);

        } catch (GuzzleException $e) {
            Log::error('Failed to fetch RSS feed', [
                'feed_url' => $feedUrl,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        } catch (\Exception $e) {
            Log::error('Failed to parse RSS XML', [
                'feed_url' => $feedUrl,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Parse XML content into topics
     *
     * @param SimpleXMLElement $xml
     * @param string $feedUrl
     * @param Category $category
     * @return array
     */
    protected function parseXml(SimpleXMLElement $xml, string $feedUrl, Category $category): array
    {
        $topics = [];

        // Check if it's an Atom feed
        if ($xml->getName() === 'feed') {
            $topics = $this->parseAtomFeed($xml, $feedUrl, $category);
        } else {
            // Assume RSS 2.0
            $topics = $this->parseRssFeed($xml, $feedUrl, $category);
        }

        return $topics;
    }

    /**
     * Parse RSS 2.0 feed
     *
     * @param SimpleXMLElement $xml
     * @param string $feedUrl
     * @param Category $category
     * @return array
     */
    protected function parseRssFeed(SimpleXMLElement $xml, string $feedUrl, Category $category): array
    {
        $topics = [];

        foreach ($xml->channel->item ?? [] as $item) {
            $title = (string) ($item->title ?? 'Untitled');
            $description = (string) ($item->description ?? '');
            $link = (string) ($item->link ?? '');
            $pubDate = (string) ($item->pubDate ?? '');

            // Skip if no title
            if (empty($title)) {
                continue;
            }

            $topics[] = [
                'title' => $this->cleanText($title),
                'description' => $this->cleanText($description),
                'keywords' => $this->extractKeywords($title, $description, $category),
                'source_urls' => array_filter([$link]),
                'confidence_score' => $this->calculateConfidenceScore($title, $description, $pubDate, $category),
                'metadata' => [
                    'feed_url' => $feedUrl,
                    'pub_date' => $pubDate,
                    'source' => $this->extractSourceFromUrl($feedUrl),
                ],
            ];
        }

        return $topics;
    }

    /**
     * Parse Atom feed
     *
     * @param SimpleXMLElement $xml
     * @param string $feedUrl
     * @param Category $category
     * @return array
     */
    protected function parseAtomFeed(SimpleXMLElement $xml, string $feedUrl, Category $category): array
    {
        $topics = [];

        foreach ($xml->entry ?? [] as $entry) {
            $title = (string) ($entry->title ?? 'Untitled');
            $summary = (string) ($entry->summary ?? $entry->content ?? '');
            $link = '';

            // Extract link
            if (isset($entry->link)) {
                foreach ($entry->link as $linkElement) {
                    if ((string) $linkElement['rel'] === 'alternate' || !isset($linkElement['rel'])) {
                        $link = (string) $linkElement['href'];
                        break;
                    }
                }
            }

            $published = (string) ($entry->published ?? $entry->updated ?? '');

            // Skip if no title
            if (empty($title)) {
                continue;
            }

            $topics[] = [
                'title' => $this->cleanText($title),
                'description' => $this->cleanText($summary),
                'keywords' => $this->extractKeywords($title, $summary, $category),
                'source_urls' => array_filter([$link]),
                'confidence_score' => $this->calculateConfidenceScore($title, $summary, $published, $category),
                'metadata' => [
                    'feed_url' => $feedUrl,
                    'pub_date' => $published,
                    'source' => $this->extractSourceFromUrl($feedUrl),
                ],
            ];
        }

        return $topics;
    }

    /**
     * Clean HTML tags and extra whitespace from text
     *
     * @param string $text
     * @return string
     */
    protected function cleanText(string $text): string
    {
        // Strip HTML tags
        $text = strip_tags($text);

        // Decode HTML entities
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        // Remove extra whitespace
        $text = preg_replace('/\s+/', ' ', $text);

        return trim($text);
    }

    /**
     * Extract keywords from title and description
     *
     * @param string $title
     * @param string $description
     * @param Category $category
     * @return array
     */
    protected function extractKeywords(string $title, string $description, Category $category): array
    {
        $keywords = [];

        // Add category keywords
        $keywords = array_merge($keywords, $category->keywords ?? []);

        // Extract from title
        $titleWords = array_filter(
            preg_split('/[\s\-\_]+/', strtolower($title)),
            function ($word) {
                return strlen($word) > 4; // Only words longer than 4 chars
            }
        );

        $keywords = array_merge($keywords, array_slice($titleWords, 0, 5));

        return array_values(array_unique($keywords));
    }

    /**
     * Extract source name from feed URL
     *
     * @param string $url
     * @return string
     */
    protected function extractSourceFromUrl(string $url): string
    {
        $host = parse_url($url, PHP_URL_HOST);

        if ($host) {
            // Remove www. prefix
            $host = preg_replace('/^www\./', '', $host);

            // Get domain name without TLD
            $parts = explode('.', $host);
            if (count($parts) >= 2) {
                return ucfirst($parts[count($parts) - 2]);
            }

            return ucfirst($host);
        }

        return 'RSS Feed';
    }

    /**
     * Calculate confidence score based on content and recency
     *
     * @param string $title
     * @param string $description
     * @param string $pubDate
     * @param Category $category
     * @return float
     */
    protected function calculateConfidenceScore(string $title, string $description, string $pubDate, Category $category): float
    {
        $score = 0.5; // Base score

        // Boost for having description
        if (!empty($description) && strlen($description) > 50) {
            $score += 0.1;
        }

        // Boost for recency
        if (!empty($pubDate)) {
            $publishedTime = strtotime($pubDate);
            if ($publishedTime !== false) {
                $hoursOld = (time() - $publishedTime) / 3600;

                if ($hoursOld < 24) {
                    $score += 0.2; // Very recent
                } elseif ($hoursOld < 48) {
                    $score += 0.15; // Recent
                } elseif ($hoursOld < 72) {
                    $score += 0.1; // Somewhat recent
                }
            }
        }

        // Boost for keyword match
        $lowerTitle = strtolower($title);
        $lowerDescription = strtolower($description);
        $categoryKeywords = $category->keywords ?? [];

        foreach ($categoryKeywords as $keyword) {
            $lowerKeyword = strtolower($keyword);
            if (str_contains($lowerTitle, $lowerKeyword) || str_contains($lowerDescription, $lowerKeyword)) {
                $score += 0.15;
                break;
            }
        }

        // Boost for longer, more detailed content
        if (strlen($title) > 30 && strlen($description) > 100) {
            $score += 0.05;
        }

        return min(1.0, $score); // Cap at 1.0
    }
}
