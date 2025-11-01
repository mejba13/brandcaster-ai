<?php

namespace Database\Seeders;

use App\Models\ContentDraft;
use App\Models\ContentVariant;
use App\Models\PublishJob;
use App\Models\SocialConnector;
use App\Models\WebsiteConnector;
use Illuminate\Database\Seeder;

class PublishJobSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Get published drafts
        $publishedDrafts = ContentDraft::where('status', ContentDraft::PUBLISHED)->get();

        foreach ($publishedDrafts as $draft) {
            $variants = ContentVariant::where('content_draft_id', $draft->id)->get();

            foreach ($variants as $variant) {
                // Determine connector based on platform
                $connector = $this->getConnector($variant->platform, $draft->brand_id);

                if (!$connector) {
                    continue;
                }

                PublishJob::create([
                    'content_draft_id' => $draft->id,
                    'content_variant_id' => $variant->id,
                    'platform' => $variant->platform,
                    'connector_id' => $connector->id,
                    'status' => PublishJob::PUBLISHED,
                    'scheduled_at' => $draft->published_at->subMinutes(rand(5, 60)),
                    'published_at' => $draft->published_at,
                    'result' => $this->generatePublishResult($variant->platform, $draft),
                    'external_id' => $this->generateExternalId($variant->platform),
                ]);
            }
        }

        // Create some scheduled jobs for the future
        $approvedDrafts = ContentDraft::where('status', ContentDraft::APPROVED)
            ->limit(10)
            ->get();

        foreach ($approvedDrafts as $draft) {
            $variants = ContentVariant::where('content_draft_id', $draft->id)->get();

            foreach ($variants as $variant) {
                $connector = $this->getConnector($variant->platform, $draft->brand_id);

                if (!$connector) {
                    continue;
                }

                PublishJob::create([
                    'content_draft_id' => $draft->id,
                    'content_variant_id' => $variant->id,
                    'platform' => $variant->platform,
                    'connector_id' => $connector->id,
                    'status' => PublishJob::SCHEDULED,
                    'scheduled_at' => now()->addHours(rand(1, 48)),
                ]);
            }
        }

        $this->command->info('Publish jobs created successfully.');
    }

    /**
     * Get connector for platform
     */
    protected function getConnector(string $platform, string $brandId)
    {
        if ($platform === 'website') {
            return WebsiteConnector::where('brand_id', $brandId)->first();
        }

        return SocialConnector::where('brand_id', $brandId)
            ->where('platform', $platform)
            ->first();
    }

    /**
     * Generate publish result
     */
    protected function generatePublishResult(string $platform, ContentDraft $draft): array
    {
        return match ($platform) {
            'website' => [
                'success' => true,
                'inserted_id' => rand(1000, 9999),
                'url' => $draft->brand->domain . '/blog/' . $draft->seo_metadata['slug'],
            ],
            'facebook' => [
                'success' => true,
                'post_id' => 'fb_' . uniqid(),
                'url' => 'https://www.facebook.com/post/' . uniqid(),
            ],
            'twitter' => [
                'success' => true,
                'post_id' => 'tw_' . rand(1000000000000000000, 9999999999999999999),
                'url' => 'https://twitter.com/user/status/' . rand(1000000000000000000, 9999999999999999999),
            ],
            'linkedin' => [
                'success' => true,
                'post_id' => 'li_' . uniqid(),
                'url' => 'https://www.linkedin.com/feed/update/' . uniqid(),
            ],
            default => ['success' => true],
        };
    }

    /**
     * Generate external ID
     */
    protected function generateExternalId(string $platform): string
    {
        return match ($platform) {
            'website' => (string) rand(1000, 9999),
            'facebook' => 'fb_' . uniqid(),
            'twitter' => 'tw_' . rand(1000000000000000000, 9999999999999999999),
            'linkedin' => 'li_' . uniqid(),
            default => uniqid(),
        };
    }
}
