<?php

namespace App\Services\Social\Contracts;

use App\Models\SocialConnector;
use App\Models\ContentVariant;

/**
 * Social Publisher Interface
 *
 * Contract for social media publishing services.
 */
interface SocialPublisherInterface
{
    /**
     * Publish content to social platform
     *
     * @param ContentVariant $variant Content to publish
     * @param SocialConnector $connector Social account credentials
     * @return array Result with post ID, URL, and metadata
     */
    public function publish(ContentVariant $variant, SocialConnector $connector): array;

    /**
     * Delete/unpublish content from platform
     *
     * @param string $postId Platform-specific post ID
     * @param SocialConnector $connector
     * @return bool Success status
     */
    public function delete(string $postId, SocialConnector $connector): bool;

    /**
     * Get post metrics from platform
     *
     * @param string $postId Platform-specific post ID
     * @param SocialConnector $connector
     * @return array Metrics (likes, shares, comments, impressions, etc.)
     */
    public function getMetrics(string $postId, SocialConnector $connector): array;

    /**
     * Refresh access token if needed
     *
     * @param SocialConnector $connector
     * @return array New token data
     */
    public function refreshToken(SocialConnector $connector): array;

    /**
     * Check if connector is within rate limits
     *
     * @param SocialConnector $connector
     * @return bool Can post now
     */
    public function canPost(SocialConnector $connector): bool;

    /**
     * Get platform name
     *
     * @return string Platform identifier
     */
    public function getPlatform(): string;
}
