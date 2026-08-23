<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\EmployeeController;
use App\Http\Controllers\Api\CustomerController;
use App\Http\Controllers\Api\WarehouseController;
use App\Http\Controllers\Api\ItemController;
use App\Http\Controllers\Api\RequestController;
use App\Http\Controllers\Api\RouteController;
use App\Http\Controllers\Api\DeliveryController;
use App\Http\Controllers\Api\CollectionController;
use App\Http\Controllers\Api\AttendanceController;
use App\Http\Controllers\Api\IncentiveController;
use App\Http\Controllers\Api\DeductionController;
use App\Http\Controllers\Api\AdvanceController;
use App\Http\Controllers\Api\AllowanceController;
use App\Http\Controllers\Api\CommissionController;
use App\Http\Controllers\Api\CarViolationController;
use App\Http\Controllers\Api\SalaryController;
use App\Http\Controllers\Api\ApprovalController;
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
use App\Http\Controllers\Api\CustomerExpectedAmountController;
use App\Http\Controllers\Api\ChatGroupController;
use App\Http\Controllers\Api\IdealEmployeeController;


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
        Route::get('/requests-chart',      [DashboardController::class, 'requestsChart']);
        Route::get('/attendance-chart',    [DashboardController::class, 'attendanceChart']);
        Route::get('/collections-chart',   [DashboardController::class, 'collectionsChart']);
        Route::get('/performance-metrics', [DashboardController::class, 'performanceMetrics']);
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

    // Customers
    Route::prefix('customers')->group(function () {
        Route::get('/',                          [CustomerController::class, 'index']);
        Route::post('/',                         [CustomerController::class, 'store']);
        Route::get('/{id}',                      [CustomerController::class, 'show']);
        Route::put('/{id}',                      [CustomerController::class, 'update']);
        Route::delete('/{id}',                   [CustomerController::class, 'destroy']);
        Route::get('/{id}/requests',             [CustomerController::class, 'requests']);
        Route::post('/{id}/assign-employee',     [CustomerController::class, 'assignEmployee']);
        Route::delete('/{id}/remove-employee/{employeeId}', [CustomerController::class, 'removeEmployee']);
        Route::get('/{customerId}/expected-amounts/history', [CustomerExpectedAmountController::class, 'history']);
    });

    // Customer Expected Amounts
    Route::prefix('customer-expected-amounts')->group(function () {
        Route::post('/',       [CustomerExpectedAmountController::class, 'store']);
        Route::put('/{id}',    [CustomerExpectedAmountController::class, 'update']);
        Route::delete('/{id}', [CustomerExpectedAmountController::class, 'destroy']);
    });

    // Warehouses
    Route::prefix('warehouses')->group(function () {
        Route::get('/',           [WarehouseController::class, 'index']);
        Route::post('/',          [WarehouseController::class, 'store']);
        Route::get('/{id}',       [WarehouseController::class, 'show']);
        Route::put('/{id}',       [WarehouseController::class, 'update']);
        Route::delete('/{id}',    [WarehouseController::class, 'destroy']);
        Route::get('/{id}/items', [WarehouseController::class, 'items']);
    });

    // Items
    Route::prefix('items')->group(function () {
        Route::get('/categories', [ItemController::class, 'categories']);
        Route::get('/',           [ItemController::class, 'index']);
        Route::post('/',          [ItemController::class, 'store']);
        Route::get('/{id}',       [ItemController::class, 'show']);
        Route::put('/{id}',       [ItemController::class, 'update']);
        Route::delete('/{id}',    [ItemController::class, 'destroy']);
    });

    // Requests
    Route::prefix('requests')->group(function () {
        Route::post('/prepaid',                 [RequestController::class, 'storePrepaid']);
        Route::get('/my',                       [RequestController::class, 'myRequests']);
        Route::get('/received/pending',         [RequestController::class, 'receivedPending']);
        Route::get('/reviewer/pending',         [RequestController::class, 'reviewerPending']);
        Route::get('/manager/pending',          [RequestController::class, 'managerPending']);
        Route::get('/',                        [RequestController::class, 'index']);
        Route::post('/',                       [RequestController::class, 'store']);
        Route::get('/{id}',                    [RequestController::class, 'show']);
        Route::put('/{id}',                    [RequestController::class, 'update']);
        Route::delete('/{id}',                 [RequestController::class, 'destroy']);
        Route::post('/{id}/items',             [RequestController::class, 'addItems']);
        Route::post('/{id}/prepare',           [RequestController::class, 'prepare']);
        Route::post('/{id}/submit-for-review', [RequestController::class, 'submitForReview']);
        Route::post('/{id}/submit-reviewer-review', [RequestController::class, 'submitReviewerReview']);
        Route::post('/{id}/transfer-to-employee', [RequestController::class, 'transferToEmployee']);
        Route::post('/{id}/reviewer-approve',  [RequestController::class, 'reviewerApprove']);
        Route::post('/{id}/reviewer-approve-final', [RequestController::class, 'reviewerApproveFinal']);
        Route::post('/{id}/reviewer-reject',   [RequestController::class, 'reviewerReject']);
        Route::post('/{id}/submit-manager-review', [RequestController::class, 'submitManagerReview']);
        Route::post('/{id}/manager-approve',   [RequestController::class, 'managerApprove']);
        Route::post('/{id}/manager-reject',    [RequestController::class, 'managerReject']);
        Route::post('/{id}/approve',           [RequestController::class, 'approve']);
        Route::post('/{id}/reject',            [RequestController::class, 'reject']);
    });

    // Delivery Routes (خطوط السير)
    Route::prefix('routes')->group(function () {
        Route::get('/daily',   [RouteController::class, 'daily']);
        Route::post('/with-stops', [RouteController::class, 'storeWithStops']);
        Route::get('/',        [RouteController::class, 'index']);
        Route::post('/',       [RouteController::class, 'store']);
        Route::get('/{id}',    [RouteController::class, 'show']);
        Route::put('/{id}',    [RouteController::class, 'update']);
        Route::delete('/{id}', [RouteController::class, 'destroy']);
        Route::get('/{id}/stops', [RouteController::class, 'stops']);
        Route::put('/{id}/with-stops', [RouteController::class, 'updateWithStops']);
        Route::post('/{id}/dispatch', [RouteController::class, 'dispatch']);
        Route::post('/{id}/deliveries', [RouteController::class, 'createDelivery']);
        Route::post('/{id}/odometer-start', [RouteController::class, 'recordOdometerStart']);
        Route::post('/{id}/odometer-end',   [RouteController::class, 'recordOdometerEnd']);
        Route::post('/{id}/odometer-verify', [RouteController::class, 'verifyOdometer']);
    });

    // Deliveries
    Route::prefix('deliveries')->group(function () {
        Route::get('/my',              [DeliveryController::class, 'driverDeliveries']);
        Route::get('/',                [DeliveryController::class, 'index']);
        Route::post('/',               [DeliveryController::class, 'store']);
        Route::get('/{id}',            [DeliveryController::class, 'show']);
        Route::put('/{id}',            [DeliveryController::class, 'update']);
        Route::delete('/{id}',         [DeliveryController::class, 'destroy']);
        Route::put('/{id}/status',     [DeliveryController::class, 'updateStatus']);
        Route::post('/{id}/complete-with-collection', [DeliveryController::class, 'completeWithCollection'])
            ->middleware('permission:create_collections');
        Route::post('/{id}/proof',     [DeliveryController::class, 'uploadProof']);
        Route::post('/{id}/tracking',  [DeliveryController::class, 'addTracking']);
        Route::get('/{id}/tracking',   [DeliveryController::class, 'tracking']);
    });

    // Collections
    Route::prefix('collections')->group(function () {
        Route::get('/daily-summary',              [CollectionController::class, 'dailySummary']);
        Route::get('/driver/{driverId}/summary',  [CollectionController::class, 'driverSummary']);
        Route::get('/',                           [CollectionController::class, 'index']);
        Route::post('/',                          [CollectionController::class, 'store'])
            ->middleware('permission:create_collections');
        Route::get('/{id}',                       [CollectionController::class, 'show']);
        Route::put('/{id}',                       [CollectionController::class, 'update'])
            ->middleware('permission:create_collections');
        Route::delete('/{id}',                    [CollectionController::class, 'destroy']);
        Route::post('/{id}/approve',              [CollectionController::class, 'approve'])
            ->middleware('permission:approve_collections');
        Route::post('/{id}/reject',               [CollectionController::class, 'reject'])
            ->middleware('permission:approve_collections');
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
        Route::get('/',                           [AttendanceController::class, 'index']);
        Route::post('/',                          [AttendanceController::class, 'store']);
        Route::get('/{id}',                       [AttendanceController::class, 'show']);
        Route::get('/{id}/penalty-details',       [AttendanceController::class, 'penaltyDetails']);
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

    // Commissions
    Route::prefix('commissions')->group(function () {
        Route::get('/monthly-summary', [CommissionController::class, 'monthlySummary']);
        Route::get('/',              [CommissionController::class, 'index']);
        Route::post('/',             [CommissionController::class, 'store']);
        Route::post('/bulk-approve', [CommissionController::class, 'bulkApprove']);
        Route::get('/{id}',          [CommissionController::class, 'show']);
        Route::put('/{id}',          [CommissionController::class, 'update']);
        Route::delete('/{id}',       [CommissionController::class, 'destroy']);
        Route::post('/{id}/approve', [CommissionController::class, 'approve']);
        Route::post('/{id}/reject',  [CommissionController::class, 'reject']);
    });

    // Car Violations
    Route::prefix('car-violations')->group(function () {
        Route::get('/employee/{empId}/summary', [CarViolationController::class, 'employeeSummary']);
        Route::get('/',        [CarViolationController::class, 'index']);
        Route::post('/',       [CarViolationController::class, 'store']);
        Route::get('/{id}',    [CarViolationController::class, 'show']);
        Route::put('/{id}',    [CarViolationController::class, 'update']);
        Route::post('/{id}/waive', [CarViolationController::class, 'waive']);
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

    // Approvals
    Route::prefix('approvals')->group(function () {
        Route::get('/pending',       [ApprovalController::class, 'pending']);
        Route::get('/history',       [ApprovalController::class, 'history']);
        Route::post('/{id}/approve', [ApprovalController::class, 'approve']);
        Route::post('/{id}/reject',  [ApprovalController::class, 'reject']);
    });

    // Reports
    Route::prefix('reports')->group(function () {
        Route::get('/employees',        [ReportController::class, 'employees']);
        Route::get('/attendance',       [ReportController::class, 'attendance']);
        Route::get('/requests',         [ReportController::class, 'requests']);
        Route::get('/collections',      [ReportController::class, 'collections']);
        Route::get('/salaries',         [ReportController::class, 'salaries']);
        Route::get('/performance',      [ReportController::class, 'performance']);
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
