<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employee_tab_permissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')
                  ->constrained('employees')
                  ->onDelete('cascade');
            $table->string('tab_name');        // اسم التاب (عربي أو انجليزي)
            $table->string('tab_key');         // مفتاح التاب للموبايل (e.g. salary, attendance)
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            // كل موظف يمكن أن يكون له نفس التاب مرة واحدة فقط
            $table->unique(['employee_id', 'tab_key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_tab_permissions');
    }
};
