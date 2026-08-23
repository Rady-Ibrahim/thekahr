<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employee_messages', function (Blueprint $table) {
            $table->foreignId('group_id')
                  ->nullable()
                  ->constrained('chat_groups')
                  ->cascadeOnDelete()
                  ->after('receiver_id');

            $table->string('message_type', 50)
                  ->default('text')
                  ->after('message');

            $table->index('group_id');
        });
    }

    public function down(): void
    {
        Schema::table('employee_messages', function (Blueprint $table) {
            $table->dropForeign(['group_id']);
            $table->dropIndex(['group_id']);
            $table->dropColumn(['group_id', 'message_type']);
        });
    }
};
