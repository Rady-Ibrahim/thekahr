<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->boolean('early_exit_penalty_enabled')->default(true)->after('daily_required_hours');
            $table->string('early_exit_deduction_type', 50)->nullable()->after('early_exit_penalty_enabled');
            $table->decimal('early_exit_deduction_value', 10, 2)->nullable()->after('early_exit_deduction_type');
        });
    }

    public function down(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->dropColumn([
                'early_exit_penalty_enabled',
                'early_exit_deduction_type',
                'early_exit_deduction_value',
            ]);
        });
    }
};