<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('requests', function (Blueprint $table) {
            $table->id();
            $table->string('request_number')->unique();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->string('customer_name')->nullable();
            $table->string('company_name')->nullable();
            $table->string('warehouse')->nullable();
            $table->foreignId('assigned_employee_id')->nullable()->constrained('employees')->nullOnDelete();
            $table->integer('items_count')->default(0);
            $table->integer('total_quantity')->default(0);
            $table->decimal('total_amount', 14, 2)->default(0);
            $table->enum('status', [
                'draft', 'prepared', 'under_review', 'approved', 'rejected',
                'ready_for_delivery', 'in_delivery', 'delivered', 'collected', 'closed'
            ])->default('draft');
            $table->foreignId('created_by_id')->constrained('employees')->cascadeOnDelete();
            $table->foreignId('prepared_by_id')->nullable()->constrained('employees')->nullOnDelete();
            $table->foreignId('reviewed_by_id')->nullable()->constrained('employees')->nullOnDelete();
            $table->foreignId('approved_by_id')->nullable()->constrained('employees')->nullOnDelete();
            $table->datetime('prepared_at')->nullable();
            $table->datetime('reviewed_at')->nullable();
            $table->datetime('approved_at')->nullable();
            $table->date('estimated_delivery_date')->nullable();
            $table->datetime('actual_delivery_date')->nullable();
            $table->text('notes')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('request_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('request_id')->constrained()->cascadeOnDelete();
            $table->foreignId('item_id')->constrained()->cascadeOnDelete();
            $table->integer('quantity');
            $table->string('unit');
            $table->decimal('unit_price', 12, 2);
            $table->decimal('total_price', 14, 2);
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('request_items');
        Schema::dropIfExists('requests');
    }
};
