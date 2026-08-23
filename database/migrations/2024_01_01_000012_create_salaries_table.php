<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('salaries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->integer('month');
            $table->integer('year');
            $table->decimal('base_salary', 12, 2);
            $table->decimal('gross_salary', 14, 2)->default(0);
            $table->decimal('total_incentives', 12, 2)->default(0);
            $table->decimal('total_allowances', 12, 2)->default(0);
            $table->decimal('total_commissions', 12, 2)->default(0);
            $table->decimal('total_deductions', 12, 2)->default(0);
            $table->decimal('total_advances', 12, 2)->default(0);
            $table->decimal('total_violations', 12, 2)->default(0);
            $table->decimal('net_salary', 14, 2)->default(0);
            $table->enum('status', ['draft', 'pending_approval', 'approved', 'paid', 'rejected', 'on_hold'])->default('draft');
            $table->foreignId('approved_by_id')->nullable()->constrained('employees')->nullOnDelete();
            $table->enum('payment_method', ['cash', 'bank_transfer', 'check', 'instapay'])->default('bank_transfer');
            $table->datetime('payment_date')->nullable();
            $table->text('approval_notes')->nullable();
            $table->timestamps();
            $table->unique(['employee_id', 'month', 'year']);
        });

        Schema::create('salary_components_log', function (Blueprint $table) {
            $table->id();
            $table->foreignId('salary_id')->constrained()->cascadeOnDelete();
            $table->string('component_type');
            $table->string('component_name');
            $table->unsignedBigInteger('component_id')->nullable();
            $table->decimal('amount', 12, 2);
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('salary_components_log');
        Schema::dropIfExists('salaries');
    }
};
