<?php

namespace Database\Seeders;

use App\Models\Brand;
use App\Models\WebsiteConnector;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Crypt;

class WebsiteConnectorSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $brands = Brand::all();

        foreach ($brands as $brand) {
            // Create a website connector for each brand
            WebsiteConnector::create([
                'brand_id' => $brand->id,
                'name' => $brand->name . ' Website',
                'driver' => 'mysql',
                'encrypted_credentials' => [
                    'host' => 'localhost',
                    'port' => '3306',
                    'database' => 'website_' . $brand->slug,
                    'username' => 'web_user',
                    'password' => 'secure_password',
                ],
                'table_name' => 'posts',
                'field_mapping' => [
                    'title' => 'post_title',
                    'body' => 'post_content',
                    'excerpt' => 'post_excerpt',
                    'slug' => 'post_name',
                    'status' => 'post_status',
                    'author_id' => 'post_author',
                    'created_at' => 'post_date',
                    'updated_at' => 'post_modified',
                ],
                'status_workflow' => [
                    'draft' => 'draft',
                    'pending' => 'pending',
                    'published' => 'publish',
                ],
                'slug_policy' => 'auto_generate',
                'timezone' => 'UTC',
                'last_tested_at' => now()->subDays(rand(1, 7)),
                'active' => true,
            ]);
        }

        $this->command->info('Website connectors created successfully.');
    }
}
