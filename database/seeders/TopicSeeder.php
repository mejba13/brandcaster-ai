<?php

namespace Database\Seeders;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Topic;
use Illuminate\Database\Seeder;

class TopicSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $topicTemplates = [
            // Web Design & UI/UX
            'web-design' => [
                'Top 10 Web Design Trends for 2025',
                'Minimalist Design: Less is More in Modern Web Development',
                'Color Psychology in Web Design: A Complete Guide',
                'Responsive Design Best Practices for Mobile-First Approach',
                'Accessibility in Web Design: Making the Web Inclusive',
                'Dark Mode Design: Implementation and Best Practices',
                'Typography Trends Shaping Modern Websites',
                'Micro-interactions: Small Details That Make a Big Difference',
            ],
            // Software Development
            'software-development' => [
                'Clean Code Principles Every Developer Should Know',
                'Microservices vs Monolithic Architecture: Which to Choose?',
                'API Security Best Practices in 2025',
                'The Future of AI in Software Development',
                'Test-Driven Development: A Comprehensive Guide',
                'DevOps Culture: Breaking Down Silos Between Teams',
                'Performance Optimization Techniques for Web Applications',
                'Version Control Best Practices with Git',
            ],
            // Cloud Computing
            'cloud-computing' => [
                'AWS vs Azure vs Google Cloud: A Comprehensive Comparison',
                'Serverless Architecture: When and How to Use It',
                'Cloud Cost Optimization Strategies for Startups',
                'Kubernetes in Production: Lessons Learned',
                'Multi-Cloud Strategy: Benefits and Challenges',
                'Cloud Security: Protecting Your Data in the Cloud',
                'Infrastructure as Code: Terraform Best Practices',
                'Container Orchestration: Beyond the Basics',
            ],
            // Cybersecurity
            'cybersecurity' => [
                'Zero Trust Security: The Future of Network Security',
                'Ransomware Prevention: Protecting Your Organization',
                'Multi-Factor Authentication: Implementation Guide',
                'Penetration Testing Best Practices for 2025',
                'Security Compliance: GDPR, SOC 2, and ISO 27001',
                'Incident Response Planning: A Step-by-Step Guide',
                'Security Automation: Tools and Techniques',
                'Cloud Security Posture Management Essentials',
            ],
        ];

        $brands = Brand::all();

        foreach ($brands as $brand) {
            $categories = Category::where('brand_id', $brand->id)->get();

            foreach ($categories as $category) {
                // Get relevant topics for this category
                $topics = $topicTemplates[$category->slug] ?? [];

                foreach ($topics as $index => $title) {
                    Topic::create([
                        'brand_id' => $brand->id,
                        'category_id' => $category->id,
                        'title' => $title,
                        'description' => $this->generateDescription($title),
                        'keywords' => $this->generateKeywords($title, $category),
                        'source_urls' => $this->generateSourceUrls(),
                        'confidence_score' => rand(65, 98) / 100,
                        'trending_at' => now()->subDays(rand(0, 3)),
                        'status' => $this->getRandomStatus(),
                    ]);
                }
            }
        }

        $this->command->info('Topics created successfully.');
    }

    /**
     * Generate description for a topic
     */
    protected function generateDescription(string $title): string
    {
        $descriptions = [
            "Explore the latest insights and best practices in {topic}. Learn from industry experts and real-world case studies.",
            "Comprehensive guide covering everything you need to know about {topic}. Updated with the latest trends and techniques.",
            "Deep dive into {topic} with practical examples and actionable strategies for your business.",
            "Expert analysis of {topic} with data-driven insights and proven methodologies.",
        ];

        return str_replace('{topic}', strtolower($title), $descriptions[array_rand($descriptions)]);
    }

    /**
     * Generate keywords for a topic
     */
    protected function generateKeywords(string $title, Category $category): array
    {
        $keywords = array_slice($category->keywords, 0, 3);

        // Extract words from title
        $titleWords = array_filter(
            explode(' ', $title),
            fn($word) => strlen($word) > 4 && !in_array(strtolower($word), ['guide', 'complete', 'best', 'practices'])
        );

        return array_merge($keywords, array_slice($titleWords, 0, 2));
    }

    /**
     * Generate realistic source URLs
     */
    protected function generateSourceUrls(): array
    {
        $sources = [
            'https://techcrunch.com/article/' . uniqid(),
            'https://www.theverge.com/news/' . uniqid(),
            'https://news.ycombinator.com/item?id=' . rand(10000000, 99999999),
            'https://medium.com/@author/article-' . uniqid(),
        ];

        return array_slice($sources, 0, rand(1, 3));
    }

    /**
     * Get random topic status
     */
    protected function getRandomStatus(): string
    {
        $statuses = [
            Topic::DISCOVERED => 70,  // 70% discovered
            Topic::QUEUED => 15,      // 15% queued
            Topic::USED => 10,        // 10% used
            Topic::EXPIRED => 5,      // 5% expired
        ];

        $rand = rand(1, 100);
        $cumulative = 0;

        foreach ($statuses as $status => $probability) {
            $cumulative += $probability;
            if ($rand <= $cumulative) {
                return $status;
            }
        }

        return Topic::DISCOVERED;
    }
}
