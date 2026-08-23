<?php

namespace Database\Seeders;

use App\Models\Department;
use Illuminate\Database\Seeder;

class DepartmentSeeder extends Seeder
{
    private const DEPARTMENTS = [
        ['name' => 'الأمن', 'description' => 'الأمن'],
        ['name' => 'السكرتاريه', 'description' => 'السكرتاريه'],
        ['name' => 'البوفيه', 'description' => 'البوفيه'],
        ['name' => 'الحسابات', 'description' => 'الحسابات'],
        ['name' => 'السيلز', 'description' => 'السيلز'],
        ['name' => 'المشتروات', 'description' => 'المشتروات'],
        ['name' => 'المخزن', 'description' => 'المخزن [ محضر مراجع مقفل تشغيله استلام تسليم ]'],
        ['name' => 'التوزيع', 'description' => 'التوزيع [ مندوب ]'],
        ['name' => 'النقل', 'description' => 'النقل [ سائق ]'],
        ['name' => 'الصيانه', 'description' => 'الصيانه'],
        ['name' => 'الشئون القانونيه', 'description' => 'الشئون القانونيه'],
        ['name' => 'الأرشيف', 'description' => 'الأرشيف'],
    ];

    public function run(): void
    {
        foreach (self::DEPARTMENTS as $dept) {
            Department::firstOrCreate(
                ['name' => $dept['name']],
                ['description' => $dept['description']]
            );
        }

        $this->command->info('✓ Departments seeded successfully.');
    }
}
