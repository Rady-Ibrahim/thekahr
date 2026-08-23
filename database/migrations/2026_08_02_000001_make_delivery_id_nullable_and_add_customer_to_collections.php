<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->dropForeignKeyIfExists('collections', 'delivery_id');

        DB::statement('ALTER TABLE collections MODIFY delivery_id BIGINT UNSIGNED NULL');

        Schema::table('collections', function ($table) {
            $table->foreignId('customer_id')->nullable()->after('driver_id')->constrained('customers')->nullOnDelete();
            $table->foreign('delivery_id')->references('id')->on('deliveries')->nullOnDelete();
        });
    }

    public function down(): void
    {
        $this->dropForeignKeyIfExists('collections', 'delivery_id');
        $this->dropForeignKeyIfExists('collections', 'customer_id');

        Schema::table('collections', function ($table) {
            $table->dropColumn('customer_id');
        });

        DB::statement('UPDATE collections SET delivery_id = 1 WHERE delivery_id IS NULL');
        DB::statement('ALTER TABLE collections MODIFY delivery_id BIGINT UNSIGNED NOT NULL');

        Schema::table('collections', function ($table) {
            $table->foreign('delivery_id')->references('id')->on('deliveries')->cascadeOnDelete();
        });
    }

    private function dropForeignKeyIfExists(string $table, string $column): void
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
