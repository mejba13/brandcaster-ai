<?php

namespace Database\Seeders;

use App\Models\Brand;
use App\Models\SocialConnector;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Crypt;

class SocialConnectorSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $brands = Brand::all();

        foreach ($brands as $brand) {
            // Facebook connector
            SocialConnector::create([
                'brand_id' => $brand->id,
                'platform' => SocialConnector::FACEBOOK,
                'account_name' => $brand->name . ' Page',
                'account_id' => 'fb_' . $brand->slug . '_123456',
                'encrypted_token' => Crypt::encryptString(json_encode([
                    'access_token' => 'test_facebook_token_' . $brand->slug,
                    'token_type' => 'Bearer',
                ])),
                'token_expires_at' => now()->addMonths(2),
                'platform_settings' => [
                    'page_id' => 'fb_page_' . $brand->slug,
                    'page_name' => $brand->name . ' Page',
                ],
                'rate_limits' => [
                    'posts_per_hour' => 5,
                    'posts_per_day' => 25,
                ],
                'last_posted_at' => now()->subHours(rand(1, 24)),
                'active' => true,
            ]);

            // Twitter connector
            SocialConnector::create([
                'brand_id' => $brand->id,
                'platform' => SocialConnector::TWITTER,
                'account_name' => '@' . $brand->slug,
                'account_id' => 'tw_' . $brand->slug . '_789012',
                'encrypted_token' => Crypt::encryptString(json_encode([
                    'access_token' => 'test_twitter_token_' . $brand->slug,
                    'access_token_secret' => 'test_twitter_secret_' . $brand->slug,
                ])),
                'token_expires_at' => null, // Twitter tokens don't expire
                'platform_settings' => [
                    'username' => $brand->slug,
                ],
                'rate_limits' => [
                    'posts_per_hour' => 15,
                    'posts_per_day' => 100,
                ],
                'last_posted_at' => now()->subHours(rand(1, 24)),
                'active' => true,
            ]);

            // LinkedIn connector
            SocialConnector::create([
                'brand_id' => $brand->id,
                'platform' => SocialConnector::LINKEDIN,
                'account_name' => $brand->name . ' Company',
                'account_id' => 'li_' . $brand->slug . '_345678',
                'encrypted_token' => Crypt::encryptString(json_encode([
                    'access_token' => 'test_linkedin_token_' . $brand->slug,
                    'token_type' => 'Bearer',
                ])),
                'token_expires_at' => now()->addMonths(2),
                'platform_settings' => [
                    'organization_id' => 'li_org_' . $brand->slug,
                    'organization_name' => $brand->name,
                ],
                'rate_limits' => [
                    'posts_per_hour' => 10,
                    'posts_per_day' => 30,
                ],
                'last_posted_at' => now()->subHours(rand(1, 24)),
                'active' => true,
            ]);
        }

        $this->command->info('Social connectors created successfully.');
    }
}
