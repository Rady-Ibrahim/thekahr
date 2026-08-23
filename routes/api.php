<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\EmployeeController;
use App\Http\Controllers\Api\AttendanceController;
use App\Http\Controllers\Api\IncentiveController;
use App\Http\Controllers\Api\DeductionController;
use App\Http\Controllers\Api\AdvanceController;
use App\Http\Controllers\Api\AllowanceController;
use App\Http\Controllers\Api\SalaryController;
use App\Http\Controllers\Api\ReportController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\WorkLocationController;
use App\Http\Controllers\Api\FinancialController;
use App\Http\Controllers\Api\EmployeeTabPermissionController;
use App\Http\Controllers\Api\EmployeeMessageController;
use App\Http\Controllers\Api\DepartmentController;
use App\Http\Controllers\Api\EmployeePointController;
use App\Http\Controllers\Api\ShiftController;
use App\Http\Controllers\Api\EmployeeShiftController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\ChatGroupController;
use App\Http\Controllers\Api\IdealEmployeeController;
use App\Http\Controllers\Api\NearExpiryItemController;
use App\Http\Controllers\Api\NearExpirySaleController;


// Public
Route::post('/auth/login', [AuthController::class, 'login']);

// Protected
Route::middleware('auth:sanctum')->group(function () {

    // Auth
    Route::post('/auth/logout',           [AuthController::class, 'logout']);
    Route::get('/auth/me',                [AuthController::class, 'me']);
    Route::put('/auth/profile',           [AuthController::class, 'updateProfile']);
    Route::post('/auth/change-password',  [AuthController::class, 'changePassword']);
    Route::post('/client-devices',        [AuthController::class, 'storeDeviceToken']);
    Route::delete('/client-devices',       [AuthController::class, 'destroyDeviceToken']);

    // Mobile: my financial transactions
    Route::get('/me/financials', [FinancialController::class, 'myFinancials']);

    // Departments
    Route::get('/departments', [DepartmentController::class, 'index']);

    // Mobile: my allowed tabs
    Route::get('/me/tabs', [EmployeeTabPermissionController::class, 'myTabs']);

    // Mobile: my points history
    Route::get('/me/points', [EmployeePointController::class, 'myPoints']);

    // Admin: Employee Points
    Route::prefix('employee-points')->group(function () {
        Route::get('/',        [EmployeePointController::class, 'index']);
        Route::post('/',       [EmployeePointController::class, 'store']);
        Route::get('/{id}',    [EmployeePointController::class, 'show']);
        Route::delete('/{id}', [EmployeePointController::class, 'destroy']);
    });

    // Ideal Employee / الموظف المثالي (Dashboard)
    Route::get('/ideal',    [IdealEmployeeController::class, 'index']);
    Route::post('/ideal',   [IdealEmployeeController::class, 'store']);
    Route::delete('/ideal', [IdealEmployeeController::class, 'destroy']);

    // ── Near-Expiry Items & Sales Incentives / المنتجات قاربة الانتهاء ─────
    Route::prefix('near-expiry-items')->group(function () {
        Route::get('/',        [NearExpiryItemController::class, 'index']);
        Route::post('/',       [NearExpiryItemController::class, 'store']);
        Route::put('/{id}',    [NearExpiryItemController::class, 'update']);
        Route::delete('/{id}', [NearExpiryItemController::class, 'destroy']);
    });

    Route::prefix('near-expiry-sales')->group(function () {
        Route::get('/leaderboard',   [NearExpirySaleController::class, 'leaderboard']);
        Route::get('/',              [NearExpirySaleController::class, 'index']);
        Route::post('/',             [NearExpirySaleController::class, 'store']);
        Route::post('/{id}/approve', [NearExpirySaleController::class, 'approve']);
        Route::post('/{id}/reject',  [NearExpirySaleController::class, 'reject']);
        Route::delete('/{id}',       [NearExpirySaleController::class, 'destroy']);
    });

    // ── Employee Messaging ──────────────────────────────────────────────────
    Route::prefix('messages')->group(function () {
        Route::get('/unread-count',        [EmployeeMessageController::class, 'unreadCount']);
        Route::get('/',                    [EmployeeMessageController::class, 'conversations']);
        Route::post('/',                   [EmployeeMessageController::class, 'send']);
        Route::get('/{employeeId}',        [EmployeeMessageController::class, 'conversation']);
        Route::put('/{employeeId}/read',   [EmployeeMessageController::class, 'markRead']);
        Route::delete('/{messageId}',      [EmployeeMessageController::class, 'destroy']);
    });

    // ── Chat Groups ────────────────────────────────────────────────────────
    Route::prefix('chat-groups')->group(function () {
        Route::get('/my',                  [ChatGroupController::class, 'myGroups']);
        Route::get('/',                    [ChatGroupController::class, 'index']);
        Route::post('/',                   [ChatGroupController::class, 'store']);
        Route::get('/{id}',                [ChatGroupController::class, 'show']);
        Route::put('/{id}',                [ChatGroupController::class, 'update']);
        Route::delete('/{id}',             [ChatGroupController::class, 'destroy']);
        Route::post('/{id}/members',       [ChatGroupController::class, 'addMembers']);
        Route::delete('/{id}/members/{employeeId}', [ChatGroupController::class, 'removeMember']);
        Route::put('/{id}/members/{employeeId}/role', [ChatGroupController::class, 'updateMemberRole']);
        Route::get('/{id}/messages',       [ChatGroupController::class, 'messages']);
        Route::post('/{id}/read',          [ChatGroupController::class, 'markRead']);
    });

    // Admin: Employee Tab Permissions
    Route::prefix('employee-tabs')->group(function () {
        Route::get('/',           [EmployeeTabPermissionController::class, 'index']);
        Route::get('/available',  [EmployeeTabPermissionController::class, 'availableTabs']);
        Route::get('/{id}',       [EmployeeTabPermissionController::class, 'show']);
        Route::post('/{id}',      [EmployeeTabPermissionController::class, 'save']);
        Route::delete('/{id}',    [EmployeeTabPermissionController::class, 'destroy']);
    });

    // Dashboard
    Route::prefix('dashboard')->group(function () {
        Route::get('/metrics',             [DashboardController::class, 'metrics']);
        Route::get('/employees-chart',     [DashboardController::class, 'employeesChart']);
        Route::get('/attendance-chart',    [DashboardController::class, 'attendanceChart']);
    });

    // Employees
    Route::prefix('employees')->group(function () {
        Route::get('/mobile-list',          [EmployeeController::class, 'mobileList']);
        Route::get('/export',               [EmployeeController::class, 'export']);
        Route::get('/',                    [EmployeeController::class, 'index']);
        Route::post('/',                   [EmployeeController::class, 'store']);
        Route::get('/managers',            [EmployeeController::class, 'managers']);
        Route::get('/peers',               [EmployeeController::class, 'peers']);
        Route::get('/me/manager',          [EmployeeController::class, 'myManager']);
        Route::get('/me/subordinates',     [EmployeeController::class, 'mySubordinates']);
        Route::get('/{id}',                [EmployeeController::class, 'show']);
        Route::put('/{id}',                [EmployeeController::class, 'update']);
        Route::delete('/{id}',             [EmployeeController::class, 'destroy']);
        Route::put('/{id}/status',         [EmployeeController::class, 'updateStatus']);
        Route::put('/{id}/manager',        [EmployeeController::class, 'updateManager']);
        Route::get('/{id}/subordinates',   [EmployeeController::class, 'subordinates']);
        Route::put('/{id}/subordinates',   [EmployeeController::class, 'assignSubordinates']);
        Route::post('/{id}/reset-password', [EmployeeController::class, 'resetPassword']);
        Route::get('/{id}/salary-history', [EmployeeController::class, 'getSalaryHistory']);
        Route::get('/{id}/attendance',     [EmployeeController::class, 'getAttendanceRecords']);
        Route::get('/{id}/financial-statement', [FinancialController::class, 'employeeFinancials']);
    });

    // Shifts
    Route::prefix('shifts')->group(function () {
        Route::get('/',                    [ShiftController::class, 'index']);
        Route::post('/',                   [ShiftController::class, 'store']);
        Route::get('/{id}',                [ShiftController::class, 'show']);
        Route::put('/{id}',                [ShiftController::class, 'update']);
        Route::delete('/{id}',             [ShiftController::class, 'destroy']);
    });

    // Employee Shift Assignments
    Route::prefix('employee-shifts')->group(function () {
        Route::get('/',                    [EmployeeShiftController::class, 'index']);
        Route::post('/',                   [EmployeeShiftController::class, 'store']);
        Route::post('/bulk',               [EmployeeShiftController::class, 'bulkStore']);
        Route::get('/current/{employeeId}', [EmployeeShiftController::class, 'current']);
        Route::delete('/{id}',             [EmployeeShiftController::class, 'destroy']);
    });

    // Attendance
    Route::prefix('attendance')->group(function () {
        Route::get('/today-summary',              [AttendanceController::class, 'todaySummary']);
        Route::get('/my-records',                 [AttendanceController::class, 'myRecords']);
        Route::get('/leave-requests',             [AttendanceController::class, 'leaveRequests']);
        Route::get('/monthly-report/{empId}',     [AttendanceController::class, 'monthlyReport']);
        Route::get('/custom/today',               [AttendanceController::class, 'customToday']);
        Route::get('/',                           [AttendanceController::class, 'index']);
        Route::post('/',                          [AttendanceController::class, 'store']);
        Route::get('/{id}',                       [AttendanceController::class, 'show']);
        Route::get('/{id}/penalty-details',       [AttendanceController::class, 'penaltyDetails']);
        Route::get('/{id}/sessions',              [AttendanceController::class, 'daySessions']);
        Route::post('/{id}/sessions',             [AttendanceController::class, 'sessionStore']);
        Route::put('/sessions/{logId}',           [AttendanceController::class, 'sessionUpdate']);
        Route::delete('/sessions/{logId}',        [AttendanceController::class, 'sessionDestroy']);
        Route::put('/{id}',                       [AttendanceController::class, 'update']);
        Route::delete('/{id}',                    [AttendanceController::class, 'destroy']);
        Route::post('/check-in',                  [AttendanceController::class, 'checkIn']);
        Route::post('/check-out',                 [AttendanceController::class, 'checkOut']);
        Route::post('/request-leave',             [AttendanceController::class, 'requestLeave']);
        Route::post('/leave-requests/{id}/approve', [AttendanceController::class, 'approveLeave']);
    });

    // Incentives
    Route::prefix('incentives')->group(function () {
        Route::get('/',              [IncentiveController::class, 'index']);
        Route::post('/',             [IncentiveController::class, 'store']);
        Route::get('/{id}',          [IncentiveController::class, 'show']);
        Route::put('/{id}',          [IncentiveController::class, 'update']);
        Route::delete('/{id}',       [IncentiveController::class, 'destroy']);
        Route::post('/{id}/approve', [IncentiveController::class, 'approve']);
        Route::post('/{id}/reject',  [IncentiveController::class, 'reject']);
    });

    // Deductions
    Route::prefix('deductions')->group(function () {
        Route::get('/',              [DeductionController::class, 'index']);
        Route::post('/',             [DeductionController::class, 'store']);
        Route::get('/{id}',          [DeductionController::class, 'show']);
        Route::delete('/{id}',       [DeductionController::class, 'destroy']);
        Route::post('/{id}/approve', [DeductionController::class, 'approve']);
        Route::post('/{id}/reject',  [DeductionController::class, 'reject']);
    });

    // Advances
    Route::prefix('advances')->group(function () {
        Route::get('/employee/{empId}/summary', [AdvanceController::class, 'employeeSummary']);
        Route::get('/',              [AdvanceController::class, 'index']);
        Route::post('/',             [AdvanceController::class, 'store']);
        Route::get('/{id}',          [AdvanceController::class, 'show']);
        Route::put('/{id}',          [AdvanceController::class, 'update']);
        Route::delete('/{id}',       [AdvanceController::class, 'destroy']);
        Route::post('/{id}/approve', [AdvanceController::class, 'approve']);
        Route::post('/{id}/reject',  [AdvanceController::class, 'reject']);
    });

    // Allowances
    Route::prefix('allowances')->group(function () {
        Route::get('/types',             [AllowanceController::class, 'types']);
        Route::get('/employee/{empId}', [AllowanceController::class, 'employeeAllowances']);
        Route::get('/',              [AllowanceController::class, 'index']);
        Route::post('/',             [AllowanceController::class, 'store']);
        Route::get('/{id}',          [AllowanceController::class, 'show']);
        Route::put('/{id}',          [AllowanceController::class, 'update']);
        Route::delete('/{id}',       [AllowanceController::class, 'destroy']);
    });

    // Salaries
    Route::prefix('salaries')->group(function () {
        Route::get('/monthly-summary',         [SalaryController::class, 'monthlySummary']);
        Route::post('/calculate',              [SalaryController::class, 'calculate']);
        Route::post('/bulk-approve',           [SalaryController::class, 'bulkApprove']);
        Route::post('/employee/{empId}/calculate', [SalaryController::class, 'calculateSingle']);
        Route::get('/',                        [SalaryController::class, 'index']);
        Route::get('/{id}',                    [SalaryController::class, 'show']);
        Route::post('/{id}/approve',           [SalaryController::class, 'approve']);
        Route::post('/{id}/pay',               [SalaryController::class, 'pay']);
    });

    // Reports
    Route::prefix('reports')->group(function () {
        Route::get('/employees',        [ReportController::class, 'employees']);
        Route::get('/attendance',       [ReportController::class, 'attendance']);
        Route::get('/salaries',         [ReportController::class, 'salaries']);
        Route::get('/incentives',       [ReportController::class, 'incentivesReport']);
        Route::get('/monthly-summary',  [ReportController::class, 'monthlyAdminSummary']);
    });

    // Work Locations
    Route::prefix('work-locations')->group(function () {
        Route::get('/',        [WorkLocationController::class, 'index']);
        Route::post('/',       [WorkLocationController::class, 'store']);
        Route::get('/{id}',    [WorkLocationController::class, 'show']);
        Route::put('/{id}',    [WorkLocationController::class, 'update']);
        Route::delete('/{id}', [WorkLocationController::class, 'destroy']);
    });

    // Notifications
    Route::prefix('notifications')->group(function () {
        Route::get('/unread-count',    [NotificationController::class, 'unreadCount']);
        Route::post('/read-all',       [NotificationController::class, 'markAllRead']);
        Route::post('/send',           [NotificationController::class, 'send']);
        Route::get('/',                [NotificationController::class, 'index']);
        Route::post('/{id}/read',      [NotificationController::class, 'markRead']);
        Route::delete('/{id}',         [NotificationController::class, 'destroy']);
    });

    // Users
    Route::prefix('users')->group(function () {
        Route::get('/',                    [UserController::class, 'index']);
        Route::post('/',                   [UserController::class, 'store']);
        Route::get('/roles',               [UserController::class, 'roles']);
        Route::get('/sidebar-permissions', [UserController::class, 'sidebarPermissions']);
        Route::get('/all-permissions',     [UserController::class, 'allPermissions']);
        Route::get('/{id}',                [UserController::class, 'show']);
        Route::put('/{id}/permissions',    [UserController::class, 'updatePermissions']);
    });
});
