<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('attendances', function (Blueprint $table) {
            $table->foreignId('check_in_location_id')->nullable()->constrained('work_locations')->nullOnDelete()->after('check_in_photo');
            $table->string('check_in_location_name')->nullable()->after('check_in_location_id');
            $table->boolean('is_within_location')->default(false)->after('check_in_location_name');
        });
    }

    public function down(): void
    {
        Schema::table('attendances', function (Blueprint $table) {
            $table->dropConstrainedForeignId('check_in_location_id');
            $table->dropColumn(['check_in_location_name', 'is_within_location']);
        });
    }
};
