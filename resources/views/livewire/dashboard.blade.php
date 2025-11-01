<?php

use App\Models\Brand;
use App\Models\ContentDraft;
use App\Models\Topic;
use App\Models\PublishJob;
use App\Services\Publishing\PublishingEngine;
use Livewire\Volt\Component;

new class extends Component {
    public $selectedBrand = null;
    public $stats = [];
    public $recentDrafts = [];
    public $upcomingPublishes = [];
    public $topTopics = [];

    public function mount()
    {
        $this->loadBrands();
        $this->loadStats();
    }

    public function loadBrands()
    {
        return Brand::active()
            ->where(function ($query) {
                // If not super admin, only show brands user has access to
                if (!auth()->user()->hasRole('super_admin')) {
                    $query->whereHas('users', function ($q) {
                        $q->where('user_id', auth()->id());
                    });
                }
            })
            ->get();
    }

    public function selectBrand($brandId)
    {
        $this->selectedBrand = $brandId;
        $this->loadStats();
    }

    public function loadStats()
    {
        $query = ContentDraft::query();

        if ($this->selectedBrand) {
            $query->where('brand_id', $this->selectedBrand);
        }

        $this->stats = [
            'pending_review' => $query->clone()->where('status', ContentDraft::STATUS_PENDING_REVIEW)->count(),
            'approved' => $query->clone()->where('status', ContentDraft::STATUS_APPROVED)->count(),
            'published_today' => $query->clone()
                ->where('status', ContentDraft::STATUS_PUBLISHED)
                ->whereDate('published_at', today())
                ->count(),
            'topics_discovered' => Topic::when($this->selectedBrand, fn($q) => $q->where('brand_id', $this->selectedBrand))
                ->where('status', Topic::STATUS_DISCOVERED)
                ->count(),
        ];

        // Recent drafts
        $this->recentDrafts = ContentDraft::with('brand', 'category')
            ->when($this->selectedBrand, fn($q) => $q->where('brand_id', $this->selectedBrand))
            ->where('status', ContentDraft::STATUS_PENDING_REVIEW)
            ->latest()
            ->take(5)
            ->get();

        // Upcoming publishes
        $this->upcomingPublishes = PublishJob::with('contentDraft.brand')
            ->whereHas('contentDraft', function ($q) {
                if ($this->selectedBrand) {
                    $q->where('brand_id', $this->selectedBrand);
                }
            })
            ->where('status', PublishJob::STATUS_PENDING)
            ->where('scheduled_at', '>', now())
            ->orderBy('scheduled_at')
            ->take(5)
            ->get();

        // Top topics
        $this->topTopics = Topic::with('category')
            ->when($this->selectedBrand, fn($q) => $q->where('brand_id', $this->selectedBrand))
            ->where('status', Topic::STATUS_DISCOVERED)
            ->orderBy('confidence_score', 'desc')
            ->take(5)
            ->get();
    }

    public function quickApprove($draftId)
    {
        $draft = ContentDraft::findOrFail($draftId);

        // Check permissions
        if (!auth()->user()->can('approve content', $draft)) {
            $this->addError('approve', 'You do not have permission to approve this content');
            return;
        }

        $draft->update([
            'status' => ContentDraft::STATUS_APPROVED,
            'approved_by' => auth()->id(),
            'approved_at' => now(),
        ]);

        $this->loadStats();

        $this->dispatch('notify', [
            'type' => 'success',
            'message' => 'Content approved successfully',
        ]);
    }
}; ?>

<div class="space-y-6">
    <!-- Header -->
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-3xl font-bold text-gray-900 dark:text-white">Dashboard</h1>
            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                Overview of your content generation and publishing activity
            </p>
        </div>

        <!-- Brand Selector -->
        <div class="w-64">
            <label for="brand-select" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                Filter by Brand
            </label>
            <select
                id="brand-select"
                wire:model.live="selectedBrand"
                class="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:bg-gray-800 dark:border-gray-700 dark:text-white"
            >
                <option value="">All Brands</option>
                @foreach($this->loadBrands() as $brand)
                    <option value="{{ $brand->id }}">{{ $brand->name }}</option>
                @endforeach
            </select>
        </div>
    </div>

    <!-- Stats Grid -->
    <div class="grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-4">
        <!-- Pending Review -->
        <div class="bg-white dark:bg-gray-800 overflow-hidden shadow rounded-lg">
            <div class="p-5">
                <div class="flex items-center">
                    <div class="flex-shrink-0">
                        <svg class="h-6 w-6 text-orange-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"></path>
                        </svg>
                    </div>
                    <div class="ml-5 w-0 flex-1">
                        <dl>
                            <dt class="text-sm font-medium text-gray-500 dark:text-gray-400 truncate">
                                Pending Review
                            </dt>
                            <dd class="text-2xl font-semibold text-gray-900 dark:text-white">
                                {{ $stats['pending_review'] }}
                            </dd>
                        </dl>
                    </div>
                </div>
            </div>
        </div>

        <!-- Approved -->
        <div class="bg-white dark:bg-gray-800 overflow-hidden shadow rounded-lg">
            <div class="p-5">
                <div class="flex items-center">
                    <div class="flex-shrink-0">
                        <svg class="h-6 w-6 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                    </div>
                    <div class="ml-5 w-0 flex-1">
                        <dl>
                            <dt class="text-sm font-medium text-gray-500 dark:text-gray-400 truncate">
                                Approved
                            </dt>
                            <dd class="text-2xl font-semibold text-gray-900 dark:text-white">
                                {{ $stats['approved'] }}
                            </dd>
                        </dl>
                    </div>
                </div>
            </div>
        </div>

        <!-- Published Today -->
        <div class="bg-white dark:bg-gray-800 overflow-hidden shadow rounded-lg">
            <div class="p-5">
                <div class="flex items-center">
                    <div class="flex-shrink-0">
                        <svg class="h-6 w-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path>
                        </svg>
                    </div>
                    <div class="ml-5 w-0 flex-1">
                        <dl>
                            <dt class="text-sm font-medium text-gray-500 dark:text-gray-400 truncate">
                                Published Today
                            </dt>
                            <dd class="text-2xl font-semibold text-gray-900 dark:text-white">
                                {{ $stats['published_today'] }}
                            </dd>
                        </dl>
                    </div>
                </div>
            </div>
        </div>

        <!-- Topics Discovered -->
        <div class="bg-white dark:bg-gray-800 overflow-hidden shadow rounded-lg">
            <div class="p-5">
                <div class="flex items-center">
                    <div class="flex-shrink-0">
                        <svg class="h-6 w-6 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 20l4-16m2 16l4-16M6 9h14M4 15h14"></path>
                        </svg>
                    </div>
                    <div class="ml-5 w-0 flex-1">
                        <dl>
                            <dt class="text-sm font-medium text-gray-500 dark:text-gray-400 truncate">
                                Topics Discovered
                            </dt>
                            <dd class="text-2xl font-semibold text-gray-900 dark:text-white">
                                {{ $stats['topics_discovered'] }}
                            </dd>
                        </dl>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Two Column Layout -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <!-- Pending Review -->
        <div class="bg-white dark:bg-gray-800 shadow rounded-lg">
            <div class="px-4 py-5 border-b border-gray-200 dark:border-gray-700 sm:px-6">
                <h3 class="text-lg leading-6 font-medium text-gray-900 dark:text-white">
                    Pending Review
                </h3>
            </div>
            <ul class="divide-y divide-gray-200 dark:divide-gray-700">
                @forelse($recentDrafts as $draft)
                    <li class="px-4 py-4 sm:px-6 hover:bg-gray-50 dark:hover:bg-gray-700">
                        <div class="flex items-center justify-between">
                            <div class="flex-1 min-w-0">
                                <p class="text-sm font-medium text-gray-900 dark:text-white truncate">
                                    {{ $draft->title }}
                                </p>
                                <p class="text-sm text-gray-500 dark:text-gray-400">
                                    {{ $draft->brand->name }} • {{ $draft->category->name }}
                                </p>
                                <p class="text-xs text-gray-400 dark:text-gray-500 mt-1">
                                    Score: {{ number_format($draft->confidence_score * 100, 1) }}%
                                </p>
                            </div>
                            <div class="ml-4 flex-shrink-0 flex space-x-2">
                                <button
                                    wire:click="quickApprove({{ $draft->id }})"
                                    class="inline-flex items-center px-3 py-1.5 border border-transparent text-xs font-medium rounded-md text-white bg-green-600 hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500"
                                >
                                    Approve
                                </button>
                                <a
                                    href="{{ route('drafts.show', $draft) }}"
                                    class="inline-flex items-center px-3 py-1.5 border border-gray-300 dark:border-gray-600 text-xs font-medium rounded-md text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-800 hover:bg-gray-50 dark:hover:bg-gray-700"
                                >
                                    Review
                                </a>
                            </div>
                        </div>
                    </li>
                @empty
                    <li class="px-4 py-12 text-center">
                        <p class="text-sm text-gray-500 dark:text-gray-400">No drafts pending review</p>
                    </li>
                @endforelse
            </ul>
            @if($recentDrafts->count() > 0)
                <div class="px-4 py-3 bg-gray-50 dark:bg-gray-900 text-right sm:px-6">
                    <a href="{{ route('drafts.index') }}" class="text-sm font-medium text-indigo-600 hover:text-indigo-500 dark:text-indigo-400">
                        View all drafts →
                    </a>
                </div>
            @endif
        </div>

        <!-- Upcoming Publishes -->
        <div class="bg-white dark:bg-gray-800 shadow rounded-lg">
            <div class="px-4 py-5 border-b border-gray-200 dark:border-gray-700 sm:px-6">
                <h3 class="text-lg leading-6 font-medium text-gray-900 dark:text-white">
                    Upcoming Publishes
                </h3>
            </div>
            <ul class="divide-y divide-gray-200 dark:divide-gray-700">
                @forelse($upcomingPublishes as $job)
                    <li class="px-4 py-4 sm:px-6">
                        <div class="flex items-center justify-between">
                            <div class="flex-1 min-w-0">
                                <p class="text-sm font-medium text-gray-900 dark:text-white truncate">
                                    {{ $job->contentDraft->title }}
                                </p>
                                <p class="text-sm text-gray-500 dark:text-gray-400">
                                    {{ ucfirst($job->platform) }} • {{ $job->contentDraft->brand->name }}
                                </p>
                                <p class="text-xs text-gray-400 dark:text-gray-500 mt-1">
                                    {{ $job->scheduled_at->diffForHumans() }}
                                </p>
                            </div>
                            <div class="ml-4 flex-shrink-0">
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200">
                                    Scheduled
                                </span>
                            </div>
                        </div>
                    </li>
                @empty
                    <li class="px-4 py-12 text-center">
                        <p class="text-sm text-gray-500 dark:text-gray-400">No scheduled publishes</p>
                    </li>
                @endforelse
            </ul>
        </div>
    </div>

    <!-- Top Topics -->
    <div class="bg-white dark:bg-gray-800 shadow rounded-lg">
        <div class="px-4 py-5 border-b border-gray-200 dark:border-gray-700 sm:px-6">
            <h3 class="text-lg leading-6 font-medium text-gray-900 dark:text-white">
                Top Trending Topics
            </h3>
        </div>
        <ul class="divide-y divide-gray-200 dark:divide-gray-700">
            @forelse($topTopics as $topic)
                <li class="px-4 py-4 sm:px-6 hover:bg-gray-50 dark:hover:bg-gray-700">
                    <div class="flex items-center justify-between">
                        <div class="flex-1 min-w-0">
                            <p class="text-sm font-medium text-gray-900 dark:text-white">
                                {{ $topic->title }}
                            </p>
                            <p class="text-sm text-gray-500 dark:text-gray-400">
                                {{ $topic->category->name }} • Score: {{ number_format($topic->confidence_score * 100, 1) }}%
                            </p>
                        </div>
                        <div class="ml-4 flex-shrink-0">
                            <a
                                href="{{ route('topics.show', $topic) }}"
                                class="text-sm font-medium text-indigo-600 hover:text-indigo-500 dark:text-indigo-400"
                            >
                                View →
                            </a>
                        </div>
                    </div>
                </li>
            @empty
                <li class="px-4 py-12 text-center">
                    <p class="text-sm text-gray-500 dark:text-gray-400">No topics discovered yet</p>
                </li>
            @endforelse
        </ul>
    </div>
</div>
