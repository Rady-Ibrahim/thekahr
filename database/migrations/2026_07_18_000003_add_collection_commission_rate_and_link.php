<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->decimal('collection_commission_rate', 5, 2)->nullable()->after('base_salary')
                ->comment('نسبة عمولة التحصيل للسائق/المندوب %');
        });

        Schema::table('commissions', function (Blueprint $table) {
            $table->foreignId('collection_id')->nullable()->after('employee_id')
                ->constrained('collections')->nullOnDelete();
            $table->string('source')->nullable()->after('description')
                ->comment('manual|collection');
        });
    }

    public function down(): void
    {
        Schema::table('commissions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('collection_id');
            $table->dropColumn('source');
        });

        Schema::table('employees', function (Blueprint $table) {
            $table->dropColumn('collection_commission_rate');
        });
    }
};
