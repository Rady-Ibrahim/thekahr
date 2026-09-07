<?php

namespace App\Console\Commands;

use App\Services\AttendancePenaltyService;
use App\Services\CustomAttendanceService;
use Illuminate\Console\Command;

class AutoCloseForgottenAttendance extends Command
{
    protected $signature = 'attendance:auto-close-forgotten
                            {--employee= : Optional employee id to restrict the scan to}
                            {--now= : Optional "now" timestamp for testing the grace window}';

    protected $description = 'Auto-close open attendance sessions past their shift-end + grace (4h) or check-in + 20h for custom attendance';

    public function handle(
        AttendancePenaltyService $penaltyService,
        CustomAttendanceService $customService,
    ): int {
        $employeeId = $this->option('employee') !== null ? (int) $this->option('employee') : null;
        $now = $this->option('now') !== null ? \Carbon\Carbon::parse($this->option('now')) : null;

        // Standard shift-based attendance auto-close.
        $closed = $penaltyService->autoCloseForgotten($employeeId, $now);

        // Custom flexible attendance: close sessions older than auto_close_after_hours.
        $customClosed = $customService->autoCloseStaleSessions($employeeId);

        $this->info(sprintf(
            'Auto-closed %d standard + %d custom attendance session(s).',
            count($closed),
            $customClosed,
        ));

        return self::SUCCESS;
    }
}
