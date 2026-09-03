<?php

namespace App\Console\Commands;

use App\Services\AttendancePenaltyService;
use Illuminate\Console\Command;

class AutoCloseForgottenAttendance extends Command
{
    protected $signature = 'attendance:auto-close-forgotten
                            {--employee= : Optional employee id to restrict the scan to}
                            {--now= : Optional "now" timestamp for testing the grace window}';

    protected $description = 'Auto-close open attendance sessions past their shift-end + grace (4h)';

    public function handle(AttendancePenaltyService $penaltyService): int
    {
        $employeeId = $this->option('employee') !== null ? (int) $this->option('employee') : null;
        $now = $this->option('now') !== null ? \Carbon\Carbon::parse($this->option('now')) : null;

        $closed = $penaltyService->autoCloseForgotten($employeeId, $now);

        $this->info(sprintf('Auto-closed %d forgotten attendance session(s).', count($closed)));

        return self::SUCCESS;
    }
}
