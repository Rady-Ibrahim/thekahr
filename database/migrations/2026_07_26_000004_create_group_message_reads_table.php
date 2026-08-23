<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('group_message_reads', function (Blueprint $table) {
            $table->id();
            $table->foreignId('group_id')
                  ->constrained('chat_groups')
                  ->cascadeOnDelete();
            $table->foreignId('employee_id')
                  ->constrained('employees')
                  ->cascadeOnDelete();
            $table->timestamp('last_read_at')->nullable();
            $table->timestamps();

            $table->unique(['group_id', 'employee_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('group_message_reads');
    }
};
