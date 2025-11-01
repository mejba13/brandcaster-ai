<?php

namespace App\Services\Social;

use App\Models\ContentVariant;
use App\Models\SocialConnector;
use App\Services\Social\Contracts\SocialPublisherInterface;
use GuzzleHttp\Client;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Log;

/**
 * LinkedIn Publisher
 *
 * Publishes content to LinkedIn using API v2.
 */
class LinkedInPublisher implements SocialPublisherInterface
{
    protected Client $client;
    protected string $baseUrl = 'https://api.linkedin.com/v2';

    public function __construct()
    {
        $this->client = new Client([
            'base_uri' => $this->baseUrl,
            'timeout' => 30,
        ]);
    }

    /**
     * Publish content to LinkedIn
     *
     * @param ContentVariant $variant
     * @param SocialConnector $connector
     * @return array
     */
    public function publish(ContentVariant $variant, SocialConnector $connector): array
    {
        try {
            $token = $this->getAccessToken($connector);
            $authorUrn = $this->getAuthorUrn($connector);

            // Prepare share data
            $shareData = [
                'author' => $authorUrn,
                'lifecycleState' => 'PUBLISHED',
                'specificContent' => [
                    'com.linkedin.ugc.ShareContent' => [
                        'shareCommentary' => [
                            'text' => $variant->content,
                        ],
                        'shareMediaCategory' => 'NONE',
                    ],
                ],
                'visibility' => [
                    'com.linkedin.ugc.MemberNetworkVisibility' => 'PUBLIC',
                ],
            ];

            // Add link if present
            if (isset($variant->metadata['link'])) {
                $shareData['specificContent']['com.linkedin.ugc.ShareContent']['shareMediaCategory'] = 'ARTICLE';
                $shareData['specificContent']['com.linkedin.ugc.ShareContent']['media'] = [[
                    'status' => 'READY',
                    'originalUrl' => $variant->metadata['link'],
                ]];
            }

            // Post to LinkedIn
            $response = $this->client->post('/ugcPosts', [
                'headers' => [
                    'Authorization' => "Bearer {$token}",
                    'Content-Type' => 'application/json',
                    'X-Restli-Protocol-Version' => '2.0.0',
                ],
                'json' => $shareData,
            ]);

            $result = json_decode($response->getBody()->getContents(), true);
            $postId = $result['id'] ?? null;

            Log::info('Successfully published to LinkedIn', [
                'connector_id' => $connector->id,
                'post_id' => $postId,
            ]);

            // Update last posted timestamp
            $connector->update(['last_posted_at' => now()]);

            return [
                'success' => true,
                'post_id' => $postId,
                'url' => $postId ? "https://www.linkedin.com/feed/update/{$postId}" : null,
                'platform' => 'linkedin',
            ];
        } catch (\Exception $e) {
            Log::error('Failed to publish to LinkedIn', [
                'connector_id' => $connector->id,
                'error' => $e->getMessage(),
                'response' => $e->getResponse() ? $e->getResponse()->getBody()->getContents() : null,
            ]);

            throw $e;
        }
    }

    /**
     * Delete post from LinkedIn
     *
     * @param string $postId
     * @param SocialConnector $connector
     * @return bool
     */
    public function delete(string $postId, SocialConnector $connector): bool
    {
        try {
            $token = $this->getAccessToken($connector);

            $response = $this->client->delete("/ugcPosts/{$postId}", [
                'headers' => [
                    'Authorization' => "Bearer {$token}",
                ],
            ]);

            Log::info('Successfully deleted LinkedIn post', [
                'connector_id' => $connector->id,
                'post_id' => $postId,
            ]);

            return $response->getStatusCode() === 204;
        } catch (\Exception $e) {
            Log::error('Failed to delete LinkedIn post', [
                'connector_id' => $connector->id,
                'post_id' => $postId,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Get post metrics from LinkedIn
     *
     * @param string $postId
     * @param SocialConnector $connector
     * @return array
     */
    public function getMetrics(string $postId, SocialConnector $connector): array
    {
        try {
            $token = $this->getAccessToken($connector);

            // Get post statistics
            $response = $this->client->get("/socialActions/{$postId}", [
                'headers' => [
                    'Authorization' => "Bearer {$token}",
                ],
            ]);

            $data = json_decode($response->getBody()->getContents(), true);

            return [
                'likes' => $data['likesSummary']['totalLikes'] ?? 0,
                'comments' => $data['commentsSummary']['totalComments'] ?? 0,
                'shares' => $data['sharesSummary']['totalShares'] ?? 0,
                'clicks' => $data['clickCount'] ?? 0,
                'impressions' => $data['impressionCount'] ?? 0,
                'engagement' => $data['engagementCount'] ?? 0,
            ];
        } catch (\Exception $e) {
            Log::error('Failed to get LinkedIn metrics', [
                'connector_id' => $connector->id,
                'post_id' => $postId,
                'error' => $e->getMessage(),
            ]);

            return [];
        }
    }

    /**
     * Refresh access token
     *
     * @param SocialConnector $connector
     * @return array
     */
    public function refreshToken(SocialConnector $connector): array
    {
        try {
            $tokenData = json_decode(Crypt::decryptString($connector->encrypted_token), true);
            $refreshToken = $tokenData['refresh_token'] ?? null;

            if (!$refreshToken) {
                throw new \Exception('No refresh token available');
            }

            // Request new access token
            $response = $this->client->post('https://www.linkedin.com/oauth/v2/accessToken', [
                'form_params' => [
                    'grant_type' => 'refresh_token',
                    'refresh_token' => $refreshToken,
                    'client_id' => config('services.linkedin.client_id'),
                    'client_secret' => config('services.linkedin.client_secret'),
                ],
            ]);

            $data = json_decode($response->getBody()->getContents(), true);

            // Update connector with new tokens
            $newTokenData = [
                'access_token' => $data['access_token'],
                'refresh_token' => $data['refresh_token'] ?? $refreshToken,
                'expires_in' => $data['expires_in'] ?? 5184000, // 60 days
            ];

            $connector->update([
                'encrypted_token' => Crypt::encryptString(json_encode($newTokenData)),
                'token_expires_at' => now()->addSeconds($newTokenData['expires_in']),
            ]);

            Log::info('Successfully refreshed LinkedIn token', [
                'connector_id' => $connector->id,
                'expires_at' => $connector->token_expires_at,
            ]);

            return $newTokenData;
        } catch (\Exception $e) {
            Log::error('Failed to refresh LinkedIn token', [
                'connector_id' => $connector->id,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * Check if connector can post now (rate limiting)
     *
     * @param SocialConnector $connector
     * @return bool
     */
    public function canPost(SocialConnector $connector): bool
    {
        $rateLimits = $connector->rate_limits ?? [
            'posts_per_hour' => 20,
            'posts_per_day' => 100,
        ];

        $cacheKeyHour = "linkedin_rate_limit_hour_{$connector->id}";
        $cacheKeyDay = "linkedin_rate_limit_day_{$connector->id}";

        $postsThisHour = Cache::get($cacheKeyHour, 0);
        $postsToday = Cache::get($cacheKeyDay, 0);

        $canPost = $postsThisHour < $rateLimits['posts_per_hour']
            && $postsToday < $rateLimits['posts_per_day'];

        if ($canPost) {
            Cache::put($cacheKeyHour, $postsThisHour + 1, now()->addHour());
            Cache::put($cacheKeyDay, $postsToday + 1, now()->endOfDay());
        }

        return $canPost;
    }

    /**
     * Get platform name
     *
     * @return string
     */
    public function getPlatform(): string
    {
        return SocialConnector::LINKEDIN;
    }

    /**
     * Get decrypted access token
     *
     * @param SocialConnector $connector
     * @return string
     */
    protected function getAccessToken(SocialConnector $connector): string
    {
        $tokenData = json_decode(Crypt::decryptString($connector->encrypted_token), true);
        return $tokenData['access_token'] ?? '';
    }

    /**
     * Get author URN for posting
     *
     * @param SocialConnector $connector
     * @return string
     */
    protected function getAuthorUrn(SocialConnector $connector): string
    {
        // For organization pages
        if (isset($connector->platform_settings['organization_id'])) {
            return "urn:li:organization:{$connector->platform_settings['organization_id']}";
        }

        // For personal profiles
        if (isset($connector->platform_settings['person_id'])) {
            return "urn:li:person:{$connector->platform_settings['person_id']}";
        }

        // Default to account ID from connector
        return "urn:li:person:{$connector->account_id}";
    }

    /**
     * Get user profile information
     *
     * @param string $accessToken
     * @return array
     */
    public function getUserProfile(string $accessToken): array
    {
        try {
            $response = $this->client->get('/me', [
                'headers' => [
                    'Authorization' => "Bearer {$accessToken}",
                ],
            ]);

            return json_decode($response->getBody()->getContents(), true);
        } catch (\Exception $e) {
            Log::error('Failed to get LinkedIn profile', [
                'error' => $e->getMessage(),
            ]);

            return [];
        }
    }

    /**
     * Get organizations the user can post to
     *
     * @param string $accessToken
     * @return array
     */
    public function getOrganizations(string $accessToken): array
    {
        try {
            $response = $this->client->get('/organizationalEntityAcls', [
                'headers' => [
                    'Authorization' => "Bearer {$accessToken}",
                ],
                'query' => [
                    'q' => 'roleAssignee',
                    'role' => 'ADMINISTRATOR',
                    'state' => 'APPROVED',
                ],
            ]);

            $data = json_decode($response->getBody()->getContents(), true);

            return $data['elements'] ?? [];
        } catch (\Exception $e) {
            Log::error('Failed to get LinkedIn organizations', [
                'error' => $e->getMessage(),
            ]);

            return [];
        }
    }
}
