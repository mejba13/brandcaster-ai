<?php

namespace App\Console\Commands;

use App\Models\Brand;
use App\Services\TopicDiscovery\TopicDiscoveryService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * Discover Topics Command
 *
 * Artisan command to manually trigger topic discovery for brands.
 */
class DiscoverTopics extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'topics:discover
                            {--brand= : Brand slug to discover topics for (optional)}
                            {--limit=10 : Number of topics to discover per category}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Discover trending topics for content generation';

    /**
     * Execute the console command.
     *
     * @param TopicDiscoveryService $discoveryService
     * @return int
     */
    public function handle(TopicDiscoveryService $discoveryService): int
    {
        $brandSlug = $this->option('brand');
        $limit = (int) $this->option('limit');

        $this->info('Starting topic discovery...');
        $this->newLine();

        try {
            if ($brandSlug) {
                return $this->discoverForBrand($brandSlug, $limit, $discoveryService);
            } else {
                return $this->discoverForAllBrands($limit, $discoveryService);
            }
        } catch (\Exception $e) {
            $this->error('Failed to discover topics: ' . $e->getMessage());
            Log::error('Topic discovery command failed', [
                'brand_slug' => $brandSlug,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return Command::FAILURE;
        }
    }

    /**
     * Discover topics for a specific brand
     *
     * @param string $brandSlug
     * @param int $limit
     * @param TopicDiscoveryService $discoveryService
     * @return int
     */
    protected function discoverForBrand(string $brandSlug, int $limit, TopicDiscoveryService $discoveryService): int
    {
        $brand = Brand::where('slug', $brandSlug)->first();

        if (!$brand) {
            $this->error("Brand not found: {$brandSlug}");
            return Command::FAILURE;
        }

        $this->info("Discovering topics for brand: {$brand->name}");
        $this->newLine();

        $categories = $brand->categories()->active()->get();

        if ($categories->isEmpty()) {
            $this->warn("No active categories found for brand: {$brand->name}");
            return Command::SUCCESS;
        }

        $progressBar = $this->output->createProgressBar($categories->count());
        $progressBar->setFormat('[%bar%] %current%/%max% categories - %message%');
        $progressBar->setMessage('Starting...');
        $progressBar->start();

        $totalDiscovered = 0;

        foreach ($categories as $category) {
            $progressBar->setMessage("Discovering: {$category->name}");

            try {
                $discovered = $discoveryService->discoverForCategory($category, $limit);
                $totalDiscovered += $discovered;
            } catch (\Exception $e) {
                Log::error('Failed to discover topics for category', [
                    'category_id' => $category->id,
                    'error' => $e->getMessage(),
                ]);
            }

            $progressBar->advance();
        }

        $progressBar->setMessage('Complete!');
        $progressBar->finish();
        $this->newLine(2);

        $this->displaySummary([
            [
                'Brand' => $brand->name,
                'Categories' => $categories->count(),
                'Topics Discovered' => $totalDiscovered,
            ],
        ]);

        return Command::SUCCESS;
    }

    /**
     * Discover topics for all active brands
     *
     * @param int $limit
     * @param TopicDiscoveryService $discoveryService
     * @return int
     */
    protected function discoverForAllBrands(int $limit, TopicDiscoveryService $discoveryService): int
    {
        $brands = Brand::active()->get();

        if ($brands->isEmpty()) {
            $this->warn('No active brands found.');
            return Command::SUCCESS;
        }

        $this->info("Discovering topics for {$brands->count()} brands");
        $this->newLine();

        $progressBar = $this->output->createProgressBar($brands->count());
        $progressBar->setFormat('[%bar%] %current%/%max% brands - %message%');
        $progressBar->setMessage('Starting...');
        $progressBar->start();

        $summary = [];
        $totalTopics = 0;

        foreach ($brands as $brand) {
            $progressBar->setMessage("Processing: {$brand->name}");

            try {
                $categories = $brand->categories()->active()->get();
                $discovered = 0;

                foreach ($categories as $category) {
                    try {
                        $discovered += $discoveryService->discoverForCategory($category, $limit);
                    } catch (\Exception $e) {
                        Log::error('Failed to discover topics for category', [
                            'brand_id' => $brand->id,
                            'category_id' => $category->id,
                            'error' => $e->getMessage(),
                        ]);
                    }
                }

                $totalTopics += $discovered;

                $summary[] = [
                    'Brand' => $brand->name,
                    'Categories' => $categories->count(),
                    'Topics Discovered' => $discovered,
                ];

            } catch (\Exception $e) {
                $this->error("Failed to process brand: {$brand->name}");
                Log::error('Failed to discover topics for brand', [
                    'brand_id' => $brand->id,
                    'error' => $e->getMessage(),
                ]);

                $summary[] = [
                    'Brand' => $brand->name,
                    'Categories' => 'Error',
                    'Topics Discovered' => 0,
                ];
            }

            $progressBar->advance();
        }

        $progressBar->setMessage('Complete!');
        $progressBar->finish();
        $this->newLine(2);

        $this->displaySummary($summary);

        $this->newLine();
        $this->info("Total topics discovered: {$totalTopics}");

        return Command::SUCCESS;
    }

    /**
     * Display summary table
     *
     * @param array $summary
     * @return void
     */
    protected function displaySummary(array $summary): void
    {
        $this->info('Summary:');
        $this->table(
            ['Brand', 'Categories', 'Topics Discovered'],
            array_map(function ($row) {
                return [
                    $row['Brand'],
                    $row['Categories'],
                    $row['Topics Discovered'],
                ];
            }, $summary)
        );
    }
}
