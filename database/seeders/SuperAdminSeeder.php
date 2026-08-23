<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class SuperAdminSeeder extends Seeder
{
    public function run(): void
    {
        // Ensure the super_admin role exists first
        $role = Role::firstOrCreate(
            ['name' => 'super_admin'],
            ['description' => 'مدير النظام']
        );

        // Create or update the super admin user
        $user = User::updateOrCreate(
            ['email' => 'admin@mphamedhr.com'],
            [
                'name'      => 'مدير النظام',
                'email'     => 'admin@mphamedhr.com',
                'password'  => Hash::make('Admin@123456'),
                'phone'     => '01000000000',
                'is_active' => true,
            ]
        );

        // Attach super_admin role if not already attached
        if (!$user->hasRole('super_admin')) {
            $user->roles()->attach($role);
        }

        $this->command->info('✓ Super Admin created successfully.');
        $this->command->info('  Email    : admin@mphamedhr.com');
        $this->command->info('  Password : Admin@123456');
        $this->command->warn('  ⚠  Change the password after first login!');
    }
}
