<?php

use App\Models\Brand;
use App\Services\Analytics\AnalyticsService;
use Livewire\Volt\Component;

new class extends Component {
    public $selectedBrand = null;
    public $dateRange = 30;

    public $overviewStats = [];
    public $performanceChart = [];
    public $engagementChart = [];
    public $platformPerformance = [];
    public $categoryPerformance = [];
    public $topContent = [];

    public function mount()
    {
        $brands = $this->getBrands();

        if ($brands->isNotEmpty()) {
            $this->selectedBrand = $brands->first()->id;
        }

        $this->loadAnalytics();
    }

    public function getBrands()
    {
        return Brand::active()
            ->when(!auth()->user()->hasRole('super_admin'), function ($query) {
                $query->whereHas('users', function ($q) {
                    $q->where('user_id', auth()->id());
                });
            })
            ->get();
    }

    public function updatedSelectedBrand()
    {
        $this->loadAnalytics();
    }

    public function updatedDateRange()
    {
        $this->loadAnalytics();
    }

    public function loadAnalytics()
    {
        if (!$this->selectedBrand) {
            return;
        }

        $brand = Brand::findOrFail($this->selectedBrand);
        $analyticsService = app(AnalyticsService::class);

        $this->overviewStats = $analyticsService->getOverviewStats($brand, $this->dateRange);
        $this->performanceChart = $analyticsService->getContentPerformanceChart($brand, $this->dateRange);
        $this->engagementChart = $analyticsService->getEngagementTrendsChart($brand, $this->dateRange);
        $this->platformPerformance = $analyticsService->getPlatformPerformance($brand, $this->dateRange);
        $this->categoryPerformance = $analyticsService->getCategoryPerformance($brand, $this->dateRange);
        $this->topContent = $analyticsService->getTopPerformingContent($brand, 10, $this->dateRange);
    }
}; ?>

<div class="space-y-6">
    <!-- Header -->
    <div class="sm:flex sm:items-center sm:justify-between">
        <div>
            <h1 class="text-3xl font-bold text-gray-900 dark:text-white">Analytics</h1>
            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                Content performance and engagement insights
            </p>
        </div>

        <div class="mt-4 sm:mt-0 flex space-x-3">
            <!-- Date Range Selector -->
            <select
                wire:model.live="dateRange"
                class="rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:bg-gray-800 dark:border-gray-700 dark:text-white"
            >
                <option value="7">Last 7 days</option>
                <option value="30">Last 30 days</option>
                <option value="90">Last 90 days</option>
            </select>

            <!-- Brand Selector -->
            <select
                wire:model.live="selectedBrand"
                class="rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:bg-gray-800 dark:border-gray-700 dark:text-white"
            >
                @foreach($this->getBrands() as $brand)
                    <option value="{{ $brand->id }}">{{ $brand->name }}</option>
                @endforeach
            </select>
        </div>
    </div>

    <!-- Overview Stats Grid -->
    <div class="grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-4">
        <!-- Total Generated -->
        <div class="bg-white dark:bg-gray-800 overflow-hidden shadow rounded-lg">
            <div class="p-5">
                <div class="flex items-center">
                    <div class="flex-shrink-0">
                        <svg class="h-6 w-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                        </svg>
                    </div>
                    <div class="ml-5 w-0 flex-1">
                        <dl>
                            <dt class="text-sm font-medium text-gray-500 dark:text-gray-400 truncate">
                                Content Generated
                            </dt>
                            <dd class="text-2xl font-semibold text-gray-900 dark:text-white">
                                {{ $overviewStats['content']['total_generated'] ?? 0 }}
                            </dd>
                            <dd class="text-sm text-gray-500 dark:text-gray-400">
                                {{ $overviewStats['content']['approval_rate'] ?? 0 }}% approval rate
                            </dd>
                        </dl>
                    </div>
                </div>
            </div>
        </div>

        <!-- Published -->
        <div class="bg-white dark:bg-gray-800 overflow-hidden shadow rounded-lg">
            <div class="p-5">
                <div class="flex items-center">
                    <div class="flex-shrink-0">
                        <svg class="h-6 w-6 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                        </svg>
                    </div>
                    <div class="ml-5 w-0 flex-1">
                        <dl>
                            <dt class="text-sm font-medium text-gray-500 dark:text-gray-400 truncate">
                                Published
                            </dt>
                            <dd class="text-2xl font-semibold text-gray-900 dark:text-white">
                                {{ $overviewStats['publishing']['published'] ?? 0 }}
                            </dd>
                            <dd class="text-sm text-gray-500 dark:text-gray-400">
                                {{ $overviewStats['publishing']['success_rate'] ?? 0 }}% success rate
                            </dd>
                        </dl>
                    </div>
                </div>
            </div>
        </div>

        <!-- Total Impressions -->
        <div class="bg-white dark:bg-gray-800 overflow-hidden shadow rounded-lg">
            <div class="p-5">
                <div class="flex items-center">
                    <div class="flex-shrink-0">
                        <svg class="h-6 w-6 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path>
                        </svg>
                    </div>
                    <div class="ml-5 w-0 flex-1">
                        <dl>
                            <dt class="text-sm font-medium text-gray-500 dark:text-gray-400 truncate">
                                Impressions
                            </dt>
                            <dd class="text-2xl font-semibold text-gray-900 dark:text-white">
                                {{ number_format($overviewStats['engagement']['total_impressions'] ?? 0) }}
                            </dd>
                            <dd class="text-sm text-gray-500 dark:text-gray-400">
                                {{ $overviewStats['engagement']['ctr'] ?? 0 }}% CTR
                            </dd>
                        </dl>
                    </div>
                </div>
            </div>
        </div>

        <!-- Engagement Rate -->
        <div class="bg-white dark:bg-gray-800 overflow-hidden shadow rounded-lg">
            <div class="p-5">
                <div class="flex items-center">
                    <div class="flex-shrink-0">
                        <svg class="h-6 w-6 text-pink-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z"></path>
                        </svg>
                    </div>
                    <div class="ml-5 w-0 flex-1">
                        <dl>
                            <dt class="text-sm font-medium text-gray-500 dark:text-gray-400 truncate">
                                Engagement Rate
                            </dt>
                            <dd class="text-2xl font-semibold text-gray-900 dark:text-white">
                                {{ $overviewStats['engagement']['avg_engagement_rate'] ?? 0 }}%
                            </dd>
                            <dd class="text-sm text-gray-500 dark:text-gray-400">
                                {{ number_format($overviewStats['engagement']['total_likes'] ?? 0) }} likes
                            </dd>
                        </dl>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Charts Row -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <!-- Content Performance Chart -->
        <div class="bg-white dark:bg-gray-800 shadow rounded-lg p-6">
            <h3 class="text-lg leading-6 font-medium text-gray-900 dark:text-white mb-4">
                Content Performance
            </h3>
            <div class="h-64">
                <canvas id="performanceChart"></canvas>
            </div>
        </div>

        <!-- Engagement Trends Chart -->
        <div class="bg-white dark:bg-gray-800 shadow rounded-lg p-6">
            <h3 class="text-lg leading-6 font-medium text-gray-900 dark:text-white mb-4">
                Engagement Trends
            </h3>
            <div class="h-64">
                <canvas id="engagementChart"></canvas>
            </div>
        </div>
    </div>

    <!-- Platform Performance -->
    <div class="bg-white dark:bg-gray-800 shadow rounded-lg">
        <div class="px-4 py-5 border-b border-gray-200 dark:border-gray-700 sm:px-6">
            <h3 class="text-lg leading-6 font-medium text-gray-900 dark:text-white">
                Platform Performance
            </h3>
        </div>
        <div class="px-4 py-5 sm:p-6">
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
                @foreach($platformPerformance as $platform => $stats)
                    <div class="border border-gray-200 dark:border-gray-700 rounded-lg p-4">
                        <div class="flex items-center justify-between mb-3">
                            <h4 class="text-sm font-medium text-gray-900 dark:text-white capitalize">
                                {{ $platform }}
                            </h4>
                            <span class="text-xs text-gray-500 dark:text-gray-400">
                                {{ $stats['total_posts'] }} posts
                            </span>
                        </div>
                        <div class="space-y-2">
                            <div class="flex justify-between text-sm">
                                <span class="text-gray-500 dark:text-gray-400">Impressions</span>
                                <span class="font-medium text-gray-900 dark:text-white">{{ number_format($stats['impressions']) }}</span>
                            </div>
                            <div class="flex justify-between text-sm">
                                <span class="text-gray-500 dark:text-gray-400">Clicks</span>
                                <span class="font-medium text-gray-900 dark:text-white">{{ number_format($stats['clicks']) }}</span>
                            </div>
                            <div class="flex justify-between text-sm">
                                <span class="text-gray-500 dark:text-gray-400">CTR</span>
                                <span class="font-medium text-gray-900 dark:text-white">{{ $stats['ctr'] }}%</span>
                            </div>
                            <div class="flex justify-between text-sm">
                                <span class="text-gray-500 dark:text-gray-400">Engagement Rate</span>
                                <span class="font-medium text-gray-900 dark:text-white">{{ $stats['engagement_rate'] }}%</span>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    </div>

    <!-- Two Column Layout -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <!-- Category Performance -->
        <div class="bg-white dark:bg-gray-800 shadow rounded-lg">
            <div class="px-4 py-5 border-b border-gray-200 dark:border-gray-700 sm:px-6">
                <h3 class="text-lg leading-6 font-medium text-gray-900 dark:text-white">
                    Category Performance
                </h3>
            </div>
            <div class="overflow-hidden">
                <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                    <thead class="bg-gray-50 dark:bg-gray-900">
                        <tr>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">
                                Category
                            </th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">
                                Posts
                            </th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">
                                Publish Rate
                            </th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">
                                Avg Score
                            </th>
                        </tr>
                    </thead>
                    <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                        @forelse($categoryPerformance as $category)
                            <tr>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900 dark:text-white">
                                    {{ $category['category'] }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                                    {{ $category['total_posts'] }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                                    {{ $category['publish_rate'] }}%
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                                    {{ $category['avg_score'] }}%
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="px-6 py-4 text-center text-sm text-gray-500 dark:text-gray-400">
                                    No data available
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Top Performing Content -->
        <div class="bg-white dark:bg-gray-800 shadow rounded-lg">
            <div class="px-4 py-5 border-b border-gray-200 dark:border-gray-700 sm:px-6">
                <h3 class="text-lg leading-6 font-medium text-gray-900 dark:text-white">
                    Top Performing Content
                </h3>
            </div>
            <ul class="divide-y divide-gray-200 dark:divide-gray-700">
                @forelse($topContent as $content)
                    <li class="px-4 py-4 sm:px-6">
                        <div class="flex items-center justify-between">
                            <div class="flex-1 min-w-0">
                                <p class="text-sm font-medium text-gray-900 dark:text-white truncate">
                                    {{ $content->title }}
                                </p>
                                <p class="text-sm text-gray-500 dark:text-gray-400">
                                    {{ $content->category->name }}
                                </p>
                            </div>
                            <div class="ml-4 flex-shrink-0 text-right">
                                <p class="text-sm font-medium text-gray-900 dark:text-white">
                                    {{ number_format($content->total_impressions ?? 0) }}
                                </p>
                                <p class="text-xs text-gray-500 dark:text-gray-400">
                                    impressions
                                </p>
                            </div>
                        </div>
                    </li>
                @empty
                    <li class="px-4 py-12 text-center">
                        <p class="text-sm text-gray-500 dark:text-gray-400">No published content yet</p>
                    </li>
                @endforelse
            </ul>
        </div>
    </div>
</div>

@script
<script>
    // Chart.js initialization would go here in production
    // For now, this is a placeholder for the actual chart implementation
</script>
@endscript
