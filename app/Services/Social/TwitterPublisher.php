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
 * Twitter/X Publisher
 *
 * Publishes content to Twitter/X using API v2.
 */
class TwitterPublisher implements SocialPublisherInterface
{
    protected Client $client;
    protected string $baseUrl = 'https://api.twitter.com/2';

    public function __construct()
    {
        $this->client = new Client([
            'base_uri' => $this->baseUrl,
            'timeout' => 30,
        ]);
    }

    /**
     * Publish content to Twitter/X
     *
     * @param ContentVariant $variant
     * @param SocialConnector $connector
     * @return array
     */
    public function publish(ContentVariant $variant, SocialConnector $connector): array
    {
        try {
            $token = $this->getAccessToken($connector);

            // Prepare tweet data
            $tweetData = [
                'text' => $this->prepareTweetText($variant->content),
            ];

            // Add reply settings if configured
            if (isset($connector->platform_settings['reply_settings'])) {
                $tweetData['reply_settings'] = $connector->platform_settings['reply_settings'];
            }

            // Post tweet
            $response = $this->client->post('/tweets', [
                'headers' => [
                    'Authorization' => "Bearer {$token}",
                    'Content-Type' => 'application/json',
                ],
                'json' => $tweetData,
            ]);

            $result = json_decode($response->getBody()->getContents(), true);

            $tweetId = $result['data']['id'] ?? null;
            $tweetUrl = $tweetId ? "https://twitter.com/user/status/{$tweetId}" : null;

            Log::info('Successfully published to Twitter', [
                'connector_id' => $connector->id,
                'tweet_id' => $tweetId,
            ]);

            // Update last posted timestamp
            $connector->update(['last_posted_at' => now()]);

            return [
                'success' => true,
                'post_id' => $tweetId,
                'url' => $tweetUrl,
                'platform' => 'twitter',
            ];
        } catch (\Exception $e) {
            Log::error('Failed to publish to Twitter', [
                'connector_id' => $connector->id,
                'error' => $e->getMessage(),
                'response' => $e->getResponse() ? $e->getResponse()->getBody()->getContents() : null,
            ]);

            throw $e;
        }
    }

    /**
     * Delete tweet
     *
     * @param string $postId
     * @param SocialConnector $connector
     * @return bool
     */
    public function delete(string $postId, SocialConnector $connector): bool
    {
        try {
            $token = $this->getAccessToken($connector);

            $response = $this->client->delete("/tweets/{$postId}", [
                'headers' => [
                    'Authorization' => "Bearer {$token}",
                ],
            ]);

            $result = json_decode($response->getBody()->getContents(), true);

            Log::info('Successfully deleted tweet', [
                'connector_id' => $connector->id,
                'tweet_id' => $postId,
            ]);

            return $result['data']['deleted'] ?? false;
        } catch (\Exception $e) {
            Log::error('Failed to delete tweet', [
                'connector_id' => $connector->id,
                'tweet_id' => $postId,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Get tweet metrics
     *
     * @param string $postId
     * @param SocialConnector $connector
     * @return array
     */
    public function getMetrics(string $postId, SocialConnector $connector): array
    {
        try {
            $token = $this->getAccessToken($connector);

            // Get tweet with metrics
            $response = $this->client->get("/tweets/{$postId}", [
                'headers' => [
                    'Authorization' => "Bearer {$token}",
                ],
                'query' => [
                    'tweet.fields' => 'public_metrics,non_public_metrics,organic_metrics',
                ],
            ]);

            $data = json_decode($response->getBody()->getContents(), true);
            $metrics = $data['data']['public_metrics'] ?? [];

            return [
                'retweets' => $metrics['retweet_count'] ?? 0,
                'likes' => $metrics['like_count'] ?? 0,
                'replies' => $metrics['reply_count'] ?? 0,
                'quotes' => $metrics['quote_count'] ?? 0,
                'impressions' => $metrics['impression_count'] ?? 0,
            ];
        } catch (\Exception $e) {
            Log::error('Failed to get Twitter metrics', [
                'connector_id' => $connector->id,
                'tweet_id' => $postId,
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
            $response = $this->client->post('https://api.twitter.com/2/oauth2/token', [
                'form_params' => [
                    'grant_type' => 'refresh_token',
                    'refresh_token' => $refreshToken,
                    'client_id' => config('services.twitter.client_id'),
                ],
                'headers' => [
                    'Content-Type' => 'application/x-www-form-urlencoded',
                ],
            ]);

            $data = json_decode($response->getBody()->getContents(), true);

            // Update connector with new tokens
            $newTokenData = [
                'access_token' => $data['access_token'],
                'refresh_token' => $data['refresh_token'] ?? $refreshToken,
                'expires_in' => $data['expires_in'] ?? 7200,
            ];

            $connector->update([
                'encrypted_token' => Crypt::encryptString(json_encode($newTokenData)),
                'token_expires_at' => now()->addSeconds($newTokenData['expires_in']),
            ]);

            Log::info('Successfully refreshed Twitter token', [
                'connector_id' => $connector->id,
                'expires_at' => $connector->token_expires_at,
            ]);

            return $newTokenData;
        } catch (\Exception $e) {
            Log::error('Failed to refresh Twitter token', [
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
            'posts_per_hour' => 50,
            'posts_per_day' => 300,
        ];

        $cacheKeyHour = "twitter_rate_limit_hour_{$connector->id}";
        $cacheKeyDay = "twitter_rate_limit_day_{$connector->id}";

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
        return SocialConnector::TWITTER;
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
     * Prepare tweet text (ensure it fits within character limit)
     *
     * @param string $content
     * @return string
     */
    protected function prepareTweetText(string $content): string
    {
        // Twitter allows 280 characters
        $maxLength = 280;

        if (mb_strlen($content) <= $maxLength) {
            return $content;
        }

        // Truncate and add ellipsis
        return mb_substr($content, 0, $maxLength - 3) . '...';
    }
}
