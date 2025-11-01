<?php

namespace App\Services\AI\Contracts;

use App\Models\Brand;
use App\Models\Topic;

/**
 * AI Content Service Interface
 *
 * Contract for AI-powered content generation services.
 */
interface AIContentServiceInterface
{
    /**
     * Generate a strategy brief for content
     *
     * @param Topic $topic
     * @param Brand $brand
     * @return string Strategy brief
     */
    public function generateBrief(Topic $topic, Brand $brand): string;

    /**
     * Generate an outline from a brief
     *
     * @param string $brief
     * @param Brand $brand
     * @return array Structured outline
     */
    public function generateOutline(string $brief, Brand $brand): array;

    /**
     * Generate full content draft from outline
     *
     * @param array $outline
     * @param Brand $brand
     * @param Topic|null $topic
     * @return array Content with title, body, seo_metadata
     */
    public function generateDraft(array $outline, Brand $brand, ?Topic $topic = null): array;

    /**
     * Generate platform-specific variant
     *
     * @param string $content Source content
     * @param string $platform Target platform (website, facebook, twitter, linkedin)
     * @param Brand $brand
     * @return array Variant content with title, content, formatting
     */
    public function generateVariant(string $content, string $platform, Brand $brand): array;

    /**
     * Improve/rewrite content
     *
     * @param string $content Original content
     * @param string $instruction Improvement instruction
     * @param Brand $brand
     * @return string Improved content
     */
    public function improveContent(string $content, string $instruction, Brand $brand): string;

    /**
     * Generate SEO metadata
     *
     * @param string $title
     * @param string $content
     * @param Brand $brand
     * @return array Meta description, keywords, og tags
     */
    public function generateSEOMetadata(string $title, string $content, Brand $brand): array;
}
