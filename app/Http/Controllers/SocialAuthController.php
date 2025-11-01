<?php

namespace App\Http\Controllers;

use App\Models\Brand;
use App\Models\SocialConnector;
use App\Services\Social\FacebookPublisher;
use App\Services\Social\LinkedInPublisher;
use App\Services\Social\TwitterPublisher;
use Exception;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Laravel\Socialite\Facades\Socialite;

/**
 * Social Authentication Controller
 *
 * Handles OAuth flow for connecting social media accounts (Facebook, Twitter, LinkedIn)
 * to the BrandCaster platform for content publishing.
 */
class SocialAuthController extends Controller
{
    /**
     * Redirect to Facebook OAuth
     *
     * @param Request $request
     * @return RedirectResponse
     */
    public function redirectToFacebook(Request $request): RedirectResponse
    {
        try {
            $brandId = $request->input('brand_id');

            if (!$brandId) {
                return redirect()->route('dashboard')
                    ->with('error', 'Brand ID is required');
            }

            // Verify user has access to this brand
            $brand = Brand::findOrFail($brandId);
            if (!Auth::user()->brands()->where('brands.id', $brandId)->exists()) {
                return redirect()->route('dashboard')
                    ->with('error', 'You do not have access to this brand');
            }

            // Store brand_id in session for callback
            session(['social_auth_brand_id' => $brandId]);

            return Socialite::driver('facebook')
                ->scopes(['pages_manage_posts', 'pages_read_engagement', 'pages_show_list'])
                ->redirect();
        } catch (Exception $e) {
            Log::error('Facebook OAuth redirect failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return redirect()->route('dashboard')
                ->with('error', 'Failed to connect to Facebook: ' . $e->getMessage());
        }
    }

    /**
     * Handle Facebook OAuth callback
     *
     * @return RedirectResponse
     */
    public function handleFacebookCallback(): RedirectResponse
    {
        try {
            $brandId = session('social_auth_brand_id');

            if (!$brandId) {
                throw new Exception('Session expired. Please try again.');
            }

            $socialiteUser = Socialite::driver('facebook')->user();

            // Get Facebook pages accessible by this user
            $facebookPublisher = new FacebookPublisher();
            $pages = $facebookPublisher->getPages($socialiteUser->token);

            if (empty($pages)) {
                throw new Exception('No Facebook pages found. Please ensure you have admin access to at least one Facebook page.');
            }

            // Use the first page (could be enhanced to let user choose)
            $page = $pages[0];

            // Prepare token data
            $tokenData = [
                'access_token' => $page['access_token'],
                'user_token' => $socialiteUser->token,
                'expires_in' => $socialiteUser->expiresIn ?? 5184000, // 60 days
            ];

            DB::beginTransaction();

            // Create or update social connector
            $connector = SocialConnector::updateOrCreate(
                [
                    'brand_id' => $brandId,
                    'platform' => SocialConnector::PLATFORM_FACEBOOK,
                    'account_id' => $page['id'],
                ],
                [
                    'account_name' => $page['name'],
                    'encrypted_token' => Crypt::encryptString(json_encode($tokenData)),
                    'token_expires_at' => now()->addSeconds($tokenData['expires_in']),
                    'platform_settings' => [
                        'page_id' => $page['id'],
                        'page_name' => $page['name'],
                        'category' => $page['category'] ?? null,
                    ],
                    'rate_limits' => [
                        'posts_per_hour' => 25,
                        'posts_per_day' => 100,
                    ],
                    'active' => true,
                ]
            );

            DB::commit();

            Log::info('Facebook account connected successfully', [
                'brand_id' => $brandId,
                'connector_id' => $connector->id,
                'page_id' => $page['id'],
                'page_name' => $page['name'],
            ]);

            // Clear session
            session()->forget('social_auth_brand_id');

            return redirect()->route('dashboard')
                ->with('success', "Facebook page '{$page['name']}' connected successfully!");
        } catch (Exception $e) {
            DB::rollBack();

            Log::error('Facebook OAuth callback failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            session()->forget('social_auth_brand_id');

            return redirect()->route('dashboard')
                ->with('error', 'Failed to connect Facebook account: ' . $e->getMessage());
        }
    }

    /**
     * Redirect to Twitter OAuth
     *
     * @param Request $request
     * @return RedirectResponse
     */
    public function redirectToTwitter(Request $request): RedirectResponse
    {
        try {
            $brandId = $request->input('brand_id');

            if (!$brandId) {
                return redirect()->route('dashboard')
                    ->with('error', 'Brand ID is required');
            }

            // Verify user has access to this brand
            $brand = Brand::findOrFail($brandId);
            if (!Auth::user()->brands()->where('brands.id', $brandId)->exists()) {
                return redirect()->route('dashboard')
                    ->with('error', 'You do not have access to this brand');
            }

            // Store brand_id in session for callback
            session(['social_auth_brand_id' => $brandId]);

            return Socialite::driver('twitter-oauth-2')
                ->scopes(['tweet.read', 'tweet.write', 'users.read', 'offline.access'])
                ->redirect();
        } catch (Exception $e) {
            Log::error('Twitter OAuth redirect failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return redirect()->route('dashboard')
                ->with('error', 'Failed to connect to Twitter: ' . $e->getMessage());
        }
    }

    /**
     * Handle Twitter OAuth callback
     *
     * @return RedirectResponse
     */
    public function handleTwitterCallback(): RedirectResponse
    {
        try {
            $brandId = session('social_auth_brand_id');

            if (!$brandId) {
                throw new Exception('Session expired. Please try again.');
            }

            $socialiteUser = Socialite::driver('twitter-oauth-2')->user();

            // Prepare token data
            $tokenData = [
                'access_token' => $socialiteUser->token,
                'refresh_token' => $socialiteUser->refreshToken,
                'expires_in' => $socialiteUser->expiresIn ?? 7200, // 2 hours
            ];

            DB::beginTransaction();

            // Create or update social connector
            $connector = SocialConnector::updateOrCreate(
                [
                    'brand_id' => $brandId,
                    'platform' => SocialConnector::PLATFORM_TWITTER,
                    'account_id' => $socialiteUser->id,
                ],
                [
                    'account_name' => $socialiteUser->nickname ?? $socialiteUser->name,
                    'encrypted_token' => Crypt::encryptString(json_encode($tokenData)),
                    'token_expires_at' => now()->addSeconds($tokenData['expires_in']),
                    'platform_settings' => [
                        'username' => $socialiteUser->nickname,
                        'name' => $socialiteUser->name,
                        'profile_image' => $socialiteUser->avatar ?? null,
                    ],
                    'rate_limits' => [
                        'posts_per_hour' => 50,
                        'posts_per_day' => 300,
                    ],
                    'active' => true,
                ]
            );

            DB::commit();

            Log::info('Twitter account connected successfully', [
                'brand_id' => $brandId,
                'connector_id' => $connector->id,
                'username' => $socialiteUser->nickname,
            ]);

            // Clear session
            session()->forget('social_auth_brand_id');

            return redirect()->route('dashboard')
                ->with('success', "Twitter account '@{$socialiteUser->nickname}' connected successfully!");
        } catch (Exception $e) {
            DB::rollBack();

            Log::error('Twitter OAuth callback failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            session()->forget('social_auth_brand_id');

            return redirect()->route('dashboard')
                ->with('error', 'Failed to connect Twitter account: ' . $e->getMessage());
        }
    }

    /**
     * Redirect to LinkedIn OAuth
     *
     * @param Request $request
     * @return RedirectResponse
     */
    public function redirectToLinkedIn(Request $request): RedirectResponse
    {
        try {
            $brandId = $request->input('brand_id');

            if (!$brandId) {
                return redirect()->route('dashboard')
                    ->with('error', 'Brand ID is required');
            }

            // Verify user has access to this brand
            $brand = Brand::findOrFail($brandId);
            if (!Auth::user()->brands()->where('brands.id', $brandId)->exists()) {
                return redirect()->route('dashboard')
                    ->with('error', 'You do not have access to this brand');
            }

            // Store brand_id in session for callback
            session(['social_auth_brand_id' => $brandId]);

            return Socialite::driver('linkedin-openid')
                ->scopes(['openid', 'profile', 'email', 'w_member_social'])
                ->redirect();
        } catch (Exception $e) {
            Log::error('LinkedIn OAuth redirect failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return redirect()->route('dashboard')
                ->with('error', 'Failed to connect to LinkedIn: ' . $e->getMessage());
        }
    }

    /**
     * Handle LinkedIn OAuth callback
     *
     * @return RedirectResponse
     */
    public function handleLinkedInCallback(): RedirectResponse
    {
        try {
            $brandId = session('social_auth_brand_id');

            if (!$brandId) {
                throw new Exception('Session expired. Please try again.');
            }

            $socialiteUser = Socialite::driver('linkedin-openid')->user();

            // Prepare token data
            $tokenData = [
                'access_token' => $socialiteUser->token,
                'refresh_token' => $socialiteUser->refreshToken ?? null,
                'expires_in' => $socialiteUser->expiresIn ?? 5184000, // 60 days
            ];

            DB::beginTransaction();

            // Create or update social connector
            $connector = SocialConnector::updateOrCreate(
                [
                    'brand_id' => $brandId,
                    'platform' => SocialConnector::PLATFORM_LINKEDIN,
                    'account_id' => $socialiteUser->id,
                ],
                [
                    'account_name' => $socialiteUser->name ?? $socialiteUser->email,
                    'encrypted_token' => Crypt::encryptString(json_encode($tokenData)),
                    'token_expires_at' => now()->addSeconds($tokenData['expires_in']),
                    'platform_settings' => [
                        'name' => $socialiteUser->name,
                        'email' => $socialiteUser->email,
                        'profile_image' => $socialiteUser->avatar ?? null,
                    ],
                    'rate_limits' => [
                        'posts_per_hour' => 25,
                        'posts_per_day' => 100,
                    ],
                    'active' => true,
                ]
            );

            DB::commit();

            Log::info('LinkedIn account connected successfully', [
                'brand_id' => $brandId,
                'connector_id' => $connector->id,
                'account_name' => $socialiteUser->name,
            ]);

            // Clear session
            session()->forget('social_auth_brand_id');

            return redirect()->route('dashboard')
                ->with('success', "LinkedIn account '{$socialiteUser->name}' connected successfully!");
        } catch (Exception $e) {
            DB::rollBack();

            Log::error('LinkedIn OAuth callback failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            session()->forget('social_auth_brand_id');

            return redirect()->route('dashboard')
                ->with('error', 'Failed to connect LinkedIn account: ' . $e->getMessage());
        }
    }

    /**
     * Disconnect a social account
     *
     * @param string $connectorId
     * @return RedirectResponse
     */
    public function disconnect(string $connectorId): RedirectResponse
    {
        try {
            $connector = SocialConnector::findOrFail($connectorId);

            // Verify user has access to this connector's brand
            if (!Auth::user()->brands()->where('brands.id', $connector->brand_id)->exists()) {
                return redirect()->route('dashboard')
                    ->with('error', 'You do not have access to this social connector');
            }

            DB::beginTransaction();

            $platform = $connector->platform;
            $accountName = $connector->account_name;

            // Soft delete or deactivate the connector
            $connector->update(['active' => false]);
            // Optionally: $connector->delete();

            DB::commit();

            Log::info('Social account disconnected', [
                'connector_id' => $connectorId,
                'platform' => $platform,
                'account_name' => $accountName,
            ]);

            return redirect()->route('dashboard')
                ->with('success', ucfirst($platform) . " account '{$accountName}' has been disconnected.");
        } catch (Exception $e) {
            DB::rollBack();

            Log::error('Failed to disconnect social account', [
                'connector_id' => $connectorId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return redirect()->route('dashboard')
                ->with('error', 'Failed to disconnect social account: ' . $e->getMessage());
        }
    }
}
