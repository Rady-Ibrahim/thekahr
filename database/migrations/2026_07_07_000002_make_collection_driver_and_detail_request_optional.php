<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->dropForeignIfExists('collections', 'driver_id');
        $this->dropForeignIfExists('collection_details', 'request_id');

        DB::statement('ALTER TABLE collections MODIFY driver_id BIGINT UNSIGNED NULL');
        DB::statement('ALTER TABLE collection_details MODIFY request_id BIGINT UNSIGNED NULL');

        Schema::table('collections', function ($table) {
            $table->foreign('driver_id')->references('id')->on('employees')->nullOnDelete();
        });
        Schema::table('collection_details', function ($table) {
            $table->foreign('request_id')->references('id')->on('requests')->nullOnDelete();
        });
    }

    public function down(): void
    {
        $this->dropForeignIfExists('collections', 'driver_id');
        $this->dropForeignIfExists('collection_details', 'request_id');

        DB::statement('UPDATE collections SET driver_id = 1 WHERE driver_id IS NULL');
        DB::statement('UPDATE collection_details SET request_id = 1 WHERE request_id IS NULL');
        DB::statement('ALTER TABLE collections MODIFY driver_id BIGINT UNSIGNED NOT NULL');
        DB::statement('ALTER TABLE collection_details MODIFY request_id BIGINT UNSIGNED NOT NULL');

        Schema::table('collections', function ($table) {
            $table->foreign('driver_id')->references('id')->on('employees')->cascadeOnDelete();
        });
        Schema::table('collection_details', function ($table) {
            $table->foreign('request_id')->references('id')->on('requests')->cascadeOnDelete();
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
