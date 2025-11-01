<?php

namespace Database\Seeders;

use App\Models\User;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Check if we're in a development environment
        $isDevelopment = app()->environment(['local', 'development']);

        if ($isDevelopment) {
            // Run comprehensive development seeder
            $this->command->info('Running development seeder...');
            $this->call(DevelopmentSeeder::class);
        } else {
            // Production: Run minimal seeders
            $this->command->info('Running production seeders...');

            // Run seeders in the correct order
            // 1. First create roles and permissions
            $this->call(RolesAndPermissionsSeeder::class);

            // 2. Create brands
            $this->call(BrandSeeder::class);

            // 3. Create categories (depends on brands)
            $this->call(CategorySeeder::class);

            // 4. Create users and assign to brands (depends on roles and brands)
            $this->call(UserSeeder::class);

            $this->command->info('Production seeding complete.');
            $this->command->info('Note: For development data, run: php artisan db:seed --class=DevelopmentSeeder');
        }
    }
}
