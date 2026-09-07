<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * Define the application's command schedule.
     *
     * Note: closing forgotten/stale attendance sessions is now handled by the
     * application itself (Self-cleaning Code) via:
     *   - CustomAttendanceService::autoCloseStaleSessions() on check-in/check-out,
     *   - AutoCloseStaleAttendanceMiddleware on any authenticated request.
     * No scheduled task is required for correctness; the auto-close command
     * remains available for on-demand manual runs.
     */
    protected function schedule(Schedule $schedule): void
    {
        //
    }

    /**
     * Register the commands for the application.
     */
    protected function commands(): void
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }
}
