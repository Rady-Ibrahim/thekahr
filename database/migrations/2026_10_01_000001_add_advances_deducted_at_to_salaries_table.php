<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('salaries', 'advances_deducted_at')) {
            Schema::table('salaries', function (Blueprint $table) {
                // Stamped the moment advance installments are actually consumed by a
                // real disbursement. It makes the deduction idempotent: paying a
                // salary twice can never burn two installments of the same advance.
                $table->timestamp('advances_deducted_at')->nullable()->after('payment_date');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('salaries', 'advances_deducted_at')) {
            Schema::table('salaries', function (Blueprint $table) {
                $table->dropColumn('advances_deducted_at');
            });
        }
    }
};
