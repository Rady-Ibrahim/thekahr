<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->dropForeignIfExists('deliveries', 'request_id');
        $this->dropForeignIfExists('deliveries', 'driver_id');

        DB::statement('ALTER TABLE deliveries MODIFY request_id BIGINT UNSIGNED NULL');
        DB::statement('ALTER TABLE deliveries MODIFY driver_id BIGINT UNSIGNED NULL');

        Schema::table('deliveries', function ($table) {
            $table->foreign('request_id')->references('id')->on('requests')->nullOnDelete();
            $table->foreign('driver_id')->references('id')->on('employees')->nullOnDelete();
        });
    }

    public function down(): void
    {
        $this->dropForeignIfExists('deliveries', 'request_id');
        $this->dropForeignIfExists('deliveries', 'driver_id');

        DB::statement('UPDATE deliveries SET request_id = 1 WHERE request_id IS NULL');
        DB::statement('UPDATE deliveries SET driver_id = 1 WHERE driver_id IS NULL');
        DB::statement('ALTER TABLE deliveries MODIFY request_id BIGINT UNSIGNED NOT NULL');
        DB::statement('ALTER TABLE deliveries MODIFY driver_id BIGINT UNSIGNED NOT NULL');

        Schema::table('deliveries', function ($table) {
            $table->foreign('request_id')->references('id')->on('requests')->cascadeOnDelete();
            $table->foreign('driver_id')->references('id')->on('employees')->cascadeOnDelete();
        });
    }

    private function dropForeignIfExists(string $table, string $column): void
    {
        $constraint = DB::table('information_schema.KEY_COLUMN_USAGE')
            ->where('TABLE_SCHEMA', DB::getDatabaseName())
            ->where('TABLE_NAME', $table)
            ->where('COLUMN_NAME', $column)
            ->whereNotNull('REFERENCED_TABLE_NAME')
            ->value('CONSTRAINT_NAME');

        if ($constraint) {
            DB::statement("ALTER TABLE `{$table}` DROP FOREIGN KEY `{$constraint}`");
        }
    }
};
