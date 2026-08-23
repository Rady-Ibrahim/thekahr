<?php

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        $permissions = [
            [
                'name'        => 'view_near_expiry',
                'group'       => 'near_expiry',
                'description' => 'عرض المنتجات قاربة الانتهاء وتسجيل المبيعات',
            ],
            [
                'name'        => 'manage_near_expiry',
                'group'       => 'near_expiry',
                'description' => 'إدارة المنتجات قاربة الانتهاء واعتماد المبيعات',
            ],
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission['name']], $permission);
        }

        // المدير يدير ويوافق على مبيعات فريقه
        $managerRole = Role::where('name', 'manager')->first();
        if ($managerRole) {
            $ids = Permission::whereIn('name', ['view_near_expiry', 'manage_near_expiry'])->pluck('id');
            if ($ids->isNotEmpty()) {
                $managerRole->permissions()->syncWithoutDetaching($ids);
            }
        }
    }

    public function down(): void
    {
        $names = ['view_near_expiry', 'manage_near_expiry'];

        $roles = Role::whereHas('permissions', function ($q) use ($names) {
            $q->whereIn('name', $names);
        })->get();

        foreach ($roles as $role) {
            $role->permissions()->detach(Permission::whereIn('name', $names)->pluck('id'));
        }

        Permission::whereIn('name', $names)->delete();
    }
};
