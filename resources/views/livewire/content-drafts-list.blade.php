<?php

use App\Models\Brand;
use App\Models\ContentDraft;
use App\Services\Publishing\PublishingEngine;
use Livewire\Volt\Component;
use Livewire\WithPagination;

new class extends Component {
    use WithPagination;

    public $statusFilter = '';
    public $brandFilter = '';
    public $search = '';
    public $sortBy = 'created_at';
    public $sortDirection = 'desc';

    public function mount()
    {
        //
    }

    public function with()
    {
        return [
            'drafts' => $this->getDrafts(),
            'brands' => $this->getBrands(),
            'statusCounts' => $this->getStatusCounts(),
        ];
    }

    public function getDrafts()
    {
        return ContentDraft::with(['brand', 'category', 'topic'])
            ->when($this->search, function ($query) {
                $query->where('title', 'like', '%' . $this->search . '%');
            })
            ->when($this->statusFilter, function ($query) {
                $query->where('status', $this->statusFilter);
            })
            ->when($this->brandFilter, function ($query) {
                $query->where('brand_id', $this->brandFilter);
            })
            ->when(!auth()->user()->hasRole('super_admin'), function ($query) {
                $query->whereHas('brand.users', function ($q) {
                    $q->where('user_id', auth()->id());
                });
            })
            ->orderBy($this->sortBy, $this->sortDirection)
            ->paginate(15);
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

    public function getStatusCounts()
    {
        $query = ContentDraft::query()
            ->when(!auth()->user()->hasRole('super_admin'), function ($query) {
                $query->whereHas('brand.users', function ($q) {
                    $q->where('user_id', auth()->id());
                });
            });

        return [
            'all' => $query->clone()->count(),
            'pending_review' => $query->clone()->where('status', ContentDraft::STATUS_PENDING_REVIEW)->count(),
            'approved' => $query->clone()->where('status', ContentDraft::STATUS_APPROVED)->count(),
            'published' => $query->clone()->where('status', ContentDraft::STATUS_PUBLISHED)->count(),
            'rejected' => $query->clone()->where('status', ContentDraft::STATUS_REJECTED)->count(),
        ];
    }

    public function sortBy($field)
    {
        if ($this->sortBy === $field) {
            $this->sortDirection = $this->sortDirection === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sortBy = $field;
            $this->sortDirection = 'asc';
        }
    }

    public function approve($draftId)
    {
        $draft = ContentDraft::findOrFail($draftId);

        if (!auth()->user()->can('approve content')) {
            $this->dispatch('notify', [
                'type' => 'error',
                'message' => 'You do not have permission to approve content',
            ]);
            return;
        }

        $draft->update([
            'status' => ContentDraft::STATUS_APPROVED,
            'approved_by' => auth()->id(),
            'approved_at' => now(),
        ]);

        $this->dispatch('notify', [
            'type' => 'success',
            'message' => 'Content approved successfully',
        ]);
    }

    public function reject($draftId)
    {
        $draft = ContentDraft::findOrFail($draftId);

        if (!auth()->user()->can('approve content')) {
            $this->dispatch('notify', [
                'type' => 'error',
                'message' => 'You do not have permission to reject content',
            ]);
            return;
        }

        $draft->update([
            'status' => ContentDraft::STATUS_REJECTED,
        ]);

        $this->dispatch('notify', [
            'type' => 'success',
            'message' => 'Content rejected',
        ]);
    }

    public function publishNow($draftId)
    {
        $draft = ContentDraft::findOrFail($draftId);

        if (!auth()->user()->can('publish content')) {
            $this->dispatch('notify', [
                'type' => 'error',
                'message' => 'You do not have permission to publish content',
            ]);
            return;
        }

        if ($draft->status !== ContentDraft::STATUS_APPROVED) {
            $this->dispatch('notify', [
                'type' => 'error',
                'message' => 'Content must be approved before publishing',
            ]);
            return;
        }

        $publishingEngine = app(PublishingEngine::class);

        try {
            $publishingEngine->publishDraft($draft, [
                'schedule' => false, // Publish immediately
            ]);

            $this->dispatch('notify', [
                'type' => 'success',
                'message' => 'Content publishing initiated',
            ]);
        } catch (\Exception $e) {
            $this->dispatch('notify', [
                'type' => 'error',
                'message' => 'Failed to publish: ' . $e->getMessage(),
            ]);
        }
    }

    public function updatingSearch()
    {
        $this->resetPage();
    }

    public function updatingStatusFilter()
    {
        $this->resetPage();
    }

    public function updatingBrandFilter()
    {
        $this->resetPage();
    }
}; ?>

<div class="space-y-6">
    <!-- Header -->
    <div class="sm:flex sm:items-center sm:justify-between">
        <div>
            <h1 class="text-3xl font-bold text-gray-900 dark:text-white">Content Drafts</h1>
            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                Review and manage AI-generated content
            </p>
        </div>
    </div>

    <!-- Status Tabs -->
    <div class="border-b border-gray-200 dark:border-gray-700">
        <nav class="-mb-px flex space-x-8">
            <button
                wire:click="$set('statusFilter', '')"
                class="@if($statusFilter === '') border-indigo-500 text-indigo-600 dark:text-indigo-400 @else border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 dark:text-gray-400 dark:hover:text-gray-300 @endif whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm"
            >
                All
                <span class="ml-2 py-0.5 px-2.5 rounded-full text-xs font-medium @if($statusFilter === '') bg-indigo-100 text-indigo-600 dark:bg-indigo-900 dark:text-indigo-200 @else bg-gray-100 text-gray-900 dark:bg-gray-800 dark:text-gray-300 @endif">
                    {{ $statusCounts['all'] }}
                </span>
            </button>

            <button
                wire:click="$set('statusFilter', '{{ ContentDraft::STATUS_PENDING_REVIEW }}')"
                class="@if($statusFilter === ContentDraft::STATUS_PENDING_REVIEW) border-orange-500 text-orange-600 dark:text-orange-400 @else border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 dark:text-gray-400 dark:hover:text-gray-300 @endif whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm"
            >
                Pending Review
                <span class="ml-2 py-0.5 px-2.5 rounded-full text-xs font-medium @if($statusFilter === ContentDraft::STATUS_PENDING_REVIEW) bg-orange-100 text-orange-600 dark:bg-orange-900 dark:text-orange-200 @else bg-gray-100 text-gray-900 dark:bg-gray-800 dark:text-gray-300 @endif">
                    {{ $statusCounts['pending_review'] }}
                </span>
            </button>

            <button
                wire:click="$set('statusFilter', '{{ ContentDraft::STATUS_APPROVED }}')"
                class="@if($statusFilter === ContentDraft::STATUS_APPROVED) border-green-500 text-green-600 dark:text-green-400 @else border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 dark:text-gray-400 dark:hover:text-gray-300 @endif whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm"
            >
                Approved
                <span class="ml-2 py-0.5 px-2.5 rounded-full text-xs font-medium @if($statusFilter === ContentDraft::STATUS_APPROVED) bg-green-100 text-green-600 dark:bg-green-900 dark:text-green-200 @else bg-gray-100 text-gray-900 dark:bg-gray-800 dark:text-gray-300 @endif">
                    {{ $statusCounts['approved'] }}
                </span>
            </button>

            <button
                wire:click="$set('statusFilter', '{{ ContentDraft::STATUS_PUBLISHED }}')"
                class="@if($statusFilter === ContentDraft::STATUS_PUBLISHED) border-blue-500 text-blue-600 dark:text-blue-400 @else border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 dark:text-gray-400 dark:hover:text-gray-300 @endif whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm"
            >
                Published
                <span class="ml-2 py-0.5 px-2.5 rounded-full text-xs font-medium @if($statusFilter === ContentDraft::STATUS_PUBLISHED) bg-blue-100 text-blue-600 dark:bg-blue-900 dark:text-blue-200 @else bg-gray-100 text-gray-900 dark:bg-gray-800 dark:text-gray-300 @endif">
                    {{ $statusCounts['published'] }}
                </span>
            </button>
        </nav>
    </div>

    <!-- Filters -->
    <div class="flex flex-col sm:flex-row gap-4">
        <!-- Search -->
        <div class="flex-1">
            <input
                type="text"
                wire:model.live.debounce.300ms="search"
                placeholder="Search drafts..."
                class="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:bg-gray-800 dark:border-gray-700 dark:text-white"
            />
        </div>

        <!-- Brand Filter -->
        <div class="w-full sm:w-64">
            <select
                wire:model.live="brandFilter"
                class="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:bg-gray-800 dark:border-gray-700 dark:text-white"
            >
                <option value="">All Brands</option>
                @foreach($brands as $brand)
                    <option value="{{ $brand->id }}">{{ $brand->name }}</option>
                @endforeach
            </select>
        </div>
    </div>

    <!-- Drafts Table -->
    <div class="bg-white dark:bg-gray-800 shadow overflow-hidden sm:rounded-lg">
        <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
            <thead class="bg-gray-50 dark:bg-gray-900">
                <tr>
                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                        <button wire:click="sortBy('title')" class="flex items-center space-x-1">
                            <span>Title</span>
                            @if($sortBy === 'title')
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="@if($sortDirection === 'asc') M5 15l7-7 7 7 @else M19 9l-7 7-7-7 @endif"></path>
                                </svg>
                            @endif
                        </button>
                    </th>
                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                        Brand
                    </th>
                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                        Status
                    </th>
                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                        <button wire:click="sortBy('confidence_score')" class="flex items-center space-x-1">
                            <span>Score</span>
                            @if($sortBy === 'confidence_score')
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="@if($sortDirection === 'asc') M5 15l7-7 7 7 @else M19 9l-7 7-7-7 @endif"></path>
                                </svg>
                            @endif
                        </button>
                    </th>
                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                        Created
                    </th>
                    <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                        Actions
                    </th>
                </tr>
            </thead>
            <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                @forelse($drafts as $draft)
                    <tr class="hover:bg-gray-50 dark:hover:bg-gray-700">
                        <td class="px-6 py-4">
                            <div class="text-sm font-medium text-gray-900 dark:text-white">
                                {{ Str::limit($draft->title, 60) }}
                            </div>
                            <div class="text-sm text-gray-500 dark:text-gray-400">
                                {{ $draft->category->name }}
                            </div>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                            {{ $draft->brand->name }}
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            @php
                                $statusColors = [
                                    ContentDraft::STATUS_PENDING_REVIEW => 'bg-orange-100 text-orange-800 dark:bg-orange-900 dark:text-orange-200',
                                    ContentDraft::STATUS_APPROVED => 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200',
                                    ContentDraft::STATUS_PUBLISHED => 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200',
                                    ContentDraft::STATUS_REJECTED => 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200',
                                ];
                            @endphp
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium {{ $statusColors[$draft->status] ?? 'bg-gray-100 text-gray-800 dark:bg-gray-900 dark:text-gray-200' }}">
                                {{ ucwords(str_replace('_', ' ', $draft->status)) }}
                            </span>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                            {{ number_format($draft->confidence_score * 100, 1) }}%
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                            {{ $draft->created_at->diffForHumans() }}
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium space-x-2">
                            <a href="{{ route('drafts.show', $draft) }}" class="text-indigo-600 hover:text-indigo-900 dark:text-indigo-400 dark:hover:text-indigo-300">
                                View
                            </a>

                            @if($draft->status === ContentDraft::STATUS_PENDING_REVIEW)
                                @can('approve content')
                                    <button
                                        wire:click="approve({{ $draft->id }})"
                                        class="text-green-600 hover:text-green-900 dark:text-green-400 dark:hover:text-green-300"
                                    >
                                        Approve
                                    </button>
                                    <button
                                        wire:click="reject({{ $draft->id }})"
                                        class="text-red-600 hover:text-red-900 dark:text-red-400 dark:hover:text-red-300"
                                    >
                                        Reject
                                    </button>
                                @endcan
                            @endif

                            @if($draft->status === ContentDraft::STATUS_APPROVED)
                                @can('publish content')
                                    <button
                                        wire:click="publishNow({{ $draft->id }})"
                                        class="text-blue-600 hover:text-blue-900 dark:text-blue-400 dark:hover:text-blue-300"
                                    >
                                        Publish Now
                                    </button>
                                @endcan
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="px-6 py-12 text-center text-sm text-gray-500 dark:text-gray-400">
                            No drafts found matching your filters
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>

        <!-- Pagination -->
        <div class="px-6 py-4 border-t border-gray-200 dark:border-gray-700">
            {{ $drafts->links() }}
        </div>
    </div>
</div>
