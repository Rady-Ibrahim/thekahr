<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('near_expiry_items', function (Blueprint $table) {
            $table->id();

            $table->string('name');
            $table->string('image')->nullable();

            $table->date('expiry_date');

            // الفرع كنص حر (لا يوجد جدول فروع)
            $table->string('branch', 150)->nullable();

            $table->decimal('unit_price', 10, 2)->default(0);
            $table->decimal('incentive_amount', 10, 2)->default(0);

            $table->unsignedInteger('stock_quantity')->default(0);

            $table->foreignId('created_by')
                  ->nullable()
                  ->constrained('users')
                  ->nullOnDelete();

            $table->timestamps();

            $table->index('expiry_date');
            $table->index('branch');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('near_expiry_items');
    }
};
