<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // `reject()` used to write status = 'paid', i.e. "مسدد" (fully settled), on a
        // rejected advance whose remaining_amount was still the full amount. The
        // column had no way to express "rejected", so give it one.
        if (Schema::hasColumn('advances', 'status')) {
            DB::statement("ALTER TABLE `advances` MODIFY `status` ENUM('pending','active','paid','partially_paid','rejected') NOT NULL DEFAULT 'pending'");
        }
    }

    public function down(): void
    {
        // map any rejected row onto the historical 'paid' value before narrowing
        DB::statement("UPDATE `advances` SET `status` = 'paid' WHERE `status` = 'rejected'");
        DB::statement("ALTER TABLE `advances` MODIFY `status` ENUM('pending','active','paid','partially_paid') NOT NULL DEFAULT 'pending'");
    }
};
