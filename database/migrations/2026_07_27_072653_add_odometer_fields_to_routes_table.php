<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('routes', function (Blueprint $table) {
            $table->decimal('odometer_start', 10, 2)->nullable()->after('distance_km');
            $table->decimal('odometer_end', 10, 2)->nullable()->after('odometer_start');
            $table->string('odometer_start_photo')->nullable()->after('odometer_end');
            $table->string('odometer_end_photo')->nullable()->after('odometer_start_photo');
            $table->decimal('actual_distance_km', 8, 2)->nullable()->after('odometer_end_photo');
            $table->text('odometer_notes')->nullable()->after('actual_distance_km');
            $table->foreignId('odometer_verified_by')->nullable()->after('odometer_notes')
                ->constrained('employees')->nullOnDelete();
            $table->timestamp('odometer_verified_at')->nullable()->after('odometer_verified_by');
        });
    }

    public function down(): void
    {
        Schema::table('routes', function (Blueprint $table) {
            $table->dropConstrainedForeignId('odometer_verified_by');
            $table->dropColumn([
                'odometer_start', 'odometer_end',
                'odometer_start_photo', 'odometer_end_photo',
                'actual_distance_km', 'odometer_notes', 'odometer_verified_at',
            ]);
        });
    }
};
