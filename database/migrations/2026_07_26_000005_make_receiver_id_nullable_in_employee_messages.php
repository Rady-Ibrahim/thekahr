<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employee_messages', function (Blueprint $table) {
            $table->dropForeign(['receiver_id']);
            $table->foreignId('receiver_id')->nullable()->change();
            $table->foreign('receiver_id')->references('id')->on('employees')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('employee_messages', function (Blueprint $table) {
            $table->dropForeign(['receiver_id']);
            $table->foreignId('receiver_id')->nullable(false)->change();
            $table->foreign('receiver_id')->references('id')->on('employees')->cascadeOnDelete();
        });
    }
};
