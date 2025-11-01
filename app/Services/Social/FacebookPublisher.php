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
 * Facebook Publisher
 *
 * Publishes content to Facebook Pages using Graph API.
 */
class FacebookPublisher implements SocialPublisherInterface
{
    protected Client $client;
    protected string $apiVersion = 'v18.0';
    protected string $baseUrl;

    public function __construct()
    {
        $this->baseUrl = "https://graph.facebook.com/{$this->apiVersion}";
        $this->client = new Client([
            'base_uri' => $this->baseUrl,
            'timeout' => 30,
        ]);
    }

    /**
     * Publish content to Facebook Page
     *
     * @param ContentVariant $variant
     * @param SocialConnector $connector
     * @return array
     */
    public function publish(ContentVariant $variant, SocialConnector $connector): array
    {
        try {
            $token = $this->getAccessToken($connector);
            $pageId = $connector->platform_settings['page_id'] ?? null;

            if (!$pageId) {
                throw new \Exception('Facebook Page ID not configured');
            }

            // Prepare post data
            $postData = [
                'message' => $variant->content,
                'access_token' => $token,
            ];

            // Add link if present in metadata
            if (isset($variant->metadata['link'])) {
                $postData['link'] = $variant->metadata['link'];
            }

            // Publish to page feed
            $response = $this->client->post("/{$pageId}/feed", [
                'form_params' => $postData,
            ]);

            $result = json_decode($response->getBody()->getContents(), true);

            Log::info('Successfully published to Facebook', [
                'connector_id' => $connector->id,
                'post_id' => $result['id'] ?? null,
                'page_id' => $pageId,
            ]);

            // Update last posted timestamp
            $connector->update(['last_posted_at' => now()]);

            return [
                'success' => true,
                'post_id' => $result['id'],
                'url' => "https://www.facebook.com/{$result['id']}",
                'platform' => 'facebook',
            ];
        } catch (\Exception $e) {
            Log::error('Failed to publish to Facebook', [
                'connector_id' => $connector->id,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * Delete post from Facebook
     *
     * @param string $postId
     * @param SocialConnector $connector
     * @return bool
     */
    public function delete(string $postId, SocialConnector $connector): bool
    {
        try {
            $token = $this->getAccessToken($connector);

            $response = $this->client->delete("/{$postId}", [
                'query' => ['access_token' => $token],
            ]);

            $result = json_decode($response->getBody()->getContents(), true);

            Log::info('Successfully deleted Facebook post', [
                'connector_id' => $connector->id,
                'post_id' => $postId,
            ]);

            return $result['success'] ?? false;
        } catch (\Exception $e) {
            Log::error('Failed to delete Facebook post', [
                'connector_id' => $connector->id,
                'post_id' => $postId,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Get post metrics from Facebook
     *
     * @param string $postId
     * @param SocialConnector $connector
     * @return array
     */
    public function getMetrics(string $postId, SocialConnector $connector): array
    {
        try {
            $token = $this->getAccessToken($connector);

            // Request post insights
            $response = $this->client->get("/{$postId}", [
                'query' => [
                    'fields' => 'likes.summary(true),comments.summary(true),shares,reactions.summary(true)',
                    'access_token' => $token,
                ],
            ]);

            $data = json_decode($response->getBody()->getContents(), true);

            // Get insights (impressions, reach)
            $insightsResponse = $this->client->get("/{$postId}/insights", [
                'query' => [
                    'metric' => 'post_impressions,post_impressions_unique,post_engaged_users',
                    'access_token' => $token,
                ],
            ]);

            $insights = json_decode($insightsResponse->getBody()->getContents(), true);

            $metrics = [
                'likes' => $data['likes']['summary']['total_count'] ?? 0,
                'comments' => $data['comments']['summary']['total_count'] ?? 0,
                'shares' => $data['shares']['count'] ?? 0,
                'reactions' => $data['reactions']['summary']['total_count'] ?? 0,
            ];

            // Parse insights
            foreach ($insights['data'] ?? [] as $insight) {
                $metricName = $insight['name'];
                $value = $insight['values'][0]['value'] ?? 0;

                if ($metricName === 'post_impressions') {
                    $metrics['impressions'] = $value;
                } elseif ($metricName === 'post_impressions_unique') {
                    $metrics['reach'] = $value;
                } elseif ($metricName === 'post_engaged_users') {
                    $metrics['engagement'] = $value;
                }
            }

            return $metrics;
        } catch (\Exception $e) {
            Log::error('Failed to get Facebook metrics', [
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
            $currentToken = $this->getAccessToken($connector);

            // Exchange short-lived token for long-lived token
            $response = $this->client->get('/oauth/access_token', [
                'query' => [
                    'grant_type' => 'fb_exchange_token',
                    'client_id' => config('services.facebook.client_id'),
                    'client_secret' => config('services.facebook.client_secret'),
                    'fb_exchange_token' => $currentToken,
                ],
            ]);

            $data = json_decode($response->getBody()->getContents(), true);

            // Update connector with new token
            $tokenData = [
                'access_token' => $data['access_token'],
                'expires_in' => $data['expires_in'] ?? 5184000, // 60 days default
            ];

            $connector->update([
                'encrypted_token' => Crypt::encryptString(json_encode($tokenData)),
                'token_expires_at' => now()->addSeconds($tokenData['expires_in']),
            ]);

            Log::info('Successfully refreshed Facebook token', [
                'connector_id' => $connector->id,
                'expires_at' => $connector->token_expires_at,
            ]);

            return $tokenData;
        } catch (\Exception $e) {
            Log::error('Failed to refresh Facebook token', [
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
            'posts_per_hour' => 25,
            'posts_per_day' => 100,
        ];

        $cacheKeyHour = "facebook_rate_limit_hour_{$connector->id}";
        $cacheKeyDay = "facebook_rate_limit_day_{$connector->id}";

        $postsThisHour = Cache::get($cacheKeyHour, 0);
        $postsToday = Cache::get($cacheKeyDay, 0);

        $canPost = $postsThisHour < $rateLimits['posts_per_hour']
            && $postsToday < $rateLimits['posts_per_day'];

        if ($canPost) {
            // Increment counters
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
        return SocialConnector::FACEBOOK;
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
     * Get available pages for the user
     *
     * @param string $userAccessToken
     * @return array
     */
    public function getPages(string $userAccessToken): array
    {
        try {
            $response = $this->client->get('/me/accounts', [
                'query' => [
                    'access_token' => $userAccessToken,
                    'fields' => 'id,name,access_token,category',
                ],
            ]);

            $data = json_decode($response->getBody()->getContents(), true);

            return $data['data'] ?? [];
        } catch (\Exception $e) {
            Log::error('Failed to get Facebook pages', [
                'error' => $e->getMessage(),
            ]);

            return [];
        }
    }
}
