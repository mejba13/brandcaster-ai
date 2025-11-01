<?php

namespace App\Services\TopicDiscovery;

use App\Models\Category;
use Illuminate\Support\Str;

/**
 * Topic Scorer
 *
 * Scores discovered topics based on relevance, keyword matching,
 * recency, and other factors.
 */
class TopicScorer
{
    /**
     * Score multiple topics
     *
     * @param array $topics
     * @param Category $category
     * @return array Topics with confidence_score added
     */
    public function score(array $topics, Category $category): array
    {
        return array_map(function ($topic) use ($category) {
            $topic['confidence_score'] = $this->scoreTopic($topic, $category);
            return $topic;
        }, $topics);
    }

    /**
     * Score a single topic
     *
     * @param array $topic
     * @param Category $category
     * @return float Score between 0 and 1
     */
    public function scoreTopic(array $topic, Category $category): float
    {
        $scores = [];

        // 1. Keyword relevance (40% weight)
        $scores['keyword_relevance'] = $this->scoreKeywordRelevance($topic, $category) * 0.4;

        // 2. Title quality (20% weight)
        $scores['title_quality'] = $this->scoreTitleQuality($topic) * 0.2;

        // 3. Description completeness (15% weight)
        $scores['description'] = $this->scoreDescription($topic) * 0.15;

        // 4. Source credibility (15% weight)
        $scores['source_credibility'] = $this->scoreSourceCredibility($topic) * 0.15;

        // 5. Recency (10% weight)
        $scores['recency'] = $this->scoreRecency($topic) * 0.1;

        $totalScore = array_sum($scores);

        // Normalize to 0-1 range
        return min(max($totalScore, 0), 1);
    }

    /**
     * Score keyword relevance
     *
     * @param array $topic
     * @param Category $category
     * @return float
     */
    protected function scoreKeywordRelevance(array $topic, Category $category): float
    {
        $categoryKeywords = array_map('strtolower', $category->keywords);
        $topicText = strtolower(($topic['title'] ?? '') . ' ' . ($topic['description'] ?? ''));
        $topicKeywords = array_map('strtolower', $topic['keywords'] ?? []);

        $matchCount = 0;
        $totalKeywords = count($categoryKeywords);

        if ($totalKeywords === 0) {
            return 0.5; // Neutral if no keywords defined
        }

        foreach ($categoryKeywords as $keyword) {
            // Check if keyword appears in title, description, or topic keywords
            if (
                str_contains($topicText, $keyword) ||
                in_array($keyword, $topicKeywords)
            ) {
                $matchCount++;
            }
        }

        return $matchCount / $totalKeywords;
    }

    /**
     * Score title quality
     *
     * @param array $topic
     * @return float
     */
    protected function scoreTitleQuality(array $topic): float
    {
        $title = $topic['title'] ?? '';
        $score = 1.0;

        // Penalize very short titles
        if (strlen($title) < 20) {
            $score -= 0.3;
        }

        // Penalize very long titles
        if (strlen($title) > 150) {
            $score -= 0.2;
        }

        // Bonus for question titles
        if (str_ends_with($title, '?')) {
            $score += 0.1;
        }

        // Bonus for "How to" titles
        if (preg_match('/^(how to|guide to|introduction to)/i', $title)) {
            $score += 0.15;
        }

        // Penalize clickbait patterns
        if (preg_match('/(shocking|unbelievable|you won\'t believe)/i', $title)) {
            $score -= 0.3;
        }

        return min(max($score, 0), 1);
    }

    /**
     * Score description completeness
     *
     * @param array $topic
     * @return float
     */
    protected function scoreDescription(array $topic): float
    {
        $description = $topic['description'] ?? '';

        if (empty($description)) {
            return 0.3; // Low but not zero if missing
        }

        $length = strlen($description);

        // Ideal length: 100-500 characters
        if ($length >= 100 && $length <= 500) {
            return 1.0;
        } elseif ($length < 100) {
            return 0.5 + ($length / 200); // Scale up from 0.5
        } else {
            return 0.8; // Long but acceptable
        }
    }

    /**
     * Score source credibility
     *
     * @param array $topic
     * @return float
     */
    protected function scoreSourceCredibility(array $topic): float
    {
        $sources = $topic['source_urls'] ?? [];

        if (empty($sources)) {
            return 0.5; // Neutral if no sources
        }

        $credibleDomains = [
            'techcrunch.com', 'theverge.com', 'arstechnica.com', 'wired.com',
            'medium.com', 'dev.to', 'smashingmagazine.com', 'css-tricks.com',
            'github.com', 'stackoverflow.com', 'reddit.com', 'news.ycombinator.com',
            'forbes.com', 'bloomberg.com', 'reuters.com', 'bbc.com', 'cnn.com',
            'nytimes.com', 'wsj.com', 'theguardian.com', 'axios.com',
        ];

        $credibleCount = 0;

        foreach ($sources as $url) {
            $domain = parse_url($url, PHP_URL_HOST);
            $domain = str_replace('www.', '', $domain ?? '');

            if (in_array($domain, $credibleDomains)) {
                $credibleCount++;
            }
        }

        // Score based on percentage of credible sources
        if (count($sources) === 0) {
            return 0.5;
        }

        return min(0.5 + ($credibleCount / count($sources)) * 0.5, 1.0);
    }

    /**
     * Score recency
     *
     * @param array $topic
     * @return float
     */
    protected function scoreRecency(array $topic): float
    {
        // If published_at is provided, score based on age
        if (isset($topic['published_at'])) {
            $publishedAt = is_string($topic['published_at'])
                ? strtotime($topic['published_at'])
                : $topic['published_at'];

            $ageInDays = (time() - $publishedAt) / 86400;

            // Topics less than 1 day old: 1.0
            // 1-3 days: 0.8
            // 3-7 days: 0.6
            // 7+ days: 0.4
            if ($ageInDays < 1) {
                return 1.0;
            } elseif ($ageInDays < 3) {
                return 0.8;
            } elseif ($ageInDays < 7) {
                return 0.6;
            } else {
                return 0.4;
            }
        }

        // Default if no timestamp
        return 0.7;
    }

    /**
     * Get scoring breakdown for a topic (for debugging)
     *
     * @param array $topic
     * @param Category $category
     * @return array
     */
    public function getScoreBreakdown(array $topic, Category $category): array
    {
        return [
            'keyword_relevance' => $this->scoreKeywordRelevance($topic, $category),
            'title_quality' => $this->scoreTitleQuality($topic),
            'description' => $this->scoreDescription($topic),
            'source_credibility' => $this->scoreSourceCredibility($topic),
            'recency' => $this->scoreRecency($topic),
            'total' => $this->scoreTopic($topic, $category),
        ];
    }
}
