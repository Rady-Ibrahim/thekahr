<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employee_points', function (Blueprint $table) {
            $table->id();

            $table->foreignId('employee_id')
                  ->constrained('employees')
                  ->onDelete('cascade');

            // 'credit' = له (+) | 'debit' = عليه (-)
            $table->enum('type', ['credit', 'debit'])->default('credit');

            $table->decimal('points', 10, 2);             // عدد النقاط
            $table->decimal('point_price', 10, 2);        // سعر النقطة الواحدة وقت الإضافة
            $table->decimal('total_amount', 12, 2);       // الإجمالي (points * point_price)

            $table->text('reason');                        // سبب النقاط

            $table->unsignedSmallInteger('month');
            $table->unsignedSmallInteger('year');

            $table->foreignId('created_by_id')
                  ->nullable()
                  ->constrained('users')
                  ->nullOnDelete();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['employee_id', 'year', 'month']);
            $table->index('type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_points');
    }
};
