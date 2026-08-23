<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('routes', function (Blueprint $table) {
            $table->foreignId('driver_id')->nullable()->after('route_name')->constrained('employees')->nullOnDelete();
            $table->foreignId('sales_rep_id')->nullable()->after('driver_id')->constrained('employees')->nullOnDelete();
            $table->string('vehicle_number')->nullable()->after('sales_rep_id');
        });

        Schema::create('route_stops', function (Blueprint $table) {
            $table->id();
            $table->foreignId('route_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->integer('stop_order')->default(1);
            $table->json('request_ids')->nullable();
            $table->integer('packages_count')->default(0);
            $table->decimal('expected_amount', 14, 2)->nullable();
            $table->text('goods_notes')->nullable();
            $table->enum('delivery_status', ['pending', 'delivered', 'not_delivered'])->default('pending');
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->index(['route_id', 'stop_order']);
        });

        Schema::table('deliveries', function (Blueprint $table) {
            $table->foreignId('sales_rep_id')->nullable()->after('driver_id')->constrained('employees')->nullOnDelete();
            $table->foreignId('route_stop_id')->nullable()->after('route_id')->constrained('route_stops')->nullOnDelete();
            $table->decimal('expected_collection_amount', 14, 2)->nullable()->after('vehicle_number');
            $table->integer('packages_count')->default(0)->after('expected_collection_amount');
            $table->foreignId('collection_notify_employee_id')->nullable()->after('packages_count')->constrained('employees')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('deliveries', function (Blueprint $table) {
            $table->dropConstrainedForeignId('collection_notify_employee_id');
            $table->dropColumn(['packages_count', 'expected_collection_amount']);
            $table->dropConstrainedForeignId('route_stop_id');
            $table->dropConstrainedForeignId('sales_rep_id');
        });

        Schema::dropIfExists('route_stops');

        Schema::table('routes', function (Blueprint $table) {
            $table->dropColumn('vehicle_number');
            $table->dropConstrainedForeignId('sales_rep_id');
            $table->dropConstrainedForeignId('driver_id');
        });
    }
};
