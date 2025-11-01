<?php

namespace App\Services\Publishing;

use App\Models\Brand;
use App\Models\PublishJob;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;

/**
 * Content Scheduler
 *
 * Handles intelligent scheduling of content based on:
 * - Brand settings (posts per day, quiet hours)
 * - Existing scheduled content
 * - Optimal posting times
 * - Platform-specific best practices
 */
class ContentScheduler
{
    /**
     * Get next available publishing slot for a brand
     *
     * @param Brand $brand
     * @param Carbon|null $startFrom
     * @return Carbon
     */
    public function getNextAvailableSlot(Brand $brand, ?Carbon $startFrom = null): Carbon
    {
        $startFrom = $startFrom ?? now();

        // Get brand settings
        $postsPerDay = $brand->settings['posts_per_day'] ?? 1;
        $quietHours = $brand->settings['quiet_hours'] ?? [];
        $timezone = $brand->settings['timezone'] ?? 'UTC';

        // Get optimal posting times for the brand
        $optimalTimes = $this->getOptimalPostingTimes($brand);

        $currentDay = $startFrom->copy()->setTimezone($timezone);

        // Check up to 30 days ahead
        for ($i = 0; $i < 30; $i++) {
            // Get scheduled posts for this day
            $scheduledCount = $this->getScheduledPostsForDay($brand, $currentDay);

            // If we haven't reached the daily limit
            if ($scheduledCount < $postsPerDay) {
                // Find a slot
                $slot = $this->findAvailableSlotInDay(
                    $brand,
                    $currentDay,
                    $optimalTimes,
                    $quietHours
                );

                if ($slot) {
                    return $slot->setTimezone('UTC');
                }
            }

            // Try next day
            $currentDay->addDay()->startOfDay();
        }

        // Fallback: schedule 1 hour from now
        return $startFrom->copy()->addHour();
    }

    /**
     * Calculate specific slot for a given day
     *
     * @param Brand $brand
     * @param Carbon $date
     * @param int $slotIndex
     * @return Carbon
     */
    public function calculateSlot(Brand $brand, Carbon $date, int $slotIndex): Carbon
    {
        $timezone = $brand->settings['timezone'] ?? 'UTC';
        $optimalTimes = $this->getOptimalPostingTimes($brand);

        $dayStart = $date->copy()->setTimezone($timezone)->startOfDay();

        // Get the time for this slot
        if (isset($optimalTimes[$slotIndex])) {
            $time = $optimalTimes[$slotIndex];
            return $dayStart->setTimeFromTimeString($time)->setTimezone('UTC');
        }

        // Fallback: spread evenly throughout the day
        $postsPerDay = $brand->settings['posts_per_day'] ?? 1;
        $hoursApart = 24 / max($postsPerDay, 1);
        $hour = min(floor($slotIndex * $hoursApart), 23);

        return $dayStart->addHours($hour)->setTimezone('UTC');
    }

    /**
     * Get optimal posting times for a brand
     *
     * @param Brand $brand
     * @return array Array of time strings (e.g., ['09:00', '13:00', '17:00'])
     */
    public function getOptimalPostingTimes(Brand $brand): array
    {
        // Check if brand has custom optimal times
        if (isset($brand->settings['optimal_posting_times'])) {
            return $brand->settings['optimal_posting_times'];
        }

        // Use cached analytics data if available
        $cacheKey = "optimal_times_{$brand->id}";
        if (Cache::has($cacheKey)) {
            return Cache::get($cacheKey);
        }

        // Default optimal times based on research
        // These are general best practices for B2B/B2C content
        $postsPerDay = $brand->settings['posts_per_day'] ?? 1;

        $defaults = [
            1 => ['10:00'], // 10 AM
            2 => ['09:00', '15:00'], // 9 AM, 3 PM
            3 => ['09:00', '13:00', '17:00'], // 9 AM, 1 PM, 5 PM
            4 => ['08:00', '11:00', '14:00', '17:00'], // 8 AM, 11 AM, 2 PM, 5 PM
        ];

        if ($postsPerDay <= 4) {
            return $defaults[$postsPerDay];
        }

        // For more than 4 posts, spread evenly
        $times = [];
        $hoursApart = 24 / $postsPerDay;
        $startHour = 8; // Start at 8 AM

        for ($i = 0; $i < $postsPerDay; $i++) {
            $hour = $startHour + floor($i * $hoursApart);
            $hour = min($hour, 22); // Don't post after 10 PM
            $times[] = sprintf('%02d:00', $hour);
        }

        return $times;
    }

    /**
     * Find available slot within a specific day
     *
     * @param Brand $brand
     * @param Carbon $date
     * @param array $optimalTimes
     * @param array $quietHours
     * @return Carbon|null
     */
    protected function findAvailableSlotInDay(
        Brand $brand,
        Carbon $date,
        array $optimalTimes,
        array $quietHours
    ): ?Carbon {
        // Get already scheduled times for this day
        $scheduledTimes = $this->getScheduledTimesForDay($brand, $date);

        // Try each optimal time
        foreach ($optimalTimes as $time) {
            $slot = $date->copy()->setTimeFromTimeString($time);

            // Skip if in the past
            if ($slot->isPast()) {
                continue;
            }

            // Skip if in quiet hours
            if ($this->isQuietHour($slot, $quietHours)) {
                continue;
            }

            // Skip if already scheduled within 30 minutes
            if ($this->hasNearbySchedule($slot, $scheduledTimes, 30)) {
                continue;
            }

            return $slot;
        }

        // No optimal time available, find any available time
        for ($hour = 8; $hour <= 22; $hour++) {
            $slot = $date->copy()->setTime($hour, 0);

            if ($slot->isPast()) {
                continue;
            }

            if ($this->isQuietHour($slot, $quietHours)) {
                continue;
            }

            if (!$this->hasNearbySchedule($slot, $scheduledTimes, 30)) {
                return $slot;
            }
        }

        return null;
    }

    /**
     * Get count of scheduled posts for a specific day
     *
     * @param Brand $brand
     * @param Carbon $date
     * @return int
     */
    protected function getScheduledPostsForDay(Brand $brand, Carbon $date): int
    {
        $timezone = $brand->settings['timezone'] ?? 'UTC';

        $dayStart = $date->copy()->setTimezone($timezone)->startOfDay()->setTimezone('UTC');
        $dayEnd = $date->copy()->setTimezone($timezone)->endOfDay()->setTimezone('UTC');

        return PublishJob::whereHas('contentDraft', function ($query) use ($brand) {
            $query->where('brand_id', $brand->id);
        })
            ->whereIn('status', [PublishJob::SCHEDULED, PublishJob::PUBLISHING])
            ->whereBetween('scheduled_at', [$dayStart, $dayEnd])
            ->count();
    }

    /**
     * Get scheduled times for a specific day
     *
     * @param Brand $brand
     * @param Carbon $date
     * @return array Array of Carbon instances
     */
    protected function getScheduledTimesForDay(Brand $brand, Carbon $date): array
    {
        $timezone = $brand->settings['timezone'] ?? 'UTC';

        $dayStart = $date->copy()->setTimezone($timezone)->startOfDay()->setTimezone('UTC');
        $dayEnd = $date->copy()->setTimezone($timezone)->endOfDay()->setTimezone('UTC');

        return PublishJob::whereHas('contentDraft', function ($query) use ($brand) {
            $query->where('brand_id', $brand->id);
        })
            ->whereIn('status', [PublishJob::SCHEDULED, PublishJob::PUBLISHING])
            ->whereBetween('scheduled_at', [$dayStart, $dayEnd])
            ->pluck('scheduled_at')
            ->map(fn($time) => Carbon::parse($time)->setTimezone($timezone))
            ->toArray();
    }

    /**
     * Check if time is during quiet hours
     *
     * @param Carbon $time
     * @param array $quietHours
     * @return bool
     */
    protected function isQuietHour(Carbon $time, array $quietHours): bool
    {
        if (empty($quietHours)) {
            return false;
        }

        $hour = $time->hour;

        // Check if hour is in quiet hours ranges
        foreach ($quietHours as $range) {
            if (isset($range['start']) && isset($range['end'])) {
                $start = (int) explode(':', $range['start'])[0];
                $end = (int) explode(':', $range['end'])[0];

                if ($hour >= $start && $hour < $end) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Check if there's a nearby scheduled post
     *
     * @param Carbon $time
     * @param array $scheduledTimes
     * @param int $bufferMinutes
     * @return bool
     */
    protected function hasNearbySchedule(Carbon $time, array $scheduledTimes, int $bufferMinutes): bool
    {
        foreach ($scheduledTimes as $scheduledTime) {
            $diffMinutes = abs($time->diffInMinutes($scheduledTime));

            if ($diffMinutes < $bufferMinutes) {
                return true;
            }
        }

        return false;
    }

    /**
     * Get schedule preview for upcoming days
     *
     * @param Brand $brand
     * @param int $daysAhead
     * @return array
     */
    public function getSchedulePreview(Brand $brand, int $daysAhead = 7): array
    {
        $timezone = $brand->settings['timezone'] ?? 'UTC';
        $preview = [];

        for ($i = 0; $i < $daysAhead; $i++) {
            $date = now()->setTimezone($timezone)->addDays($i)->startOfDay();

            $scheduledCount = $this->getScheduledPostsForDay($brand, $date);
            $maxPosts = $brand->settings['posts_per_day'] ?? 1;
            $available = $maxPosts - $scheduledCount;

            $preview[] = [
                'date' => $date->toDateString(),
                'day_name' => $date->format('l'),
                'scheduled_posts' => $scheduledCount,
                'max_posts' => $maxPosts,
                'available_slots' => max($available, 0),
                'optimal_times' => $this->getOptimalPostingTimes($brand),
            ];
        }

        return $preview;
    }

    /**
     * Validate and suggest improvements for brand scheduling settings
     *
     * @param Brand $brand
     * @return array Suggestions
     */
    public function validateSchedulingSettings(Brand $brand): array
    {
        $suggestions = [];
        $settings = $brand->settings;

        // Check posts per day
        $postsPerDay = $settings['posts_per_day'] ?? 1;
        if ($postsPerDay > 5) {
            $suggestions[] = [
                'type' => 'warning',
                'message' => 'More than 5 posts per day may appear spammy to your audience.',
            ];
        }

        // Check quiet hours
        if (empty($settings['quiet_hours'])) {
            $suggestions[] = [
                'type' => 'info',
                'message' => 'Consider setting quiet hours to avoid posting during off-hours.',
            ];
        }

        // Check timezone
        if (!isset($settings['timezone'])) {
            $suggestions[] = [
                'type' => 'warning',
                'message' => 'No timezone set. Using UTC by default.',
            ];
        }

        // Check optimal posting times
        if (!isset($settings['optimal_posting_times'])) {
            $suggestions[] = [
                'type' => 'info',
                'message' => 'Using default optimal posting times. Consider customizing based on your audience analytics.',
            ];
        }

        return $suggestions;
    }
}
