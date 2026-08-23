<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('requests', function (Blueprint $table) {
            $table->integer('orders_count')->default(1)->after('items_count');
            $table->string('payment_type')->nullable()->after('total_amount');
            $table->foreignId('reviewer_employee_id')->nullable()->after('prepared_by_id')->constrained('employees')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('requests', function (Blueprint $table) {
            $table->dropConstrainedForeignId('reviewer_employee_id');
            $table->dropColumn(['orders_count', 'payment_type']);
        });
    }
};
