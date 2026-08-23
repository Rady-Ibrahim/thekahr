<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->enum('employee_type', ['manager', 'employee', 'driver_representative'])
                ->default('employee')
                ->after('department');
        });

        // Backfill from existing roles when possible
        $managerUserIds = DB::table('role_user')
            ->join('roles', 'roles.id', '=', 'role_user.role_id')
            ->where(function ($q) {
                $q->where('roles.name', 'manager')
                    ->orWhere('roles.name', 'like', '%_manager');
            })
            ->pluck('role_user.user_id');

        if ($managerUserIds->isNotEmpty()) {
            DB::table('employees')
                ->whereIn('user_id', $managerUserIds)
                ->update(['employee_type' => 'manager']);
        }

        $driverUserIds = DB::table('role_user')
            ->join('roles', 'roles.id', '=', 'role_user.role_id')
            ->where('roles.name', 'driver')
            ->pluck('role_user.user_id');

        if ($driverUserIds->isNotEmpty()) {
            DB::table('employees')
                ->whereIn('user_id', $driverUserIds)
                ->where('employee_type', 'employee')
                ->update(['employee_type' => 'driver_representative']);
        }

        // Anyone who already has subordinates is treated as a manager
        $managerIds = DB::table('employees')
            ->whereNotNull('reporting_manager_id')
            ->distinct()
            ->pluck('reporting_manager_id');

        if ($managerIds->isNotEmpty()) {
            DB::table('employees')
                ->whereIn('id', $managerIds)
                ->update(['employee_type' => 'manager']);
        }
    }

    public function down(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->dropColumn('employee_type');
        });
    }
};
