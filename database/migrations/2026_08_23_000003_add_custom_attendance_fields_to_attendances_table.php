<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('attendances', function (Blueprint $table) {
            $table->integer('total_worked_minutes')->default(0)->after('working_hours');
            $table->decimal('total_worked_hours', 5, 2)->default(0.00)->after('total_worked_minutes');
            $table->decimal('required_hours', 5, 2)->nullable()->after('total_worked_hours');
            $table->string('hours_status', 20)->nullable()->after('required_hours');
        });
    }

    public function down(): void
    {
        Schema::table('attendances', function (Blueprint $table) {
            $table->dropColumn(['total_worked_minutes', 'total_worked_hours', 'required_hours', 'hours_status']);
        });
    }
};
