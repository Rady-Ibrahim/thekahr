<?php

namespace Database\Seeders;

// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            RoleAndPermissionSeeder::class,
            SuperAdminSeeder::class,
            DepartmentSeeder::class,
            ShiftSeeder::class,
        ]);

        if (env('SEED_MEDICAL_FRESH', false)) {
            $this->call(MedicalSystemFreshSeeder::class);
        }
    }
}
