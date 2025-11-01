<?php

namespace Database\Seeders;

use App\Models\Brand;
use App\Models\ContentDraft;
use App\Models\ContentVariant;
use App\Models\Topic;
use App\Models\User;
use Illuminate\Database\Seeder;

class ContentDraftSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $brands = Brand::all();
        $users = User::all();

        foreach ($brands as $brand) {
            // Get topics for this brand
            $topics = Topic::where('brand_id', $brand->id)
                ->where('status', Topic::USED)
                ->orWhere('status', Topic::QUEUED)
                ->get();

            foreach ($topics as $topic) {
                $status = $this->getRandomStatus();

                $draft = ContentDraft::create([
                    'brand_id' => $brand->id,
                    'category_id' => $topic->category_id,
                    'topic_id' => $topic->id,
                    'title' => $topic->title,
                    'body' => $this->generateBody($topic),
                    'seo_metadata' => $this->generateSEOMetadata($topic),
                    'keywords' => $topic->keywords,
                    'confidence_score' => $topic->confidence_score,
                    'status' => $status,
                    'approved_by' => in_array($status, [ContentDraft::APPROVED, ContentDraft::PUBLISHED])
                        ? $users->random()->id
                        : null,
                    'approved_at' => in_array($status, [ContentDraft::APPROVED, ContentDraft::PUBLISHED])
                        ? now()->subDays(rand(0, 5))
                        : null,
                    'published_at' => $status === ContentDraft::PUBLISHED
                        ? now()->subDays(rand(0, 10))
                        : null,
                    'generated_at' => now()->subDays(rand(1, 15)),
                ]);

                // Create variants for drafts that are approved or published
                if (in_array($status, [ContentDraft::APPROVED, ContentDraft::PUBLISHED])) {
                    $this->createVariants($draft);
                }
            }
        }

        // Also create some drafts without topics (pending review)
        foreach ($brands as $brand) {
            for ($i = 0; $i < 5; $i++) {
                ContentDraft::create([
                    'brand_id' => $brand->id,
                    'category_id' => $brand->categories()->inRandomOrder()->first()->id,
                    'topic_id' => null,
                    'title' => $this->generateRandomTitle(),
                    'body' => $this->generateRandomBody(),
                    'seo_metadata' => [
                        'meta_description' => 'Sample meta description for SEO purposes.',
                        'keywords' => ['sample', 'content', 'seo'],
                        'slug' => 'sample-content-' . uniqid(),
                    ],
                    'keywords' => ['sample', 'content'],
                    'confidence_score' => rand(60, 85) / 100,
                    'status' => ContentDraft::PENDING_REVIEW,
                    'generated_at' => now()->subDays(rand(0, 3)),
                ]);
            }
        }

        $this->command->info('Content drafts created successfully.');
    }

    /**
     * Get random draft status
     */
    protected function getRandomStatus(): string
    {
        $statuses = [
            ContentDraft::PENDING_REVIEW => 30,
            ContentDraft::APPROVED => 25,
            ContentDraft::PUBLISHED => 40,
            ContentDraft::REJECTED => 5,
        ];

        $rand = rand(1, 100);
        $cumulative = 0;

        foreach ($statuses as $status => $probability) {
            $cumulative += $probability;
            if ($rand <= $cumulative) {
                return $status;
            }
        }

        return ContentDraft::PENDING_REVIEW;
    }

    /**
     * Generate body content
     */
    protected function generateBody(Topic $topic): string
    {
        return <<<HTML
<h2>Introduction</h2>
<p>In today's rapidly evolving digital landscape, understanding {$topic->title} has become crucial for businesses and professionals alike. This comprehensive guide will walk you through the essential concepts, best practices, and actionable strategies.</p>

<h2>Key Concepts</h2>
<p>Before diving into the specifics, it's important to understand the foundational principles that underpin this topic. These concepts form the basis for all advanced techniques and methodologies.</p>

<h2>Best Practices</h2>
<ul>
<li>Start with a clear understanding of your goals and objectives</li>
<li>Follow industry-standard approaches and methodologies</li>
<li>Continuously monitor and optimize your implementation</li>
<li>Stay updated with the latest trends and developments</li>
<li>Measure results and iterate based on data-driven insights</li>
</ul>

<h2>Implementation Strategy</h2>
<p>Implementing these concepts requires a structured approach. Begin by assessing your current situation, then develop a roadmap that aligns with your business objectives. Focus on incremental improvements rather than trying to change everything at once.</p>

<h2>Common Challenges and Solutions</h2>
<p>While implementing these strategies, you may encounter various challenges. The key is to anticipate these obstacles and prepare solutions in advance. Regular review and adjustment of your approach will help you overcome these hurdles.</p>

<h2>Conclusion</h2>
<p>Mastering {$topic->title} is an ongoing journey that requires dedication, continuous learning, and adaptation. By following the principles and practices outlined in this guide, you'll be well-positioned to achieve success in this area.</p>
HTML;
    }

    /**
     * Generate SEO metadata
     */
    protected function generateSEOMetadata(Topic $topic): array
    {
        return [
            'meta_description' => substr($topic->description, 0, 155),
            'keywords' => $topic->keywords,
            'slug' => \Illuminate\Support\Str::slug($topic->title),
            'og_title' => $topic->title,
            'og_description' => $topic->description,
            'og_image' => 'https://via.placeholder.com/1200x630',
        ];
    }

    /**
     * Create content variants
     */
    protected function createVariants(ContentDraft $draft): void
    {
        $platforms = ['website', 'facebook', 'twitter', 'linkedin'];

        foreach ($platforms as $platform) {
            ContentVariant::create([
                'content_draft_id' => $draft->id,
                'platform' => $platform,
                'title' => $platform === 'twitter'
                    ? \Illuminate\Support\Str::limit($draft->title, 100)
                    : $draft->title,
                'content' => $this->generateVariantContent($draft, $platform),
                'formatting' => $this->getFormattingForPlatform($platform),
                'metadata' => $this->getMetadataForPlatform($platform, $draft),
                'status' => ContentVariant::STATUS_READY,
            ]);
        }
    }

    /**
     * Generate platform-specific content
     */
    protected function generateVariantContent(ContentDraft $draft, string $platform): string
    {
        return match ($platform) {
            'website' => $draft->body,
            'facebook' => $this->generateFacebookPost($draft),
            'twitter' => $this->generateTwitterPost($draft),
            'linkedin' => $this->generateLinkedInPost($draft),
            default => $draft->body,
        };
    }

    /**
     * Generate Facebook post
     */
    protected function generateFacebookPost(ContentDraft $draft): string
    {
        return "{$draft->title}\n\n" .
               \Illuminate\Support\Str::limit(strip_tags($draft->body), 200) .
               "\n\nRead more: [LINK]\n\n#" . implode(' #', array_slice($draft->keywords, 0, 3));
    }

    /**
     * Generate Twitter post
     */
    protected function generateTwitterPost(ContentDraft $draft): string
    {
        $title = \Illuminate\Support\Str::limit($draft->title, 200);
        $hashtags = '#' . implode(' #', array_slice($draft->keywords, 0, 2));

        return "{$title}\n\n{$hashtags}\n\n[LINK]";
    }

    /**
     * Generate LinkedIn post
     */
    protected function generateLinkedInPost(ContentDraft $draft): string
    {
        return "{$draft->title}\n\n" .
               \Illuminate\Support\Str::limit(strip_tags($draft->body), 300) .
               "\n\nLearn more: [LINK]\n\n#" . implode(' #', array_slice($draft->keywords, 0, 5));
    }

    /**
     * Get formatting for platform
     */
    protected function getFormattingForPlatform(string $platform): array
    {
        return match ($platform) {
            'facebook' => ['max_length' => 63206, 'supports_html' => false],
            'twitter' => ['max_length' => 280, 'supports_html' => false],
            'linkedin' => ['max_length' => 3000, 'supports_html' => false],
            default => ['supports_html' => true],
        };
    }

    /**
     * Get metadata for platform
     */
    protected function getMetadataForPlatform(string $platform, ContentDraft $draft): array
    {
        return match ($platform) {
            'facebook' => ['link' => $draft->brand->domain . '/blog/' . $draft->seo_metadata['slug']],
            'twitter' => ['link' => $draft->brand->domain . '/blog/' . $draft->seo_metadata['slug']],
            'linkedin' => ['link' => $draft->brand->domain . '/blog/' . $draft->seo_metadata['slug']],
            default => [],
        };
    }

    /**
     * Generate random title
     */
    protected function generateRandomTitle(): string
    {
        $titles = [
            'Understanding Modern Development Practices',
            'A Comprehensive Guide to Digital Transformation',
            'Essential Tips for Business Growth',
            'Innovative Strategies for Success',
            'The Future of Technology and Innovation',
        ];

        return $titles[array_rand($titles)];
    }

    /**
     * Generate random body
     */
    protected function generateRandomBody(): string
    {
        return '<p>This is sample content generated for development purposes. In production, this would be replaced with AI-generated content based on the selected topic and brand voice.</p>';
    }
}
