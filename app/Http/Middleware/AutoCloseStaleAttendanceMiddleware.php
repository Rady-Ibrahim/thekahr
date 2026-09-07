<?php

namespace App\Http\Middleware;

use App\Services\AttendancePenaltyService;
use App\Services\CustomAttendanceService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AutoCloseStaleAttendanceMiddleware
{
    /**
     * Self-cleaning hook: any authenticated hit on the app closes forgotten
     * (stale) attendance sessions older than the 20-hour threshold, so the app
     * no longer depends on a cron job to stay tidy.
     *
     * - Admins / attendance managers trigger a full sweep for ALL employees
     *   (both custom attendance_logs and shift-based attendances).
     * - Regular employees only clean their own open sessions.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = auth()->user() ?? auth('sanctum')->user();

        if ($user === null) {
            return $next($request);
        }

        $customService = app(CustomAttendanceService::class);

        if ($user->hasRole('admin') || $user->hasRole('super_admin') || $user->hasPermission('manage_attendance')) {
            $customService->autoCloseStaleSessions();
            app(AttendancePenaltyService::class)->autoCloseForgotten();
        } else {
            $employeeId = $user->employee_id;

            if ($employeeId !== null) {
                $customService->autoCloseStaleSessions($employeeId);
            }
        }

        return $next($request);
    }
}