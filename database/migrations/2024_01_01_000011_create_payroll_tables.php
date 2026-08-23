<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('incentives', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->integer('month');
            $table->integer('year');
            $table->decimal('amount', 12, 2);
            $table->string('incentive_type');
            $table->text('description')->nullable();
            $table->foreignId('approved_by_id')->nullable()->constrained('employees')->nullOnDelete();
            $table->enum('status', ['pending', 'approved', 'rejected', 'paid'])->default('pending');
            $table->timestamps();
        });

        Schema::create('deductions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->integer('month');
            $table->integer('year');
            $table->decimal('amount', 12, 2);
            $table->string('deduction_type');
            $table->text('reason')->nullable();
            $table->foreignId('applied_by_id')->nullable()->constrained('employees')->nullOnDelete();
            $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending');
            $table->timestamps();
        });

        Schema::create('advances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->decimal('amount', 12, 2);
            $table->date('advance_date');
            $table->integer('installments_count')->default(1);
            $table->decimal('installment_amount', 12, 2);
            $table->integer('paid_installments')->default(0);
            $table->integer('remaining_installments')->default(0);
            $table->decimal('remaining_amount', 12, 2);
            $table->enum('status', ['pending', 'active', 'paid', 'partially_paid'])->default('pending');
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('allowances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->string('allowance_type');
            $table->decimal('amount', 12, 2);
            $table->date('start_date');
            $table->date('end_date')->nullable();
            $table->boolean('recurring')->default(false);
            $table->enum('status', ['active', 'inactive', 'paused'])->default('active');
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('commissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->integer('month');
            $table->integer('year');
            $table->decimal('amount', 12, 2);
            $table->decimal('commission_rate', 5, 2)->nullable();
            $table->decimal('total_sales', 14, 2)->nullable();
            $table->text('description')->nullable();
            $table->enum('status', ['pending', 'approved', 'rejected', 'paid'])->default('pending');
            $table->foreignId('approved_by_id')->nullable()->constrained('employees')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('car_violations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->string('vehicle_number')->nullable();
            $table->string('violation_type');
            $table->date('violation_date');
            $table->string('violation_code')->nullable();
            $table->decimal('fine_amount', 12, 2);
            $table->enum('status', ['pending', 'paid', 'waived', 'disputed'])->default('pending');
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('car_violations');
        Schema::dropIfExists('commissions');
        Schema::dropIfExists('allowances');
        Schema::dropIfExists('advances');
        Schema::dropIfExists('deductions');
        Schema::dropIfExists('incentives');
    }
};
