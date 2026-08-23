<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Deductions - already has 'reason', add transaction_reason_id
        Schema::table('deductions', function (Blueprint $table) {
            $table->foreignId('transaction_reason_id')->nullable()->constrained('transaction_reasons')->nullOnDelete();
        });

        // Incentives - already has 'description', add transaction_reason_id
        Schema::table('incentives', function (Blueprint $table) {
            $table->foreignId('transaction_reason_id')->nullable()->constrained('transaction_reasons')->nullOnDelete();
        });

        // Advances - rename notes to reason, add transaction_reason_id
        DB::statement('ALTER TABLE advances CHANGE notes reason TEXT NULL');
        Schema::table('advances', function (Blueprint $table) {
            $table->foreignId('transaction_reason_id')->nullable()->constrained('transaction_reasons')->nullOnDelete();
        });

        // Allowances - add reason column, add transaction_reason_id
        Schema::table('allowances', function (Blueprint $table) {
            $table->text('reason')->nullable()->after('notes');
            $table->foreignId('transaction_reason_id')->nullable()->constrained('transaction_reasons')->nullOnDelete();
        });

        // Commissions - add reason column, add transaction_reason_id
        Schema::table('commissions', function (Blueprint $table) {
            $table->text('reason')->nullable()->after('description');
            $table->foreignId('transaction_reason_id')->nullable()->constrained('transaction_reasons')->nullOnDelete();
        });

        // Car violations - rename notes to reason, add transaction_reason_id
        DB::statement('ALTER TABLE car_violations CHANGE notes reason TEXT NULL');
        Schema::table('car_violations', function (Blueprint $table) {
            $table->foreignId('transaction_reason_id')->nullable()->constrained('transaction_reasons')->nullOnDelete();
        });

        // Employee points - already has 'reason', add transaction_reason_id
        Schema::table('employee_points', function (Blueprint $table) {
            $table->foreignId('transaction_reason_id')->nullable()->constrained('transaction_reasons')->nullOnDelete();
        });

        // Salary component log - add reason column
        Schema::table('salary_components_log', function (Blueprint $table) {
            $table->text('reason')->nullable()->after('notes');
        });
    }

    public function down(): void
    {
        Schema::table('salary_components_log', function (Blueprint $table) {
            $table->dropColumn('reason');
        });

        Schema::table('employee_points', function (Blueprint $table) {
            $table->dropForeign(['transaction_reason_id']);
            $table->dropColumn('transaction_reason_id');
        });

        Schema::table('car_violations', function (Blueprint $table) {
            $table->dropForeign(['transaction_reason_id']);
            $table->dropColumn('transaction_reason_id');
            DB::statement('ALTER TABLE car_violations CHANGE reason notes TEXT NULL');
        });

        Schema::table('commissions', function (Blueprint $table) {
            $table->dropForeign(['transaction_reason_id']);
            $table->dropColumn(['transaction_reason_id', 'reason']);
        });

        Schema::table('allowances', function (Blueprint $table) {
            $table->dropForeign(['transaction_reason_id']);
            $table->dropColumn(['transaction_reason_id', 'reason']);
        });

        Schema::table('advances', function (Blueprint $table) {
            $table->dropForeign(['transaction_reason_id']);
            $table->dropColumn('transaction_reason_id');
            DB::statement('ALTER TABLE advances CHANGE reason notes TEXT NULL');
        });

        Schema::table('incentives', function (Blueprint $table) {
            $table->dropForeign(['transaction_reason_id']);
            $table->dropColumn('transaction_reason_id');
        });

        Schema::table('deductions', function (Blueprint $table) {
            $table->dropForeign(['transaction_reason_id']);
            $table->dropColumn('transaction_reason_id');
        });
    }
};
