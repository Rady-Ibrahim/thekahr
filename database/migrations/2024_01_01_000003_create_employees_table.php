<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employees', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('employee_code')->unique();
            $table->string('name');
            $table->string('email')->unique()->nullable();
            $table->string('phone')->unique();
            $table->string('phone_alternative')->nullable();
            $table->string('national_id')->unique()->nullable();
            $table->date('date_of_birth')->nullable();
            $table->date('joining_date');
            $table->string('position');
            $table->string('department');
            $table->string('salary_type')->default('monthly');
            $table->decimal('base_salary', 12, 2);
            $table->enum('status', ['active', 'inactive', 'suspended', 'resigned', 'on_leave'])->default('active');
            $table->string('car_license')->nullable();
            $table->string('car_number')->nullable();
            $table->string('gps_device_id')->nullable();
            $table->foreignId('reporting_manager_id')->nullable()->constrained('employees')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employees');
    }
};
