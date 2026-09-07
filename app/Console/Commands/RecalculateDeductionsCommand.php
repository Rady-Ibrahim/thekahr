<?php

namespace App\Console\Commands;

use App\Models\Attendance;
use App\Services\AttendancePenaltyService;
use Illuminate\Console\Command;

class RecalculateDeductionsCommand extends Command
{
    protected $signature = 'attendance:recalculate-deductions
                            {--date= : Specific date YYYY-MM-DD to recalculate}
                            {--all : Process all records (no date filter)}
                            {--employee= : Restrict to a specific employee id}';

    protected $description = 'Recalculate attendance penalties and late/early deductions from shift rules';

    public function handle(AttendancePenaltyService $penaltyService): int
    {
        $query = Attendance::with(['employee', 'employee.shiftAssignments.shift', 'shift']);

        if ($this->option('date')) {
            $query->where('attendance_date', $this->option('date'));
        } elseif (!$this->option('all')) {
            $query->where('attendance_date', now()->toDateString());
        }

        if ($this->option('employee')) {
            $query->where('employee_id', (int) $this->option('employee'));
        }

        $records = $query->get();
        $count = $records->count();

        if ($count === 0) {
            $this->warn('No attendance records matched the given filters.');
            return self::SUCCESS;
        }

        $this->info("Processing {$count} attendance record(s)…");

        $bar = $this->output->createProgressBar($count);
        $bar->start();

        foreach ($records as $attendance) {
            // Resolution order: the record's own shift → employee's default assignment.
            $shift = $attendance->shift
                ?? $attendance->employee?->currentShift();

            if (!$shift) {
                $bar->advance();
                continue;
            }

            $penaltyService->recalculatePenaltyForAttendance($attendance, $shift);
            $bar->advance();
        }

        $bar->finish();
        $this->newLine();
        $this->info('Recalculation completed successfully.');

        return self::SUCCESS;
    }
}
