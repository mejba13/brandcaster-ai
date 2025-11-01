<?php

namespace Database\Seeders;

use App\Models\Brand;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class BrandSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $brands = [
            [
                'name' => 'Mejba Personal Portfolio',
                'slug' => 'mejba',
                'domain' => 'https://www.mejba.me',
                'brand_voice' => [
                    'tone' => 'Professional yet approachable, technical but accessible',
                    'audience' => 'Tech professionals, potential clients, fellow developers',
                    'style' => 'Educational, insightful, showcasing expertise without being pretentious',
                    'lexicon' => [
                        'preferred' => ['innovative', 'solution-oriented', 'user-centric', 'scalable', 'modern'],
                        'avoid' => ['revolutionary', 'game-changing', 'disruptive', 'ninja', 'rockstar'],
                    ],
                ],
                'style_guide' => [
                    'dos' => [
                        'Focus on practical solutions and real-world applications',
                        'Share technical insights and best practices',
                        'Highlight problem-solving approach',
                        'Use code examples where appropriate',
                        'Maintain a personal, authentic voice',
                    ],
                    'donts' => [
                        'Overpromise or exaggerate capabilities',
                        'Use overly technical jargon without explanation',
                        'Criticize other developers or technologies',
                        'Share unfinished or untested work',
                    ],
                    'blocklist' => ['cheap', 'quick fix', 'hack', 'trick', 'secret'],
                ],
                'settings' => [
                    'auto_approve_threshold' => 0.85,
                    'auto_publish' => false,
                    'posts_per_day' => 2,
                    'quiet_hours' => [
                        'start' => '22:00',
                        'end' => '08:00',
                        'timezone' => 'UTC',
                    ],
                ],
                'active' => true,
            ],
            [
                'name' => 'Ramlit Limited',
                'slug' => 'ramlit',
                'domain' => 'https://www.ramlit.com',
                'brand_voice' => [
                    'tone' => 'Professional, authoritative, business-focused',
                    'audience' => 'Business decision-makers, enterprise clients, stakeholders',
                    'style' => 'Strategic, results-driven, emphasizing ROI and business value',
                    'lexicon' => [
                        'preferred' => ['enterprise', 'strategic', 'scalable', 'efficient', 'ROI', 'transformation', 'optimization'],
                        'avoid' => ['cheap', 'basic', 'simple', 'easy', 'budget'],
                    ],
                ],
                'style_guide' => [
                    'dos' => [
                        'Emphasize business outcomes and value proposition',
                        'Use data and metrics to support claims',
                        'Highlight enterprise-grade solutions',
                        'Focus on security, reliability, and scalability',
                        'Showcase case studies and success stories',
                    ],
                    'donts' => [
                        'Use casual or informal language',
                        'Make technical details too prominent over business value',
                        'Promise unrealistic timelines or results',
                        'Discuss pricing without context',
                    ],
                    'blocklist' => ['cheap', 'amateur', 'experimental', 'beta', 'untested'],
                ],
                'settings' => [
                    'auto_approve_threshold' => 0.90,
                    'auto_publish' => false,
                    'posts_per_day' => 3,
                    'quiet_hours' => [
                        'start' => '18:00',
                        'end' => '09:00',
                        'timezone' => 'UTC',
                    ],
                ],
                'active' => true,
            ],
            [
                'name' => 'ColorPark Creative Agency',
                'slug' => 'colorpark',
                'domain' => 'https://www.colorpark.io',
                'brand_voice' => [
                    'tone' => 'Creative, vibrant, inspiring, energetic',
                    'audience' => 'Creative professionals, startups, brands seeking fresh ideas',
                    'style' => 'Bold, imaginative, trend-aware, visually focused',
                    'lexicon' => [
                        'preferred' => ['creative', 'vibrant', 'innovative', 'bold', 'fresh', 'inspiring', 'unique', 'artistic'],
                        'avoid' => ['boring', 'standard', 'typical', 'corporate', 'traditional'],
                    ],
                ],
                'style_guide' => [
                    'dos' => [
                        'Showcase visual creativity and design thinking',
                        'Highlight unique and original approaches',
                        'Share design trends and creative insights',
                        'Use vivid, descriptive language',
                        'Emphasize brand personality and storytelling',
                    ],
                    'donts' => [
                        'Use generic stock imagery or concepts',
                        'Be overly formal or stuffy',
                        'Ignore current design trends',
                        'Downplay the importance of aesthetics',
                    ],
                    'blocklist' => ['boring', 'plain', 'ordinary', 'standard', 'generic'],
                ],
                'settings' => [
                    'auto_approve_threshold' => 0.80,
                    'auto_publish' => true,
                    'posts_per_day' => 4,
                    'quiet_hours' => [
                        'start' => '23:00',
                        'end' => '07:00',
                        'timezone' => 'UTC',
                    ],
                ],
                'active' => true,
            ],
            [
                'name' => 'xCyberSecurity Global Services',
                'slug' => 'xcybersecurity',
                'domain' => 'https://www.xcybersecurity.io',
                'brand_voice' => [
                    'tone' => 'Authoritative, serious, trustworthy, vigilant',
                    'audience' => 'Security professionals, IT decision-makers, compliance officers',
                    'style' => 'Technical, detailed, security-focused, proactive',
                    'lexicon' => [
                        'preferred' => ['secure', 'protected', 'compliant', 'vigilant', 'robust', 'hardened', 'threat', 'vulnerability'],
                        'avoid' => ['hack-proof', 'unhackable', '100% secure', 'guaranteed', 'foolproof'],
                    ],
                ],
                'style_guide' => [
                    'dos' => [
                        'Provide accurate, up-to-date security information',
                        'Explain threats clearly without fearmongering',
                        'Reference industry standards and compliance frameworks',
                        'Use specific technical details when relevant',
                        'Emphasize proactive security measures',
                    ],
                    'donts' => [
                        'Make absolute security guarantees',
                        'Use fear-based marketing tactics',
                        'Oversimplify complex security issues',
                        'Disclose sensitive vulnerability details publicly',
                    ],
                    'blocklist' => ['hack-proof', 'unhackable', '100% secure', 'guaranteed safe', 'completely protected'],
                ],
                'settings' => [
                    'auto_approve_threshold' => 0.95,
                    'auto_publish' => false,
                    'posts_per_day' => 2,
                    'quiet_hours' => [
                        'start' => '20:00',
                        'end' => '06:00',
                        'timezone' => 'UTC',
                    ],
                ],
                'active' => true,
            ],
        ];

        foreach ($brands as $brandData) {
            Brand::create($brandData);
        }
    }
}
