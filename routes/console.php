<?php

use App\Jobs\DiscoverTopicsJob;
use App\Models\Brand;
use App\Models\ContentDraft;
use App\Models\Topic;
use App\Services\Publishing\PublishingEngine;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Schedule topic discovery every 6 hours
Schedule::job(new DiscoverTopicsJob())
    ->everySixHours()
    ->name('discover-topics')
    ->withoutOverlapping()
    ->onOneServer();

// Clean up old topics daily at 2 AM
Schedule::call(function () {
    $daysOld = 7;
    $expired = Topic::where('status', Topic::DISCOVERED)
        ->where('trending_at', '<', now()->subDays($daysOld))
        ->update(['status' => Topic::EXPIRED]);

    if ($expired > 0) {
        \Illuminate\Support\Facades\Log::info('Cleaned up old topics', [
            'count' => $expired,
            'days_old' => $daysOld,
        ]);
    }
})->dailyAt('02:00')
    ->name('cleanup-old-topics')
    ->onOneServer();

// Generate and schedule content for all brands every morning at 6 AM
Schedule::call(function () {
    $publishingEngine = app(PublishingEngine::class);

    Brand::active()->each(function ($brand) use ($publishingEngine) {
        try {
            $stats = $publishingEngine->generateForBrand($brand, [
                'limit' => $brand->settings['posts_per_day'] ?? 1,
                'auto_approve' => $brand->settings['auto_approve'] ?? false,
                'schedule' => true,
            ]);

            \Illuminate\Support\Facades\Log::info('Scheduled daily content generation', [
                'brand_id' => $brand->id,
                'stats' => $stats,
            ]);
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Daily content generation failed', [
                'brand_id' => $brand->id,
                'error' => $e->getMessage(),
            ]);
        }
    });
})->dailyAt('06:00')
    ->name('generate-daily-content')
    ->withoutOverlapping()
    ->onOneServer();

// Auto-approve high-quality content twice per day
Schedule::call(function () {
    $publishingEngine = app(PublishingEngine::class);

    Brand::active()->each(function ($brand) use ($publishingEngine) {
        // Only auto-approve if enabled for the brand
        if (!($brand->settings['auto_approve'] ?? false)) {
            return;
        }

        try {
            $approved = $publishingEngine->autoApprove($brand, 0.8);

            if ($approved > 0) {
                \Illuminate\Support\Facades\Log::info('Auto-approved content', [
                    'brand_id' => $brand->id,
                    'count' => $approved,
                ]);
            }
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Auto-approval failed', [
                'brand_id' => $brand->id,
                'error' => $e->getMessage(),
            ]);
        }
    });
})->twiceDaily(9, 15) // 9 AM and 3 PM
    ->name('auto-approve-content')
    ->onOneServer();

// Clean up old rejected drafts and expired topics weekly
Schedule::call(function () {
    $publishingEngine = app(PublishingEngine::class);

    try {
        $stats = $publishingEngine->cleanup(30); // Delete content older than 30 days

        \Illuminate\Support\Facades\Log::info('Cleaned up old content', $stats);
    } catch (\Exception $e) {
        \Illuminate\Support\Facades\Log::error('Cleanup failed', [
            'error' => $e->getMessage(),
        ]);
    }
})->weekly()
    ->sundays()
    ->at('03:00')
    ->name('cleanup-old-content')
    ->onOneServer();
