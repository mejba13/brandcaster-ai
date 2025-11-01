<?php

namespace App\Jobs;

use App\Models\Brand;
use App\Services\TopicDiscovery\TopicDiscoveryService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Discover Topics Job
 *
 * Discovers trending topics for brands using the TopicDiscoveryService.
 * Can be run for a specific brand or all active brands.
 */
class DiscoverTopicsJob implements ShouldQueue
{
    use Queueable, InteractsWithQueue, SerializesModels;

    /**
     * The brand to discover topics for (null for all brands)
     *
     * @var Brand|null
     */
    protected ?Brand $brand;

    /**
     * Number of topics to discover per category
     *
     * @var int
     */
    protected int $topicsPerCategory;

    /**
     * The number of times the job may be attempted
     *
     * @var int
     */
    public int $tries = 3;

    /**
     * The number of seconds to wait before retrying the job
     *
     * @var int
     */
    public int $backoff = 300; // 5 minutes

    /**
     * The maximum number of seconds the job can run
     *
     * @var int
     */
    public int $timeout = 900; // 15 minutes

    /**
     * Create a new job instance
     *
     * @param Brand|null $brand Brand to discover for (null = all brands)
     * @param int $topicsPerCategory Number of topics per category
     */
    public function __construct(?Brand $brand = null, int $topicsPerCategory = 10)
    {
        $this->brand = $brand;
        $this->topicsPerCategory = $topicsPerCategory;
    }

    /**
     * Execute the job
     *
     * @param TopicDiscoveryService $discoveryService
     * @return void
     */
    public function handle(TopicDiscoveryService $discoveryService): void
    {
        try {
            if ($this->brand) {
                $this->discoverForBrand($this->brand, $discoveryService);
            } else {
                $this->discoverForAllBrands($discoveryService);
            }
        } catch (\Exception $e) {
            Log::error('Failed to discover topics in job', [
                'brand_id' => $this->brand?->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            // Re-throw to trigger retry mechanism
            throw $e;
        }
    }

    /**
     * Discover topics for a specific brand
     *
     * @param Brand $brand
     * @param TopicDiscoveryService $discoveryService
     * @return void
     */
    protected function discoverForBrand(Brand $brand, TopicDiscoveryService $discoveryService): void
    {
        Log::info('Starting topic discovery job for brand', [
            'brand_id' => $brand->id,
            'brand_name' => $brand->name,
            'topics_per_category' => $this->topicsPerCategory,
        ]);

        try {
            $discovered = $discoveryService->discoverForBrand($brand, $this->topicsPerCategory);

            Log::info('Completed topic discovery job for brand', [
                'brand_id' => $brand->id,
                'brand_name' => $brand->name,
                'topics_discovered' => $discovered,
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to discover topics for brand in job', [
                'brand_id' => $brand->id,
                'brand_name' => $brand->name,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * Discover topics for all active brands
     *
     * @param TopicDiscoveryService $discoveryService
     * @return void
     */
    protected function discoverForAllBrands(TopicDiscoveryService $discoveryService): void
    {
        Log::info('Starting topic discovery job for all active brands', [
            'topics_per_category' => $this->topicsPerCategory,
        ]);

        $brands = Brand::active()->get();
        $totalDiscovered = 0;
        $successCount = 0;
        $failureCount = 0;

        foreach ($brands as $brand) {
            try {
                $discovered = $discoveryService->discoverForBrand($brand, $this->topicsPerCategory);
                $totalDiscovered += $discovered;
                $successCount++;

                Log::info('Discovered topics for brand', [
                    'brand_id' => $brand->id,
                    'brand_name' => $brand->name,
                    'topics_discovered' => $discovered,
                ]);

            } catch (\Exception $e) {
                $failureCount++;

                Log::error('Failed to discover topics for brand', [
                    'brand_id' => $brand->id,
                    'brand_name' => $brand->name,
                    'error' => $e->getMessage(),
                ]);

                // Continue with other brands even if one fails
            }
        }

        Log::info('Completed topic discovery job for all brands', [
            'total_brands' => $brands->count(),
            'successful' => $successCount,
            'failed' => $failureCount,
            'total_topics_discovered' => $totalDiscovered,
        ]);
    }

    /**
     * Handle a job failure
     *
     * @param \Throwable $exception
     * @return void
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('Topic discovery job failed after all retries', [
            'brand_id' => $this->brand?->id,
            'brand_name' => $this->brand?->name,
            'topics_per_category' => $this->topicsPerCategory,
            'error' => $exception->getMessage(),
            'trace' => $exception->getTraceAsString(),
        ]);

        // You could send a notification to administrators here
    }

    /**
     * Get the tags that should be assigned to the job
     *
     * @return array
     */
    public function tags(): array
    {
        $tags = ['topic-discovery'];

        if ($this->brand) {
            $tags[] = "brand:{$this->brand->id}";
            $tags[] = "brand-slug:{$this->brand->slug}";
        } else {
            $tags[] = 'all-brands';
        }

        return $tags;
    }
}
