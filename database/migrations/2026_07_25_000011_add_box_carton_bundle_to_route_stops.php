<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('route_stops', function (Blueprint $table) {
            $table->integer('boxes_count')->default(0)->after('packages_count');
            $table->integer('cartons_count')->default(0)->after('boxes_count');
            $table->integer('bundles_count')->default(0)->after('cartons_count');
        });
    }

    public function down(): void
    {
        Schema::table('route_stops', function (Blueprint $table) {
            $table->dropColumn(['boxes_count', 'cartons_count', 'bundles_count']);
        });
    }
};
