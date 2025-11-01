<?php

use App\Models\Brand;
use Livewire\Volt\Component;

new class extends Component {
    public Brand $brand;

    public $name;
    public $slug;
    public $domain;
    public $active;

    // Brand Voice
    public $tone;
    public $style;
    public $audience;
    public $lexicon = [];

    // Style Guide
    public $dos = [];
    public $donts = [];
    public $blocklist = [];

    // Settings
    public $auto_approve;
    public $auto_publish;
    public $posts_per_day;
    public $timezone;
    public $quiet_hours = [];
    public $optimal_posting_times = [];

    public function mount()
    {
        $this->name = $this->brand->name;
        $this->slug = $this->brand->slug;
        $this->domain = $this->brand->domain;
        $this->active = $this->brand->active;

        // Load brand voice
        $this->tone = $this->brand->brand_voice['tone'] ?? '';
        $this->style = $this->brand->brand_voice['style'] ?? '';
        $this->audience = $this->brand->brand_voice['audience'] ?? '';
        $this->lexicon = $this->brand->brand_voice['lexicon'] ?? [];

        // Load style guide
        $this->dos = $this->brand->style_guide['dos'] ?? [];
        $this->donts = $this->brand->style_guide['donts'] ?? [];
        $this->blocklist = $this->brand->style_guide['blocklist'] ?? [];

        // Load settings
        $this->auto_approve = $this->brand->settings['auto_approve'] ?? false;
        $this->auto_publish = $this->brand->settings['auto_publish'] ?? false;
        $this->posts_per_day = $this->brand->settings['posts_per_day'] ?? 1;
        $this->timezone = $this->brand->settings['timezone'] ?? 'UTC';
        $this->quiet_hours = $this->brand->settings['quiet_hours'] ?? [];
        $this->optimal_posting_times = $this->brand->settings['optimal_posting_times'] ?? [];
    }

    public function save()
    {
        $this->validate([
            'name' => 'required|string|max:255',
            'slug' => 'required|string|max:255|unique:brands,slug,' . $this->brand->id,
            'domain' => 'nullable|url',
            'tone' => 'required|string',
            'style' => 'required|string',
            'audience' => 'required|string',
            'posts_per_day' => 'required|integer|min:1|max:10',
            'timezone' => 'required|string',
        ]);

        $this->brand->update([
            'name' => $this->name,
            'slug' => $this->slug,
            'domain' => $this->domain,
            'active' => $this->active,
            'brand_voice' => [
                'tone' => $this->tone,
                'style' => $this->style,
                'audience' => $this->audience,
                'lexicon' => array_values(array_filter($this->lexicon)),
            ],
            'style_guide' => [
                'dos' => array_values(array_filter($this->dos)),
                'donts' => array_values(array_filter($this->donts)),
                'blocklist' => array_values(array_filter($this->blocklist)),
            ],
            'settings' => [
                'auto_approve' => $this->auto_approve,
                'auto_publish' => $this->auto_publish,
                'posts_per_day' => $this->posts_per_day,
                'timezone' => $this->timezone,
                'quiet_hours' => array_values(array_filter($this->quiet_hours)),
                'optimal_posting_times' => array_values(array_filter($this->optimal_posting_times)),
            ],
        ]);

        $this->dispatch('notify', [
            'type' => 'success',
            'message' => 'Brand settings saved successfully',
        ]);
    }

    public function addLexicon()
    {
        $this->lexicon[] = '';
    }

    public function removeLexicon($index)
    {
        unset($this->lexicon[$index]);
        $this->lexicon = array_values($this->lexicon);
    }

    public function addDo()
    {
        $this->dos[] = '';
    }

    public function removeDo($index)
    {
        unset($this->dos[$index]);
        $this->dos = array_values($this->dos);
    }

    public function addDont()
    {
        $this->donts[] = '';
    }

    public function removeDont($index)
    {
        unset($this->donts[$index]);
        $this->donts = array_values($this->donts);
    }

    public function addBlocklist()
    {
        $this->blocklist[] = '';
    }

    public function removeBlocklist($index)
    {
        unset($this->blocklist[$index]);
        $this->blocklist = array_values($this->blocklist);
    }

    public function addQuietHour()
    {
        $this->quiet_hours[] = ['start' => '22:00', 'end' => '06:00'];
    }

    public function removeQuietHour($index)
    {
        unset($this->quiet_hours[$index]);
        $this->quiet_hours = array_values($this->quiet_hours);
    }

    public function addOptimalTime()
    {
        $this->optimal_posting_times[] = '09:00';
    }

    public function removeOptimalTime($index)
    {
        unset($this->optimal_posting_times[$index]);
        $this->optimal_posting_times = array_values($this->optimal_posting_times);
    }
}; ?>

<div class="space-y-6">
    <!-- Header -->
    <div class="sm:flex sm:items-center sm:justify-between">
        <div>
            <h1 class="text-3xl font-bold text-gray-900 dark:text-white">Brand Settings</h1>
            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                Configure brand voice, style guide, and content generation settings
            </p>
        </div>
    </div>

    <form wire:submit="save" class="space-y-8">
        <!-- Basic Information -->
        <div class="bg-white dark:bg-gray-800 shadow sm:rounded-lg">
            <div class="px-4 py-5 sm:p-6">
                <h3 class="text-lg leading-6 font-medium text-gray-900 dark:text-white mb-4">
                    Basic Information
                </h3>

                <div class="grid grid-cols-1 gap-6 sm:grid-cols-2">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                            Brand Name
                        </label>
                        <input
                            type="text"
                            wire:model="name"
                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:bg-gray-700 dark:border-gray-600 dark:text-white"
                        />
                        @error('name') <span class="text-red-500 text-sm">{{ $message }}</span> @enderror
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                            Slug
                        </label>
                        <input
                            type="text"
                            wire:model="slug"
                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:bg-gray-700 dark:border-gray-600 dark:text-white"
                        />
                        @error('slug') <span class="text-red-500 text-sm">{{ $message }}</span> @enderror
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                            Website Domain
                        </label>
                        <input
                            type="url"
                            wire:model="domain"
                            placeholder="https://example.com"
                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:bg-gray-700 dark:border-gray-600 dark:text-white"
                        />
                        @error('domain') <span class="text-red-500 text-sm">{{ $message }}</span> @enderror
                    </div>

                    <div class="flex items-center">
                        <input
                            type="checkbox"
                            wire:model="active"
                            class="h-4 w-4 text-indigo-600 focus:ring-indigo-500 border-gray-300 rounded"
                        />
                        <label class="ml-2 block text-sm font-medium text-gray-700 dark:text-gray-300">
                            Active
                        </label>
                    </div>
                </div>
            </div>
        </div>

        <!-- Brand Voice -->
        <div class="bg-white dark:bg-gray-800 shadow sm:rounded-lg">
            <div class="px-4 py-5 sm:p-6">
                <h3 class="text-lg leading-6 font-medium text-gray-900 dark:text-white mb-4">
                    Brand Voice
                </h3>

                <div class="space-y-6">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                            Tone
                        </label>
                        <textarea
                            wire:model="tone"
                            rows="2"
                            placeholder="e.g., Professional, friendly, authoritative"
                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:bg-gray-700 dark:border-gray-600 dark:text-white"
                        ></textarea>
                        @error('tone') <span class="text-red-500 text-sm">{{ $message }}</span> @enderror
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                            Writing Style
                        </label>
                        <textarea
                            wire:model="style"
                            rows="2"
                            placeholder="e.g., Conversational, technical, storytelling"
                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:bg-gray-700 dark:border-gray-600 dark:text-white"
                        ></textarea>
                        @error('style') <span class="text-red-500 text-sm">{{ $message }}</span> @enderror
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                            Target Audience
                        </label>
                        <textarea
                            wire:model="audience"
                            rows="2"
                            placeholder="e.g., Tech-savvy professionals, small business owners"
                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:bg-gray-700 dark:border-gray-600 dark:text-white"
                        ></textarea>
                        @error('audience') <span class="text-red-500 text-sm">{{ $message }}</span> @enderror
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                            Brand Lexicon (Key Terms)
                        </label>
                        @foreach($lexicon as $index => $term)
                            <div class="flex gap-2 mb-2">
                                <input
                                    type="text"
                                    wire:model="lexicon.{{ $index }}"
                                    placeholder="e.g., SaaS, B2B, ROI"
                                    class="flex-1 rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:bg-gray-700 dark:border-gray-600 dark:text-white"
                                />
                                <button
                                    type="button"
                                    wire:click="removeLexicon({{ $index }})"
                                    class="px-3 py-2 bg-red-600 text-white rounded-md hover:bg-red-700"
                                >
                                    Remove
                                </button>
                            </div>
                        @endforeach
                        <button
                            type="button"
                            wire:click="addLexicon"
                            class="mt-2 px-4 py-2 bg-gray-200 dark:bg-gray-700 text-gray-700 dark:text-gray-300 rounded-md hover:bg-gray-300 dark:hover:bg-gray-600"
                        >
                            Add Term
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Content Generation Settings -->
        <div class="bg-white dark:bg-gray-800 shadow sm:rounded-lg">
            <div class="px-4 py-5 sm:p-6">
                <h3 class="text-lg leading-6 font-medium text-gray-900 dark:text-white mb-4">
                    Content Generation Settings
                </h3>

                <div class="space-y-6">
                    <div class="flex items-center">
                        <input
                            type="checkbox"
                            wire:model="auto_approve"
                            class="h-4 w-4 text-indigo-600 focus:ring-indigo-500 border-gray-300 rounded"
                        />
                        <label class="ml-2 block text-sm font-medium text-gray-700 dark:text-gray-300">
                            Auto-approve high-quality content (score ≥ 80%)
                        </label>
                    </div>

                    <div class="flex items-center">
                        <input
                            type="checkbox"
                            wire:model="auto_publish"
                            class="h-4 w-4 text-indigo-600 focus:ring-indigo-500 border-gray-300 rounded"
                        />
                        <label class="ml-2 block text-sm font-medium text-gray-700 dark:text-gray-300">
                            Auto-publish approved content
                        </label>
                    </div>

                    <div class="grid grid-cols-1 gap-6 sm:grid-cols-2">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                                Posts per Day
                            </label>
                            <input
                                type="number"
                                wire:model="posts_per_day"
                                min="1"
                                max="10"
                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:bg-gray-700 dark:border-gray-600 dark:text-white"
                            />
                            @error('posts_per_day') <span class="text-red-500 text-sm">{{ $message }}</span> @enderror
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                                Timezone
                            </label>
                            <select
                                wire:model="timezone"
                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:bg-gray-700 dark:border-gray-600 dark:text-white"
                            >
                                <option value="UTC">UTC</option>
                                <option value="America/New_York">Eastern Time</option>
                                <option value="America/Chicago">Central Time</option>
                                <option value="America/Denver">Mountain Time</option>
                                <option value="America/Los_Angeles">Pacific Time</option>
                                <option value="Europe/London">London</option>
                                <option value="Asia/Dhaka">Dhaka</option>
                            </select>
                            @error('timezone') <span class="text-red-500 text-sm">{{ $message }}</span> @enderror
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Save Button -->
        <div class="flex justify-end">
            <button
                type="submit"
                class="px-6 py-3 bg-indigo-600 text-white font-medium rounded-md hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500"
            >
                Save Settings
            </button>
        </div>
    </form>
</div>
