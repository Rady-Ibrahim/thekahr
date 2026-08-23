<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('attendances', function (Blueprint $table) {
            $table->foreignId('shift_id')->nullable()->constrained()->nullOnDelete();
            $table->integer('early_exit_minutes')->default(0);
            $table->decimal('actual_worked_hours', 5, 2)->default(0.00);
            $table->string('applied_late_deduction_type', 50)->nullable();
            $table->string('applied_early_deduction_type', 50)->nullable();
            $table->decimal('deduction_amount', 10, 2)->default(0.00);
            $table->boolean('payroll_pushed')->default(false);
        });
    }

    public function down(): void
    {
        Schema::table('attendances', function (Blueprint $table) {
            $table->dropConstrainedForeignId('shift_id');
            $table->dropColumn([
                'early_exit_minutes',
                'actual_worked_hours',
                'applied_late_deduction_type',
                'applied_early_deduction_type',
                'deduction_amount',
                'payroll_pushed',
            ]);
        });
    }
};
