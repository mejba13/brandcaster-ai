<?php

namespace Database\Seeders;

use App\Models\Metric;
use App\Models\PublishJob;
use Illuminate\Database\Seeder;

class MetricSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Get published jobs
        $publishedJobs = PublishJob::where('status', PublishJob::STATUS_PUBLISHED)->get();

        foreach ($publishedJobs as $job) {
            // Create initial metrics
            Metric::create([
                'publish_job_id' => $job->id,
                'impressions' => $this->getImpressions($job->platform),
                'clicks' => 0,
                'likes' => 0,
                'shares' => 0,
                'comments' => 0,
                'saves' => 0,
                'metric_date' => $job->published_at->format('Y-m-d'),
            ]);

            // Create metrics for subsequent days (simulate growth)
            $daysSincePublish = now()->diffInDays($job->published_at);

            for ($day = 1; $day <= min($daysSincePublish, 30); $day++) {
                $metricDate = $job->published_at->copy()->addDays($day);

                if ($metricDate->isFuture()) {
                    break;
                }

                $baseImpressions = $this->getImpressions($job->platform);
                $impressions = $this->getImpressions($job->platform, $day);
                $clicks = (int) ($impressions * rand(2, 8) / 100); // 2-8% CTR
                $likes = (int) ($impressions * rand(1, 5) / 100); // 1-5% like rate
                $shares = (int) ($likes * rand(10, 30) / 100); // 10-30% of likes
                $comments = (int) ($likes * rand(5, 20) / 100); // 5-20% of likes
                $saves = (int) ($likes * rand(15, 40) / 100); // 15-40% of likes

                Metric::create([
                    'publish_job_id' => $job->id,
                    'impressions' => $impressions,
                    'clicks' => $clicks,
                    'likes' => $likes,
                    'shares' => $shares,
                    'comments' => $comments,
                    'saves' => $saves,
                    'metric_date' => $metricDate->format('Y-m-d'),
                ]);
            }
        }

        $this->command->info('Metrics created successfully.');
    }

    /**
     * Get realistic impressions for platform
     */
    protected function getImpressions(string $platform, int $dayOffset = 0): int
    {
        // Base impressions by platform
        $baseImpressions = match ($platform) {
            'website' => rand(500, 2000),
            'facebook' => rand(1000, 5000),
            'twitter' => rand(800, 3000),
            'linkedin' => rand(600, 2500),
            default => rand(500, 2000),
        };

        // Decay factor: impressions decrease over time
        $decayFactor = 1 - ($dayOffset * 0.05); // 5% decrease per day
        $decayFactor = max($decayFactor, 0.2); // Minimum 20% of base

        return (int) ($baseImpressions * $decayFactor);
    }
}
