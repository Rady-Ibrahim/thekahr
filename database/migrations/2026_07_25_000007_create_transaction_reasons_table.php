<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('transaction_reasons', function (Blueprint $table) {
            $table->id();
            $table->string('type'); // deduction, incentive, advance, allowance, commission, violation, point
            $table->string('name_ar');
            $table->string('name_en')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        DB::table('transaction_reasons')->insert([
            // Deductions
            ['type' => 'deduction', 'name_ar' => 'غياب', 'name_en' => 'Absence'],
            ['type' => 'deduction', 'name_ar' => 'تأخر', 'name_en' => 'Late'],
            ['type' => 'deduction', 'name_ar' => 'جزاء', 'name_en' => 'Penalty'],
            // Incentives
            ['type' => 'incentive', 'name_ar' => 'أداء متميز', 'name_en' => 'Performance'],
            ['type' => 'incentive', 'name_ar' => 'حضور كامل', 'name_en' => 'Attendance'],
            ['type' => 'incentive', 'name_ar' => 'مبيعات', 'name_en' => 'Sales'],
            ['type' => 'incentive', 'name_ar' => 'ساعات إضافية', 'name_en' => 'Overtime'],
            // Advances
            ['type' => 'advance', 'name_ar' => 'سلفة عادية', 'name_en' => 'Regular advance'],
            ['type' => 'advance', 'name_ar' => 'سلفة طارئة', 'name_en' => 'Emergency advance'],
            ['type' => 'advance', 'name_ar' => 'سلفة علاج', 'name_en' => 'Medical advance'],
            // Allowances
            ['type' => 'allowance', 'name_ar' => 'بدل انتقال', 'name_en' => 'Transportation'],
            ['type' => 'allowance', 'name_ar' => 'بدل سفر', 'name_en' => 'Travel'],
            ['type' => 'allowance', 'name_ar' => 'بدل مبيعات', 'name_en' => 'Sales allowance'],
            ['type' => 'allowance', 'name_ar' => 'بدل إجازات', 'name_en' => 'Vacation allowance'],
            // Commissions
            ['type' => 'commission', 'name_ar' => 'عمولة تحصيل', 'name_en' => 'Collection commission'],
            ['type' => 'commission', 'name_ar' => 'عمولة مبيعات', 'name_en' => 'Sales commission'],
            // Car violations
            ['type' => 'violation', 'name_ar' => 'سرعة زائدة', 'name_en' => 'Speeding'],
            ['type' => 'violation', 'name_ar' => 'مخالفة وقوف', 'name_en' => 'Parking'],
            ['type' => 'violation', 'name_ar' => 'حادث', 'name_en' => 'Accident'],
            ['type' => 'violation', 'name_ar' => 'إشارة حمراء', 'name_en' => 'Red light'],
            // Points
            ['type' => 'point', 'name_ar' => 'إنجاز', 'name_en' => 'Achievement'],
            ['type' => 'point', 'name_ar' => 'التزام', 'name_en' => 'Commitment'],
            ['type' => 'point', 'name_ar' => 'مخالفة', 'name_en' => 'Violation'],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('transaction_reasons');
    }
};
