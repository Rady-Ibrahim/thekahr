<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ideal_employees', function (Blueprint $table) {
            $table->id();

            $table->foreignId('employee_id')
                  ->nullable()
                  ->constrained('employees')
                  ->nullOnDelete();

            // 'month' = الموظف المثالي للشهر | 'week' = الموظف المثالي لأسبوع
            $table->enum('period', ['month', 'week']);

            $table->unsignedSmallInteger('month');
            $table->unsignedSmallInteger('year');

            // رقم الأسبوع داخل الشهر (1..N) - إجباري في حالة period = week
            $table->unsignedTinyInteger('week')->nullable();

            $table->foreignId('created_by_id')
                  ->nullable()
                  ->constrained('users')
                  ->nullOnDelete();

            $table->timestamps();
            $table->softDeletes();

            $table->unique(['period', 'year', 'month', 'week']);
            $table->index(['year', 'month']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ideal_employees');
    }
};