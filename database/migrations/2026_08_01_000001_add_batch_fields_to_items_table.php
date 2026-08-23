<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('items', function (Blueprint $table) {
            $table->string('company_name')->nullable()->after('category');
            $table->string('barcode')->nullable()->after('company_name');
            $table->string('batch_number')->nullable()->after('barcode');
            $table->date('expiry_date')->nullable()->after('batch_number');
            $table->boolean('is_available')->default(true)->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('items', function (Blueprint $table) {
            $table->dropColumn(['company_name', 'barcode', 'batch_number', 'expiry_date', 'is_available']);
        });
    }
};
