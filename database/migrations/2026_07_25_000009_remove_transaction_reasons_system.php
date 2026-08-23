<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Must drop FKs before columns, then drop the parent table last
        Schema::table('salary_components_log', function (Blueprint $table) {
            $table->dropColumn('reason');
        });

        $tables = ['deductions', 'incentives', 'advances', 'allowances', 'commissions', 'car_violations', 'employee_points'];
        foreach ($tables as $table) {
            Schema::table($table, function (Blueprint $table) {
                $table->dropForeign(['transaction_reason_id']);
                $table->dropColumn('transaction_reason_id');
            });
        }

        Schema::dropIfExists('transaction_reasons');
    }

    public function down(): void
    {
        // Re-create table and columns (minimal — for rollback)
        Schema::create('transaction_reasons', function (Blueprint $table) {
            $table->id();
            $table->string('type');
            $table->string('name_ar');
            $table->string('name_en')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        $tables = ['deductions', 'incentives', 'advances', 'allowances', 'commissions', 'car_violations', 'employee_points'];
        foreach ($tables as $table) {
            Schema::table($table, function (Blueprint $table) {
                $table->foreignId('transaction_reason_id')->nullable()->constrained('transaction_reasons')->nullOnDelete();
            });
        }

        Schema::table('salary_components_log', function (Blueprint $table) {
            $table->text('reason')->nullable()->after('notes');
        });
    }
};
