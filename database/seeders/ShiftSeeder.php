<?php

namespace Database\Seeders;

use App\Models\Shift;
use Illuminate\Database\Seeder;

class ShiftSeeder extends Seeder
{
    public function run(): void
    {
        $shift = Shift::create([
            'name' => 'فترة صباحية',
            'start_time' => '08:00',
            'end_time' => '17:00',
            'grace_period_minutes' => 15,
            'is_active' => true,
        ]);

        $shift->lateRules()->createMany([
            [
                'min_delay_minutes' => 1,
                'max_delay_minutes' => 119,
                'deduction_type' => 'minutes',
                'deduction_value' => null,
            ],
            [
                'min_delay_minutes' => 120,
                'max_delay_minutes' => null,
                'deduction_type' => 'half_day',
                'deduction_value' => null,
            ],
        ]);

        $shift->earlyExitRules()->createMany([
            [
                'min_early_minutes' => 1,
                'max_early_minutes' => 59,
                'deduction_type' => 'minutes',
                'deduction_value' => null,
            ],
            [
                'min_early_minutes' => 60,
                'max_early_minutes' => null,
                'deduction_type' => 'half_day',
                'deduction_value' => null,
            ],
        ]);
    }
}
