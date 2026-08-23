<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('near_expiry_sales', function (Blueprint $table) {
            $table->id();

            $table->foreignId('near_expiry_item_id')
                  ->constrained('near_expiry_items')
                  ->restrictOnDelete();

            $table->foreignId('employee_id')
                  ->constrained('employees')
                  ->restrictOnDelete();

            $table->string('branch', 150)->nullable();
            $table->string('invoice_number', 100)->nullable();
            $table->date('invoice_date');

            $table->unsignedInteger('quantity_sold');

            // Snapshot وقت البيع
            $table->decimal('unit_price', 10, 2);
            $table->decimal('unit_incentive', 10, 2);
            $table->decimal('total_incentive', 12, 2);

            // شهر وسنة الفاتورة للربط بالرواتب
            $table->unsignedSmallInteger('month');
            $table->unsignedSmallInteger('year');

            $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending');

            $table->foreignId('approved_by')
                  ->nullable()
                  ->constrained('employees')
                  ->nullOnDelete();

            // الحافز المُنشأ تلقائياً عند الاعتماد
            $table->foreignId('incentive_id')
                  ->nullable()
                  ->constrained('incentives')
                  ->nullOnDelete();

            $table->foreignId('created_by')
                  ->nullable()
                  ->constrained('users')
                  ->nullOnDelete();

            $table->timestamps();

            $table->index(['employee_id', 'year', 'month', 'status']);
            $table->index(['near_expiry_item_id']);
            $table->index('invoice_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('near_expiry_sales');
    }
};
