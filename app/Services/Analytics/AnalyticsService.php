<?php

namespace App\Services\Analytics;

use App\Models\Brand;
use App\Models\ContentDraft;
use App\Models\Metric;
use App\Models\PublishJob;
use App\Models\Topic;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Analytics Service
 *
 * Provides comprehensive analytics for content performance,
 * publishing activity, and engagement metrics.
 */
class AnalyticsService
{
    /**
     * Get overview statistics for a brand
     *
     * @param Brand $brand
     * @param int $days
     * @return array
     */
    public function getOverviewStats(Brand $brand, int $days = 30): array
    {
        $since = now()->subDays($days);

        return [
            'content' => $this->getContentStats($brand, $since),
            'publishing' => $this->getPublishingStats($brand, $since),
            'engagement' => $this->getEngagementStats($brand, $since),
            'topics' => $this->getTopicStats($brand, $since),
        ];
    }

    /**
     * Get content generation statistics
     *
     * @param Brand $brand
     * @param Carbon $since
     * @return array
     */
    protected function getContentStats(Brand $brand, Carbon $since): array
    {
        $drafts = ContentDraft::where('brand_id', $brand->id)
            ->where('created_at', '>=', $since);

        $total = $drafts->clone()->count();
        $approved = $drafts->clone()->where('status', ContentDraft::STATUS_APPROVED)->count();
        $published = $drafts->clone()->where('status', ContentDraft::STATUS_PUBLISHED)->count();
        $rejected = $drafts->clone()->where('status', ContentDraft::STATUS_REJECTED)->count();

        return [
            'total_generated' => $total,
            'approved' => $approved,
            'published' => $published,
            'rejected' => $rejected,
            'approval_rate' => $total > 0 ? round(($approved / $total) * 100, 2) : 0,
            'avg_confidence_score' => round($drafts->clone()->avg('confidence_score') * 100, 2),
            'avg_generation_time' => $this->getAverageGenerationTime($brand, $since),
        ];
    }

    /**
     * Get publishing statistics
     *
     * @param Brand $brand
     * @param Carbon $since
     * @return array
     */
    protected function getPublishingStats(Brand $brand, Carbon $since): array
    {
        $jobs = PublishJob::whereHas('contentDraft', function ($query) use ($brand) {
            $query->where('brand_id', $brand->id);
        })->where('created_at', '>=', $since);

        $total = $jobs->clone()->count();
        $published = $jobs->clone()->where('status', PublishJob::STATUS_PUBLISHED)->count();
        $failed = $jobs->clone()->where('status', PublishJob::STATUS_FAILED)->count();

        $byPlatform = PublishJob::whereHas('contentDraft', function ($query) use ($brand) {
            $query->where('brand_id', $brand->id);
        })
            ->where('created_at', '>=', $since)
            ->where('status', PublishJob::STATUS_PUBLISHED)
            ->select('platform', DB::raw('count(*) as count'))
            ->groupBy('platform')
            ->pluck('count', 'platform')
            ->toArray();

        return [
            'total_jobs' => $total,
            'published' => $published,
            'failed' => $failed,
            'success_rate' => $total > 0 ? round(($published / $total) * 100, 2) : 0,
            'by_platform' => $byPlatform,
        ];
    }

    /**
     * Get engagement statistics
     *
     * @param Brand $brand
     * @param Carbon $since
     * @return array
     */
    protected function getEngagementStats(Brand $brand, Carbon $since): array
    {
        $metrics = Metric::whereHas('publishJob.contentDraft', function ($query) use ($brand) {
            $query->where('brand_id', $brand->id);
        })->where('created_at', '>=', $since);

        $totalImpressions = $metrics->clone()->sum('impressions');
        $totalClicks = $metrics->clone()->sum('clicks');
        $totalLikes = $metrics->clone()->sum('likes');
        $totalShares = $metrics->clone()->sum('shares');
        $totalComments = $metrics->clone()->sum('comments');

        return [
            'total_impressions' => $totalImpressions,
            'total_clicks' => $totalClicks,
            'total_likes' => $totalLikes,
            'total_shares' => $totalShares,
            'total_comments' => $totalComments,
            'avg_engagement_rate' => $this->calculateEngagementRate($brand, $since),
            'ctr' => $totalImpressions > 0 ? round(($totalClicks / $totalImpressions) * 100, 2) : 0,
        ];
    }

    /**
     * Get topic discovery statistics
     *
     * @param Brand $brand
     * @param Carbon $since
     * @return array
     */
    protected function getTopicStats(Brand $brand, Carbon $since): array
    {
        $topics = Topic::where('brand_id', $brand->id)
            ->where('created_at', '>=', $since);

        return [
            'total_discovered' => $topics->clone()->count(),
            'used' => $topics->clone()->where('status', Topic::STATUS_USED)->count(),
            'available' => $topics->clone()->where('status', Topic::STATUS_DISCOVERED)->count(),
            'expired' => $topics->clone()->where('status', Topic::STATUS_EXPIRED)->count(),
            'avg_confidence_score' => round($topics->clone()->avg('confidence_score') * 100, 2),
        ];
    }

    /**
     * Get content performance over time
     *
     * @param Brand $brand
     * @param int $days
     * @return array
     */
    public function getContentPerformanceChart(Brand $brand, int $days = 30): array
    {
        $data = [];
        $startDate = now()->subDays($days);

        for ($i = 0; $i < $days; $i++) {
            $date = $startDate->copy()->addDays($i);

            $data[] = [
                'date' => $date->format('Y-m-d'),
                'generated' => ContentDraft::where('brand_id', $brand->id)
                    ->whereDate('created_at', $date)
                    ->count(),
                'published' => PublishJob::whereHas('contentDraft', function ($query) use ($brand) {
                    $query->where('brand_id', $brand->id);
                })
                    ->where('status', PublishJob::STATUS_PUBLISHED)
                    ->whereDate('published_at', $date)
                    ->count(),
            ];
        }

        return $data;
    }

    /**
     * Get engagement trends over time
     *
     * @param Brand $brand
     * @param int $days
     * @return array
     */
    public function getEngagementTrendsChart(Brand $brand, int $days = 30): array
    {
        $data = [];
        $startDate = now()->subDays($days);

        for ($i = 0; $i < $days; $i++) {
            $date = $startDate->copy()->addDays($i);

            $metrics = Metric::whereHas('publishJob.contentDraft', function ($query) use ($brand) {
                $query->where('brand_id', $brand->id);
            })
                ->whereDate('created_at', $date)
                ->selectRaw('SUM(impressions) as impressions, SUM(clicks) as clicks, SUM(likes) as likes, SUM(shares) as shares, SUM(comments) as comments')
                ->first();

            $data[] = [
                'date' => $date->format('Y-m-d'),
                'impressions' => $metrics->impressions ?? 0,
                'clicks' => $metrics->clicks ?? 0,
                'engagements' => ($metrics->likes ?? 0) + ($metrics->shares ?? 0) + ($metrics->comments ?? 0),
            ];
        }

        return $data;
    }

    /**
     * Get top performing content
     *
     * @param Brand $brand
     * @param int $limit
     * @param int $days
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getTopPerformingContent(Brand $brand, int $limit = 10, int $days = 30): \Illuminate\Database\Eloquent\Collection
    {
        $since = now()->subDays($days);

        return ContentDraft::where('brand_id', $brand->id)
            ->where('status', ContentDraft::STATUS_PUBLISHED)
            ->whereHas('publishJobs', function ($query) use ($since) {
                $query->where('published_at', '>=', $since);
            })
            ->withSum(['publishJobs as total_impressions' => function ($query) use ($since) {
                $query->join('metrics', 'publish_jobs.id', '=', 'metrics.publish_job_id')
                    ->where('metrics.created_at', '>=', $since);
            }], 'metrics.impressions')
            ->withSum(['publishJobs as total_engagements' => function ($query) use ($since) {
                $query->join('metrics', 'publish_jobs.id', '=', 'metrics.publish_job_id')
                    ->where('metrics.created_at', '>=', $since);
            }], DB::raw('metrics.likes + metrics.shares + metrics.comments'))
            ->orderByDesc('total_engagements')
            ->limit($limit)
            ->get();
    }

    /**
     * Get platform performance comparison
     *
     * @param Brand $brand
     * @param int $days
     * @return array
     */
    public function getPlatformPerformance(Brand $brand, int $days = 30): array
    {
        $since = now()->subDays($days);

        $platforms = ['website', 'facebook', 'twitter', 'linkedin'];
        $performance = [];

        foreach ($platforms as $platform) {
            $jobs = PublishJob::whereHas('contentDraft', function ($query) use ($brand) {
                $query->where('brand_id', $brand->id);
            })
                ->where('platform', $platform)
                ->where('created_at', '>=', $since);

            $totalJobs = $jobs->clone()->count();
            $publishedJobs = $jobs->clone()->where('status', PublishJob::STATUS_PUBLISHED)->count();

            // Get metrics
            $metrics = Metric::whereHas('publishJob', function ($query) use ($brand, $platform, $since) {
                $query->whereHas('contentDraft', function ($q) use ($brand) {
                    $q->where('brand_id', $brand->id);
                })
                    ->where('platform', $platform)
                    ->where('created_at', '>=', $since);
            })
                ->selectRaw('
                    SUM(impressions) as total_impressions,
                    SUM(clicks) as total_clicks,
                    SUM(likes) as total_likes,
                    SUM(shares) as total_shares,
                    SUM(comments) as total_comments
                ')
                ->first();

            $impressions = $metrics->total_impressions ?? 0;
            $clicks = $metrics->total_clicks ?? 0;
            $engagements = ($metrics->total_likes ?? 0) + ($metrics->total_shares ?? 0) + ($metrics->total_comments ?? 0);

            $performance[$platform] = [
                'total_posts' => $publishedJobs,
                'impressions' => $impressions,
                'clicks' => $clicks,
                'engagements' => $engagements,
                'ctr' => $impressions > 0 ? round(($clicks / $impressions) * 100, 2) : 0,
                'engagement_rate' => $impressions > 0 ? round(($engagements / $impressions) * 100, 2) : 0,
                'success_rate' => $totalJobs > 0 ? round(($publishedJobs / $totalJobs) * 100, 2) : 0,
            ];
        }

        return $performance;
    }

    /**
     * Get category performance
     *
     * @param Brand $brand
     * @param int $days
     * @return array
     */
    public function getCategoryPerformance(Brand $brand, int $days = 30): array
    {
        $since = now()->subDays($days);

        return ContentDraft::where('content_drafts.brand_id', $brand->id)
            ->where('content_drafts.created_at', '>=', $since)
            ->join('categories', 'content_drafts.category_id', '=', 'categories.id')
            ->select(
                'categories.name as category_name',
                DB::raw('COUNT(*) as total_posts'),
                DB::raw('AVG(content_drafts.confidence_score) as avg_score'),
                DB::raw('SUM(CASE WHEN content_drafts.status = "published" THEN 1 ELSE 0 END) as published_count')
            )
            ->groupBy('categories.id', 'categories.name')
            ->orderByDesc('published_count')
            ->get()
            ->map(function ($item) {
                return [
                    'category' => $item->category_name,
                    'total_posts' => $item->total_posts,
                    'published' => $item->published_count,
                    'avg_score' => round($item->avg_score * 100, 2),
                    'publish_rate' => $item->total_posts > 0
                        ? round(($item->published_count / $item->total_posts) * 100, 2)
                        : 0,
                ];
            })
            ->toArray();
    }

    /**
     * Calculate average generation time
     *
     * @param Brand $brand
     * @param Carbon $since
     * @return float
     */
    protected function getAverageGenerationTime(Brand $brand, Carbon $since): float
    {
        $avgSeconds = ContentDraft::where('brand_id', $brand->id)
            ->where('created_at', '>=', $since)
            ->whereNotNull('generated_at')
            ->selectRaw('AVG(TIMESTAMPDIFF(SECOND, created_at, generated_at)) as avg_seconds')
            ->value('avg_seconds');

        return $avgSeconds ? round($avgSeconds / 60, 2) : 0; // Return in minutes
    }

    /**
     * Calculate average engagement rate
     *
     * @param Brand $brand
     * @param Carbon $since
     * @return float
     */
    protected function calculateEngagementRate(Brand $brand, Carbon $since): float
    {
        $metrics = Metric::whereHas('publishJob.contentDraft', function ($query) use ($brand) {
            $query->where('brand_id', $brand->id);
        })
            ->where('created_at', '>=', $since)
            ->selectRaw('
                SUM(impressions) as total_impressions,
                SUM(likes + shares + comments) as total_engagements
            ')
            ->first();

        $impressions = $metrics->total_impressions ?? 0;
        $engagements = $metrics->total_engagements ?? 0;

        return $impressions > 0 ? round(($engagements / $impressions) * 100, 2) : 0;
    }

    /**
     * Get publishing schedule adherence
     *
     * @param Brand $brand
     * @param int $days
     * @return array
     */
    public function getScheduleAdherence(Brand $brand, int $days = 30): array
    {
        $since = now()->subDays($days);

        $scheduledJobs = PublishJob::whereHas('contentDraft', function ($query) use ($brand) {
            $query->where('brand_id', $brand->id);
        })
            ->where('status', PublishJob::STATUS_PENDING)
            ->where('scheduled_at', '>=', $since)
            ->count();

        $publishedOnTime = PublishJob::whereHas('contentDraft', function ($query) use ($brand) {
            $query->where('brand_id', $brand->id);
        })
            ->where('status', PublishJob::STATUS_PUBLISHED)
            ->where('published_at', '>=', $since)
            ->whereRaw('ABS(TIMESTAMPDIFF(MINUTE, scheduled_at, published_at)) <= 5')
            ->count();

        $totalPublished = PublishJob::whereHas('contentDraft', function ($query) use ($brand) {
            $query->where('brand_id', $brand->id);
        })
            ->where('status', PublishJob::STATUS_PUBLISHED)
            ->where('published_at', '>=', $since)
            ->count();

        return [
            'scheduled_jobs' => $scheduledJobs,
            'published_on_time' => $publishedOnTime,
            'adherence_rate' => $totalPublished > 0
                ? round(($publishedOnTime / $totalPublished) * 100, 2)
                : 0,
        ];
    }

    /**
     * Get summary report for email/export
     *
     * @param Brand $brand
     * @param int $days
     * @return array
     */
    public function getSummaryReport(Brand $brand, int $days = 7): array
    {
        $stats = $this->getOverviewStats($brand, $days);
        $topContent = $this->getTopPerformingContent($brand, 5, $days);
        $platformPerf = $this->getPlatformPerformance($brand, $days);

        return [
            'period' => [
                'start' => now()->subDays($days)->format('Y-m-d'),
                'end' => now()->format('Y-m-d'),
                'days' => $days,
            ],
            'brand' => [
                'id' => $brand->id,
                'name' => $brand->name,
            ],
            'stats' => $stats,
            'top_content' => $topContent,
            'platform_performance' => $platformPerf,
        ];
    }
}
