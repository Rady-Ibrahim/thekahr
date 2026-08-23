<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->boolean('is_custom_attendance')->default(false)->after('base_salary');
            $table->decimal('daily_required_hours', 5, 2)->nullable()->after('is_custom_attendance');
        });
    }

    public function down(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->dropColumn(['is_custom_attendance', 'daily_required_hours']);
        });
    }
};
