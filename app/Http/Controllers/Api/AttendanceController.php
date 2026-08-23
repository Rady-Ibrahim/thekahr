<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Traits\RestrictToSubordinates;
use App\Models\Attendance;
use App\Models\AttendanceRequest;
use App\Models\Employee;
use App\Models\WorkLocation;
use App\Services\AttendancePenaltyService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;

class AttendanceController
{
    use RestrictToSubordinates;
    public function __construct(private AttendancePenaltyService $penaltyService) {}

    public function index(Request $request): JsonResponse
    {
        $query = Attendance::with(['employee', 'shift', 'employee.shiftAssignments.shift']);

        if ($request->filled('employee_id')) $query->where('employee_id', $request->employee_id);
        if ($request->filled('status'))      $query->where('status', $request->status);
        if ($request->filled('date'))        $query->where('attendance_date', $request->date);
        if ($request->filled('date_from'))   $query->whereDate('attendance_date', '>=', $request->date_from);
        if ($request->filled('date_to'))     $query->whereDate('attendance_date', '<=', $request->date_to);
        if ($request->filled('search')) {
            $search = $request->search;
            $query->whereHas('employee', function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('employee_code', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%");
            });
        }
        if ($request->filled('month') && $request->filled('year')) {
            $query->whereMonth('attendance_date', $request->month)
                  ->whereYear('attendance_date', $request->year);
        }
        if ($request->filled('shift_id')) {
            $shiftId = (int) $request->shift_id;
            $query->where(function ($q) use ($shiftId) {
                $q->where('shift_id', $shiftId)
                    ->orWhereHas('employee.shiftAssignments', function ($q) use ($shiftId) {
                        $q->where('shift_id', $shiftId)
                            ->whereColumn('employee_shift.effective_from', '<=', 'attendances.attendance_date')
                            ->where(function ($q) {
                                $q->whereNull('employee_shift.effective_to')
                                    ->orWhereColumn('employee_shift.effective_to', '>=', 'attendances.attendance_date');
                            });
                    });
            });
        }

        $records = $query->orderByDesc('attendance_date')->paginate($request->get('per_page', 15));

        $records->getCollection()->each(function (Attendance $attendance) {
            if (!$attendance->shift) {
                $date = Carbon::parse($attendance->attendance_date)->toDateString();
                $assignment = $attendance->employee?->shiftAssignments
                    ->filter(function ($assignment) use ($date) {
                        $from = Carbon::parse($assignment->effective_from)->toDateString();
                        $to = $assignment->effective_to
                            ? Carbon::parse($assignment->effective_to)->toDateString()
                            : null;
                        return $from <= $date && ($to === null || $to >= $date);
                    })
                    ->sortByDesc(fn ($a) => Carbon::parse($a->effective_from)->toDateString())
                    ->first();
                $attendance->setRelation('shift', $assignment?->shift);
            }

            $deduction = $this->penaltyService->calculateRecordDeduction($attendance);
            $attendance->setAttribute('salary_deduction_amount', $deduction['amount']);
            $attendance->setAttribute('salary_deduction_label', $deduction['label']);
        });

        return response()->json(['success' => true, 'data' => $records]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'employee_id'     => 'required|exists:employees,id',
            'date'            => 'nullable|date|before_or_equal:today',
            'attendance_date' => 'nullable|date|before_or_equal:today',
            'status'          => 'required|in:present,absent,late,early_leave,on_leave,excused',
            'check_in_time'   => 'nullable|date_format:H:i',
            'check_out_time'  => 'nullable|date_format:H:i',
            'late_minutes'    => 'nullable|integer|min:0',
            'shift_id'        => 'nullable|exists:shifts,id',
            'notes'           => 'nullable|string',
        ], [
            'date.before_or_equal'            => 'لا يمكن تسجيل حضور في تاريخ مستقبلي',
            'attendance_date.before_or_equal' => 'لا يمكن تسجيل حضور في تاريخ مستقبلي',
        ]);

        $attendanceDate = $validated['attendance_date'] ?? $validated['date'] ?? today()->toDateString();
        $employee = Employee::findOrFail($validated['employee_id']);
        $date = Carbon::parse($attendanceDate);

        $record = Attendance::updateOrCreate(
            ['employee_id' => $validated['employee_id'], 'attendance_date' => $attendanceDate],
            [
                'status' => $validated['status'],
                'check_in_time' => $validated['check_in_time'] ?? null,
                'check_out_time' => $validated['check_out_time'] ?? null,
                'shift_id' => $validated['shift_id'] ?? null,
                'notes' => $validated['notes'] ?? null,
            ]
        );

        if ($validated['status'] === 'absent') {
            $record->update([
                'late_minutes' => 0,
                'working_hours' => 0,
                'early_exit_minutes' => 0,
                'actual_worked_hours' => 0,
                'applied_late_deduction_type' => 'full_day',
                'deduction_amount' => 0,
            ]);
        } else {
            $record = $this->penaltyService->processAttendance($record);

            if ($record->check_in_time && $record->check_out_time) {
                $checkIn = Carbon::parse($attendanceDate . ' ' . $record->check_in_time);
                $checkOut = Carbon::parse($attendanceDate . ' ' . $record->check_out_time);
                $record->working_hours = max(0, (int) $checkIn->diffInHours($checkOut));
                $record->save();
            }

            // Auto-set status based on late minutes
            $lateThreshold = (int) Config::get('hr.working_hours.late_threshold_minutes', 15);
            if ($record->status === 'present' && $record->late_minutes > $lateThreshold) {
                $record->update(['status' => 'late']);
            }
        }

        return response()->json([
            'success' => true,
            'message' => 'تم حفظ سجل الحضور وسيتم احتساب الخصم تلقائياً في المرتب',
            'data' => $record->load(['employee', 'shift']),
            'penalty' => [
                'late_minutes' => $record->late_minutes,
                'early_exit_minutes' => $record->early_exit_minutes,
                'actual_worked_hours' => $record->actual_worked_hours,
                'applied_late_deduction_type' => $record->applied_late_deduction_type,
                'applied_early_deduction_type' => $record->applied_early_deduction_type,
                'deduction_amount' => $record->deduction_amount,
            ],
        ], 201);
    }

    public function show($id): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => Attendance::with(['employee', 'shift'])->findOrFail($id),
        ]);
    }

    public function update(Request $request, $id): JsonResponse
    {
        $record = Attendance::findOrFail($id);
        $validated = $request->validate([
            'employee_id'     => 'sometimes|exists:employees,id',
            'date'            => 'nullable|date|before_or_equal:today',
            'attendance_date' => 'nullable|date|before_or_equal:today',
            'status'          => 'sometimes|in:present,absent,late,early_leave,on_leave,excused',
            'check_in_time'   => 'nullable|date_format:H:i',
            'check_out_time'  => 'nullable|date_format:H:i',
            'late_minutes'    => 'nullable|integer|min:0',
            'shift_id'        => 'nullable|exists:shifts,id',
            'notes'           => 'nullable|string',
        ], [
            'date.before_or_equal'            => 'لا يمكن تسجيل حضور في تاريخ مستقبلي',
            'attendance_date.before_or_equal' => 'لا يمكن تسجيل حضور في تاريخ مستقبلي',
        ]);

        $attendanceDate = $validated['attendance_date'] ?? $validated['date'] ?? $record->attendance_date->toDateString();
        $checkIn = $validated['check_in_time'] ?? ($record->check_in_time ? Carbon::parse($record->check_in_time)->format('H:i') : null);
        $checkOut = $validated['check_out_time'] ?? ($record->check_out_time ? Carbon::parse($record->check_out_time)->format('H:i') : null);

        $record->update([
            'employee_id' => $validated['employee_id'] ?? $record->employee_id,
            'attendance_date' => $attendanceDate,
            'status' => $validated['status'] ?? $record->status,
            'check_in_time' => $checkIn,
            'check_out_time' => $checkOut,
            'shift_id' => $validated['shift_id'] ?? $record->shift_id,
            'notes' => $validated['notes'] ?? $record->notes,
        ]);

        if ($record->status !== 'absent') {
            $record = $this->penaltyService->processAttendance($record);

            if ($record->check_in_time && $record->check_out_time) {
                $date = Carbon::parse($attendanceDate);
                $ci = Carbon::parse($date->toDateString() . ' ' . $record->check_in_time);
                $co = Carbon::parse($date->toDateString() . ' ' . $record->check_out_time);
                $record->working_hours = max(0, (int) $ci->diffInHours($co));
                $record->save();
            }

            $lateThreshold = (int) Config::get('hr.working_hours.late_threshold_minutes', 15);
            if ($record->status === 'present' && $record->late_minutes > $lateThreshold) {
                $record->update(['status' => 'late']);
            }
        }

        return response()->json([
            'success' => true,
            'message' => 'تم تحديث سجل الحضور',
            'data' => $record->load(['employee', 'shift']),
            'penalty' => [
                'late_minutes' => $record->late_minutes,
                'early_exit_minutes' => $record->early_exit_minutes,
                'actual_worked_hours' => $record->actual_worked_hours,
                'applied_late_deduction_type' => $record->applied_late_deduction_type,
                'applied_early_deduction_type' => $record->applied_early_deduction_type,
                'deduction_amount' => $record->deduction_amount,
            ],
        ]);
    }

    public function destroy($id): JsonResponse
    {
        Attendance::findOrFail($id)->delete();

        return response()->json(['success' => true, 'message' => 'تم حذف سجل الحضور']);
    }

    public function checkIn(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'employee_id' => 'required|exists:employees,id',
            'latitude'    => 'nullable|numeric',
            'longitude'   => 'nullable|numeric',
            'photo'       => 'nullable|image|max:3072',
        ]);

        $today  = today()->toDateString();
        $exists = Attendance::where('employee_id', $validated['employee_id'])
                            ->where('attendance_date', $today)
                            ->whereNotNull('check_in_time')
                            ->exists();

        if ($exists) {
            return response()->json(['success' => false, 'message' => 'تم تسجيل الحضور مسبقاً لهذا اليوم'], 422);
        }

        $employee = Employee::findOrFail($validated['employee_id']);
        $now = now();
        $date = Carbon::parse($today);

        $shift = $this->penaltyService->resolveShift($employee, $date);
        $lateResult = ['late_minutes' => 0, 'deduction_type' => null, 'deduction_amount' => 0.0];

        if ($shift) {
            $lateResult = $this->penaltyService->calculateLatePenalty($shift, $now, $date, $employee);
        } else {
            $lateResult = $this->penaltyService->calculateLateFromConfig($now, $date);
        }

        $lateThreshold = (int) Config::get('hr.working_hours.late_threshold_minutes', 15);
        $status = $lateResult['late_minutes'] > $lateThreshold ? 'late' : 'present';

        $photoPath = null;
        if ($request->hasFile('photo')) {
            $photoPath = $request->file('photo')->store('attendance/checkin', 'public');
        }

        $locationData = ($validated['latitude'] ?? null) !== null && ($validated['longitude'] ?? null) !== null
            ? $this->detectLocation((float) $validated['latitude'], (float) $validated['longitude'])
            : ['id' => null, 'name' => null, 'within' => false, 'distance' => null];

        $record = Attendance::updateOrCreate(
            ['employee_id' => $validated['employee_id'], 'attendance_date' => $today],
            [
                'check_in_time'              => $now->toTimeString(),
                'check_in_latitude'          => $validated['latitude'] ?? null,
                'check_in_longitude'         => $validated['longitude'] ?? null,
                'check_in_photo'             => $photoPath,
                'status'                     => $status,
                'late_minutes'               => $lateResult['late_minutes'],
                'shift_id'                   => $shift?->id,
                'applied_late_deduction_type' => $lateResult['deduction_type'],
                'check_in_location_id'       => $locationData['id'],
                'check_in_location_name'     => $locationData['name'],
                'is_within_location'         => $locationData['within'],
            ]
        );

        return response()->json([
            'success'           => true,
            'message'           => 'تم تسجيل الحضور بنجاح',
            'data'              => $record,
            'late_minutes'      => $lateResult['late_minutes'],
            'status'            => $status,
            'location'          => $locationData,
            'shift'             => $shift ? ['id' => $shift->id, 'name' => $shift->name, 'grace_period_minutes' => $shift->grace_period_minutes] : null,
            'applied_deduction_type' => $lateResult['deduction_type'],
        ]);
    }

    public function checkOut(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'employee_id' => 'required|exists:employees,id',
            'latitude'    => 'nullable|numeric',
            'longitude'   => 'nullable|numeric',
            'photo'       => 'nullable|image|max:3072',
        ]);

        $today  = today()->toDateString();
        $record = Attendance::where('employee_id', $validated['employee_id'])
                            ->where('attendance_date', $today)
                            ->first();

        if (!$record || !$record->check_in_time) {
            return response()->json(['success' => false, 'message' => 'لم يتم تسجيل الحضور بعد'], 422);
        }

        if ($record->check_out_time) {
            return response()->json(['success' => false, 'message' => 'تم تسجيل الانصراف مسبقاً'], 422);
        }

        $checkIn = Carbon::parse($today . ' ' . $record->check_in_time);
        $checkOut = now();
        $date = Carbon::parse($today);

        $photoPath = null;
        if ($request->hasFile('photo')) {
            $photoPath = $request->file('photo')->store('attendance/checkout', 'public');
        }

        $record->update([
            'check_out_time'      => $checkOut->toTimeString(),
            'check_out_latitude'  => $validated['latitude'] ?? null,
            'check_out_longitude' => $validated['longitude'] ?? null,
            'check_out_photo'     => $photoPath,
        ]);

        $record = $this->penaltyService->processAttendance($record);

        $workingHours = $record->actual_worked_hours ?? 0;

        return response()->json([
            'success'       => true,
            'message'       => 'تم تسجيل الانصراف بنجاح',
            'data'          => $record,
            'working_hours' => $workingHours,
            'penalty'       => [
                'late_minutes' => $record->late_minutes,
                'early_exit_minutes' => $record->early_exit_minutes,
                'actual_worked_hours' => $record->actual_worked_hours,
                'applied_late_deduction_type' => $record->applied_late_deduction_type,
                'applied_early_deduction_type' => $record->applied_early_deduction_type,
                'deduction_amount' => $record->deduction_amount,
            ],
        ]);
    }

    public function penaltyDetails($id): JsonResponse
    {
        $record = Attendance::with(['employee', 'shift.lateRules', 'shift.earlyExitRules'])->findOrFail($id);

        $details = [
            'shift_name' => $record->shift?->name,
            'shift_start' => $record->shift?->start_time,
            'shift_end' => $record->shift?->end_time,
            'grace_period_minutes' => $record->shift?->grace_period_minutes,
            'check_in_time' => $record->check_in_time,
            'check_out_time' => $record->check_out_time,
            'late' => [
                'minutes' => $record->late_minutes,
                'effective_delay' => null,
                'deduction_type' => $record->applied_late_deduction_type,
            ],
            'early_exit' => [
                'minutes' => $record->early_exit_minutes,
                'deduction_type' => $record->applied_early_deduction_type,
            ],
            'actual_worked_hours' => $record->actual_worked_hours,
            'total_deduction_amount' => $record->deduction_amount,
            'payroll_pushed' => $record->payroll_pushed,
        ];

        if ($record->shift && $record->check_in_time) {
            $date = $record->attendance_date instanceof Carbon
                ? $record->attendance_date
                : Carbon::parse($record->attendance_date);
            $checkIn = Carbon::parse($date->toDateString() . ' ' . $record->check_in_time);
            $scheduledStart = Carbon::parse($date->toDateString() . ' ' . $record->shift->start_time);
            $actualDelay = max(0, (int) $scheduledStart->diffInMinutes($checkIn, false));
            $effectiveDelay = max(0, $actualDelay - $record->shift->grace_period_minutes);

            $details['late']['effective_delay'] = $effectiveDelay;
            $details['late']['actual_delay'] = $actualDelay;
        }

        return response()->json(['success' => true, 'data' => $details]);
    }

    public function myRecords(Request $request): JsonResponse
    {
        $employee = $this->currentEmployee();
        if (!$employee) {
            return response()->json([
                'success'    => true,
                'data'       => [],
                'statistics' => [
                    'present'            => 0,
                    'absent'             => 0,
                    'late'               => 0,
                    'on_leave'           => 0,
                    'total_hours'        => 0,
                    'total_late_minutes' => 0,
                    'total_early_exit_minutes' => 0,
                    'total_deduction_amount' => 0,
                ],
            ]);
        }

        $month = $request->get('month', now()->month);
        $year  = $request->get('year', now()->year);

        $records = Attendance::with('shift')
            ->where('employee_id', $employee->id)
            ->whereMonth('attendance_date', $month)
            ->whereYear('attendance_date', $year)
            ->orderBy('attendance_date')
            ->get();

        $stats = [
            'present'            => $records->where('status', 'present')->count(),
            'absent'             => $records->where('status', 'absent')->count(),
            'late'               => $records->where('status', 'late')->count(),
            'on_leave'           => $records->where('status', 'on_leave')->count(),
            'total_hours'        => $records->sum('actual_worked_hours'),
            'total_late_minutes' => $records->sum('late_minutes'),
            'total_early_exit_minutes' => $records->sum('early_exit_minutes'),
            'total_deduction_amount'   => $records->sum('deduction_amount'),
        ];

        return response()->json([
            'success'    => true,
            'data'       => $records,
            'statistics' => $stats,
        ]);
    }

    public function todaySummary(): JsonResponse
    {
        $today = today()->toDateString();
        $total = Employee::where('status', 'active')->count();

        $todayRecords = Attendance::with('employee')
            ->where('attendance_date', $today)
            ->get();

        $present = $todayRecords->where('status', 'present')->values();
        $late    = $todayRecords->where('status', 'late')->values();
        $onLeave = $todayRecords->where('status', 'on_leave')->values();

        $absentEmployees = Employee::where('status', 'active')
            ->whereDoesntHave('attendances', function ($q) use ($today) {
                $q->where('attendance_date', $today);
            })
            ->get();

        $list = function ($records) {
            return $records->map(fn ($a) => [
                'id' => $a->id,
                'name' => $a->employee?->name,
                'employee_code' => $a->employee?->employee_code,
                'check_in_time' => $a->check_in_time,
                'check_out_time' => $a->check_out_time,
                'late_minutes' => $a->late_minutes,
            ])->values();
        };

        $summary = [
            'total_employees' => $total,
            'present'         => $present->count(),
            'late'            => $late->count(),
            'absent'          => $total - $todayRecords->count(),
            'no_checkout'     => $todayRecords->whereNotNull('check_in_time')->whereNull('check_out_time')->count(),
            'lists' => [
                'present'  => $list($present),
                'late'     => $list($late),
                'on_leave' => $list($onLeave),
                'absent'   => $absentEmployees->map(fn ($e) => [
                    'id' => $e->id,
                    'name' => $e->name,
                    'employee_code' => $e->employee_code,
                    'check_in_time' => null,
                    'check_out_time' => null,
                    'late_minutes' => null,
                ])->values(),
            ],
        ];

        return response()->json(['success' => true, 'data' => $summary]);
    }

    public function requestLeave(Request $request): JsonResponse
    {
        $currentEmployee = $this->getCurrentEmployee();
        if (!$currentEmployee) {
            return response()->json(['success' => false, 'message' => 'غير مصرح - لا يوجد ملف موظف مرتبط'], 401);
        }

        $validated = $request->validate([
            'employee_id'  => 'nullable|exists:employees,id',
            'request_type' => 'required|in:sick,leave,late,early,excuse',
            'from_date'    => 'required|date',
            'to_date'      => 'required|date|after_or_equal:from_date',
            'reason'       => 'required|string',
        ]);

        $employeeId = $validated['employee_id'] ?? $currentEmployee->id;

        if ((int) $employeeId !== (int) $currentEmployee->id && !$this->isAdminUser()) {
            return response()->json(['success' => false, 'message' => 'غير مصرح بإنشاء طلب إجازة بالنيابة عن موظف آخر'], 403);
        }

        $from = Carbon::parse($validated['from_date']);
        $to   = Carbon::parse($validated['to_date']);
        $validated['days_count'] = $from->diffInDays($to) + 1;
        $validated['employee_id'] = (int) $employeeId;

        $leaveRequest = AttendanceRequest::create($validated);

        return response()->json([
            'success' => true,
            'message' => 'تم إرسال طلب الإجازة بنجاح وهو في انتظار الموافقة',
            'data'    => $leaveRequest,
        ], 201);
    }

    public function approveLeave(Request $request, $id): JsonResponse
    {
        if (!$this->isAdminUser()) {
            return response()->json(['success' => false, 'message' => 'غير مصرح بالموافقة على طلبات الإجازات'], 403);
        }

        $leaveRequest = AttendanceRequest::findOrFail($id);
        $validated    = $request->validate([
            'status' => 'required|in:approved,rejected',
            'notes'  => 'nullable|string',
        ]);

        $leaveRequest->update([
            'approval_status' => $validated['status'],
            'approved_by_id'  => $this->getCurrentEmployee()?->id ?? 1,
            'approval_notes'  => $validated['notes'] ?? null,
        ]);

        if ($validated['status'] === 'approved') {
            $from = Carbon::parse($leaveRequest->from_date);
            $to   = Carbon::parse($leaveRequest->to_date);

            for ($date = $from; $date->lte($to); $date->addDay()) {
                Attendance::updateOrCreate(
                    ['employee_id' => $leaveRequest->employee_id, 'attendance_date' => $date->toDateString()],
                    ['status' => 'on_leave']
                );
            }
        }

        return response()->json([
            'success' => true,
            'message' => $validated['status'] === 'approved' ? 'تمت الموافقة على الإجازة' : 'تم رفض الإجازة',
            'data'    => $leaveRequest,
        ]);
    }

    public function leaveRequests(Request $request): JsonResponse
    {
        $query = AttendanceRequest::with('employee');

        if ($request->filled('request_type')) $query->where('request_type', $request->request_type);
        else $query->where('request_type', '!=', 'early');
        if ($request->filled('status'))    $query->where('approval_status', $request->status);
        if ($request->filled('date_from')) $query->whereDate('from_date', '>=', $request->date_from);
        if ($request->filled('date_to'))   $query->whereDate('to_date', '<=', $request->date_to);

        if ($request->filled('search')) {
            $search = $request->search;
            $query->whereHas('employee', function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('employee_code', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%");
            });
        }

        $this->scopeSubordinates($query);

        $requests = $query->orderByDesc('created_at')->paginate($request->get('per_page', 15));

        return response()->json(['success' => true, 'data' => $requests]);
    }

    public function monthlyReport(Request $request, $employeeId): JsonResponse
    {
        $month = $request->get('month', now()->month);
        $year  = $request->get('year', now()->year);

        $records = Attendance::with('shift')
            ->where('employee_id', $employeeId)
            ->whereMonth('attendance_date', $month)
            ->whereYear('attendance_date', $year)
            ->orderBy('attendance_date')
            ->get();

        $employee     = Employee::findOrFail($employeeId);
        $workingDays  = $this->getWorkingDaysInMonth($month, $year);

        $stats = [
            'employee'          => $employee->only(['name', 'employee_code', 'position', 'department']),
            'month'             => $month,
            'year'              => $year,
            'working_days'      => $workingDays,
            'present'           => $records->where('status', 'present')->count(),
            'absent'            => $records->where('status', 'absent')->count(),
            'late'              => $records->where('status', 'late')->count(),
            'on_leave'          => $records->where('status', 'on_leave')->count(),
            'total_hours'       => $records->sum('actual_worked_hours'),
            'total_late_minutes'=> $records->sum('late_minutes'),
            'total_early_exit_minutes' => $records->sum('early_exit_minutes'),
            'total_deduction_amount'   => $records->sum('deduction_amount'),
            'attendance_rate'   => $workingDays > 0 ? round(($records->where('status', 'present')->count() / $workingDays) * 100, 1) : 0,
        ];

        return response()->json(['success' => true, 'data' => $records, 'statistics' => $stats]);
    }

    private function detectLocation(float $lat, float $lng): array
    {
        $locations = WorkLocation::where('is_active', true)->get();

        foreach ($locations as $location) {
            $distance = $this->haversineDistance($lat, $lng, $location->latitude, $location->longitude);
            if ($distance <= $location->radius_meters) {
                return [
                    'id'       => $location->id,
                    'name'     => $location->name,
                    'within'   => true,
                    'distance' => round($distance),
                ];
            }
        }

        return ['id' => null, 'name' => null, 'within' => false, 'distance' => null];
    }

    private function haversineDistance(float $lat1, float $lon1, float $lat2, float $lon2): float
    {
        $earthRadius = 6371000;
        $dLat = deg2rad($lat2 - $lat1);
        $dLon = deg2rad($lon2 - $lon1);
        $a = sin($dLat / 2) ** 2 + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLon / 2) ** 2;
        return $earthRadius * 2 * atan2(sqrt($a), sqrt(1 - $a));
    }

    private function getWorkingDaysInMonth(int $month, int $year): int
    {
        $start = Carbon::createFromDate($year, $month, 1);
        $end   = $start->copy()->endOfMonth();
        $count = 0;
        for ($day = $start; $day->lte($end); $day->addDay()) {
            if (!$day->isWeekend()) $count++;
        }
        return $count;
    }

    private function currentEmployee(): ?Employee
    {
        if (!auth()->id()) {
            return null;
        }

        return Employee::where('user_id', auth()->id())->first();
    }
}
