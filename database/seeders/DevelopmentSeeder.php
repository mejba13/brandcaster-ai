<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

/**
 * Development Seeder
 *
 * Comprehensive seeder for development environment.
 * Creates realistic data for testing and development.
 *
 * Usage:
 *   php artisan db:seed --class=DevelopmentSeeder
 *
 * This will populate the database with:
 * - 4 brands with complete configurations
 * - Users with different roles
 * - Categories for each brand
 * - Website and social media connectors
 * - Trending topics
 * - Content drafts in various states
 * - Published content with variants
 * - Publishing jobs and schedules
 * - Engagement metrics
 */
class DevelopmentSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->command->info('🌱 Starting development database seeding...');
        $this->command->newLine();

        // Run seeders in order
        $seeders = [
            'RolesAndPermissionsSeeder' => 'Setting up roles and permissions',
            'BrandSeeder' => 'Creating brands',
            'CategorySeeder' => 'Creating categories',
            'UserSeeder' => 'Creating users',
            'WebsiteConnectorSeeder' => 'Setting up website connectors',
            'SocialConnectorSeeder' => 'Setting up social media connectors',
            'TopicSeeder' => 'Discovering trending topics',
            'ContentDraftSeeder' => 'Generating content drafts',
            'PublishJobSeeder' => 'Creating publishing jobs',
            'MetricSeeder' => 'Collecting engagement metrics',
        ];

        foreach ($seeders as $seeder => $description) {
            $this->command->info("📦 {$description}...");

            $this->call($seeder);

            $this->command->info("   ✅ Complete");
            $this->command->newLine();
        }

        $this->command->newLine();
        $this->command->info('🎉 Development database seeding completed successfully!');
        $this->command->newLine();

        $this->displaySummary();
    }

    /**
     * Display seeding summary
     */
    protected function displaySummary(): void
    {
        $this->command->info('📊 Seeding Summary:');
        $this->command->newLine();

        $summary = [
            ['Resource', 'Count', 'Status'],
            ['Brands', \App\Models\Brand::count(), '✓'],
            ['Categories', \App\Models\Category::count(), '✓'],
            ['Users', \App\Models\User::count(), '✓'],
            ['Website Connectors', \App\Models\WebsiteConnector::count(), '✓'],
            ['Social Connectors', \App\Models\SocialConnector::count(), '✓'],
            ['Topics', \App\Models\Topic::count(), '✓'],
            ['Content Drafts', \App\Models\ContentDraft::count(), '✓'],
            ['Content Variants', \App\Models\ContentVariant::count(), '✓'],
            ['Publish Jobs', \App\Models\PublishJob::count(), '✓'],
            ['Metrics', \App\Models\Metric::count(), '✓'],
        ];

        $this->command->table($summary[0], array_slice($summary, 1));

        $this->command->newLine();
        $this->command->info('🔐 Default Login Credentials:');
        $this->command->info('   Email: admin@brandcaster.ai');
        $this->command->info('   Password: password');
        $this->command->newLine();

        $this->command->info('🚀 Quick Start Commands:');
        $this->command->info('   php artisan topics:discover --brand=mejba');
        $this->command->info('   php artisan content:generate-and-publish --brand=mejba --limit=2');
        $this->command->info('   php artisan content:schedule --brand=mejba --days=7');
        $this->command->newLine();
    }
}
