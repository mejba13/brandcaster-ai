<?php

namespace App\Services\TopicDiscovery;

use App\Services\TopicDiscovery\Contracts\TrendSourceInterface;
use App\Services\TopicDiscovery\TrendSources\SerpApiTrendSource;
use App\Services\TopicDiscovery\TrendSources\RssFeedTrendSource;

/**
 * Trend Source Registry
 *
 * Manages registration and retrieval of trend sources.
 */
class TrendSourceRegistry
{
    /** @var TrendSourceInterface[] */
    protected array $sources = [];

    public function __construct()
    {
        // Auto-register default sources
        $this->registerDefaultSources();
    }

    /**
     * Register a trend source
     *
     * @param TrendSourceInterface $source
     * @return void
     */
    public function register(TrendSourceInterface $source): void
    {
        $this->sources[$source->getName()] = $source;
    }

    /**
     * Get a specific source by name
     *
     * @param string $name
     * @return TrendSourceInterface|null
     */
    public function get(string $name): ?TrendSourceInterface
    {
        return $this->sources[$name] ?? null;
    }

    /**
     * Get all registered sources
     *
     * @return TrendSourceInterface[]
     */
    public function getAll(): array
    {
        return $this->sources;
    }

    /**
     * Get only available (configured) sources
     *
     * @return TrendSourceInterface[]
     */
    public function getAvailableSources(): array
    {
        return array_filter($this->sources, fn($source) => $source->isAvailable());
    }

    /**
     * Register default trend sources
     *
     * @return void
     */
    protected function registerDefaultSources(): void
    {
        // Register SerpAPI source
        $this->register(app(SerpApiTrendSource::class));

        // Register RSS feed source
        $this->register(app(RssFeedTrendSource::class));

        // Future sources can be registered here:
        // $this->register(app(NewsApiTrendSource::class));
        // $this->register(app(TwitterTrendSource::class));
        // $this->register(app(RedditTrendSource::class));
    }

    /**
     * Check if any sources are available
     *
     * @return bool
     */
    public function hasAvailableSources(): bool
    {
        return count($this->getAvailableSources()) > 0;
    }

    /**
     * Get source names
     *
     * @param bool $onlyAvailable
     * @return array
     */
    public function getSourceNames(bool $onlyAvailable = false): array
    {
        $sources = $onlyAvailable ? $this->getAvailableSources() : $this->sources;
        return array_map(fn($source) => $source->getName(), $sources);
    }
}
