<?php

namespace Database\Seeders;

use App\Models\Brand;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Create super admin user
        $superAdmin = User::create([
            'name' => 'Admin User',
            'email' => 'admin@brandcaster.ai',
            'password' => Hash::make('password'),
            'email_verified_at' => now(),
            'active' => true,
        ]);

        // Assign super_admin role
        $superAdmin->assignRole('super_admin');

        // Get all brands
        $brands = Brand::all();

        // Link super admin to all brands with admin role
        foreach ($brands as $brand) {
            $superAdmin->brands()->attach($brand->id, [
                'role' => 'admin',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }
}
