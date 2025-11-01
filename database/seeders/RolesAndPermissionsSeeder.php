<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class RolesAndPermissionsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Reset cached roles and permissions
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        // Create permissions
        $permissions = [
            'brands.manage',
            'connectors.manage',
            'content.create',
            'content.edit',
            'content.approve',
            'content.publish',
            'analytics.view',
            'settings.manage',
            'users.manage',
        ];

        foreach ($permissions as $permission) {
            \Spatie\Permission\Models\Permission::create(['name' => $permission]);
        }

        // Create roles and assign permissions

        // Super Admin - has all permissions
        $superAdmin = \Spatie\Permission\Models\Role::create(['name' => 'super_admin']);
        $superAdmin->givePermissionTo($permissions);

        // Brand Admin - can manage brands, connectors, users, and view analytics
        $brandAdmin = \Spatie\Permission\Models\Role::create(['name' => 'brand_admin']);
        $brandAdmin->givePermissionTo([
            'brands.manage',
            'connectors.manage',
            'content.create',
            'content.edit',
            'content.approve',
            'content.publish',
            'analytics.view',
            'settings.manage',
            'users.manage',
        ]);

        // Content Manager - can create, edit, approve, and publish content
        $contentManager = \Spatie\Permission\Models\Role::create(['name' => 'content_manager']);
        $contentManager->givePermissionTo([
            'content.create',
            'content.edit',
            'content.approve',
            'content.publish',
            'analytics.view',
        ]);

        // Reviewer - can create, edit, and approve content
        $reviewer = \Spatie\Permission\Models\Role::create(['name' => 'reviewer']);
        $reviewer->givePermissionTo([
            'content.create',
            'content.edit',
            'content.approve',
            'analytics.view',
        ]);

        // Analyst - can only view analytics and create content
        $analyst = \Spatie\Permission\Models\Role::create(['name' => 'analyst']);
        $analyst->givePermissionTo([
            'content.create',
            'analytics.view',
        ]);
    }
}
