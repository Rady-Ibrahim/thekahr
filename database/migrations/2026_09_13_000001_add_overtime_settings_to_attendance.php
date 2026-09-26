<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            if (!Schema::hasColumn('employees', 'overtime_enabled')) {
                $table->boolean('overtime_enabled')->default(true)->after('daily_required_hours');
            }
        });

        Schema::table('attendances', function (Blueprint $table) {
            if (!Schema::hasColumn('attendances', 'overtime_minutes')) {
                $table->unsignedInteger('overtime_minutes')->default(0)->after('hours_status');
            }

            if (!Schema::hasColumn('attendances', 'overtime_hours')) {
                $table->decimal('overtime_hours', 8, 2)->default(0)->after('overtime_minutes');
            }
        });

        // Optional per-shift required hours: when null the shift's own
        // start/end window is used to derive the required daily hours.
        if (Schema::hasTable('shifts') && !Schema::hasColumn('shifts', 'required_hours')) {
            Schema::table('shifts', function (Blueprint $table) {
                $table->decimal('required_hours', 5, 2)->nullable()->after('end_time');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('shifts') && Schema::hasColumn('shifts', 'required_hours')) {
            Schema::table('shifts', function (Blueprint $table) {
                $table->dropColumn('required_hours');
            });
        }

        Schema::table('attendances', function (Blueprint $table) {
            $table->dropColumn(['overtime_minutes', 'overtime_hours']);
        });

        Schema::table('employees', function (Blueprint $table) {
            $table->dropColumn('overtime_enabled');
        });
    }
};
