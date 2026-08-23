<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('routes', function (Blueprint $table) {
            $table->id();
            $table->string('route_code')->unique();
            $table->string('route_name')->nullable();
            $table->string('start_point')->nullable();
            $table->string('end_point')->nullable();
            $table->decimal('distance_km', 8, 2)->default(0);
            $table->integer('estimated_time_minutes')->default(0);
            $table->json('waypoints')->nullable();
            $table->enum('status', ['active', 'inactive', 'archived'])->default('active');
            $table->timestamps();
        });

        Schema::create('deliveries', function (Blueprint $table) {
            $table->id();
            $table->string('delivery_number')->unique();
            $table->foreignId('request_id')->constrained()->cascadeOnDelete();
            $table->foreignId('route_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('driver_id')->constrained('employees')->cascadeOnDelete();
            $table->string('vehicle_number')->nullable();
            $table->enum('status', ['pending', 'in_transit', 'completed', 'failed', 'partially_delivered'])->default('pending');
            $table->decimal('start_latitude', 10, 8)->nullable();
            $table->decimal('start_longitude', 11, 8)->nullable();
            $table->decimal('end_latitude', 10, 8)->nullable();
            $table->decimal('end_longitude', 11, 8)->nullable();
            $table->datetime('start_time')->nullable();
            $table->datetime('end_time')->nullable();
            $table->text('delivery_notes')->nullable();
            $table->string('delivery_photo')->nullable();
            $table->string('signature_proof')->nullable();
            $table->json('delivery_items')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('delivery_checkpoints', function (Blueprint $table) {
            $table->id();
            $table->foreignId('delivery_id')->constrained()->cascadeOnDelete();
            $table->integer('checkpoint_order');
            $table->string('location_name')->nullable();
            $table->decimal('latitude', 10, 8)->nullable();
            $table->decimal('longitude', 11, 8)->nullable();
            $table->datetime('expected_time')->nullable();
            $table->datetime('actual_time')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('vehicle_tracking', function (Blueprint $table) {
            $table->id();
            $table->foreignId('delivery_id')->constrained()->cascadeOnDelete();
            $table->decimal('latitude', 10, 8);
            $table->decimal('longitude', 11, 8);
            $table->decimal('speed', 8, 2)->nullable();
            $table->string('direction')->nullable();
            $table->datetime('captured_at');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vehicle_tracking');
        Schema::dropIfExists('delivery_checkpoints');
        Schema::dropIfExists('deliveries');
        Schema::dropIfExists('routes');
    }
};
