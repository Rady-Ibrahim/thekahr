<?php

use App\Models\Permission;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        Permission::firstOrCreate(
            ['name' => 'view_ideal_employees'],
            [
                'name'        => 'view_ideal_employees',
                'group'       => 'dashboard',
                'description' => 'عرض واختيار الموظف المثالي',
            ]
        );
    }

    public function down(): void
    {
        Permission::where('name', 'view_ideal_employees')->delete();
    }
};