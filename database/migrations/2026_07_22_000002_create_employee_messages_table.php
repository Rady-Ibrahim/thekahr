<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employee_messages', function (Blueprint $table) {
            $table->id();

            // المُرسِل والمُستقبِل (كلاهما موظفون)
            $table->foreignId('sender_id')
                  ->constrained('employees')
                  ->onDelete('cascade');

            $table->foreignId('receiver_id')
                  ->constrained('employees')
                  ->onDelete('cascade');

            $table->text('message');

            // هل قرأها المستقبِل؟
            $table->boolean('is_read')->default(false);
            $table->timestamp('read_at')->nullable();

            $table->timestamps();
            $table->softDeletes();

            // فهارس لتسريع جلب المحادثات
            $table->index(['sender_id', 'receiver_id']);
            $table->index(['receiver_id', 'is_read']);
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_messages');
    }
};
