<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('collections', function (Blueprint $table) {
            $table->id();
            $table->string('collection_number')->unique();
            $table->foreignId('delivery_id')->constrained()->cascadeOnDelete();
            $table->foreignId('driver_id')->constrained('employees')->cascadeOnDelete();
            $table->decimal('total_amount', 14, 2);
            $table->enum('payment_method', ['cash', 'bank_transfer', 'check', 'instapay', 'fawry'])->default('cash');
            $table->enum('collection_status', ['pending', 'collected', 'deposited', 'rejected'])->default('pending');
            $table->date('collected_date')->nullable();
            $table->date('deposited_date')->nullable();
            $table->text('notes')->nullable();
            $table->string('check_number')->nullable();
            $table->date('check_due_date')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('collection_details', function (Blueprint $table) {
            $table->id();
            $table->foreignId('collection_id')->constrained()->cascadeOnDelete();
            $table->foreignId('request_id')->constrained()->cascadeOnDelete();
            $table->decimal('amount', 14, 2);
            $table->string('reference_number')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('collection_details');
        Schema::dropIfExists('collections');
    }
};
