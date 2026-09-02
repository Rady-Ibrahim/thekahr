<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Traits\RestrictToSubordinates;
use App\Models\Attendance;
use App\Models\AttendanceLog;
use App\Models\AttendanceRequest;
use App\Models\Employee;
use App\Models\WorkLocation;
use App\Services\AttendancePenaltyService;
use App\Services\CustomAttendanceService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;

class AttendanceController
{
    use RestrictToSubordinates;
    public function __construct(
        private AttendancePenaltyService $penaltyService,
        private ?CustomAttendanceService $customService = null,
    ) {
        $this->customService ??= app(CustomAttendanceService::class);
    }

    public function index(Request $request): JsonResponse
    {
        $query = Attendance::with(['employee', 'shift', 'employee.shiftAssignments.shift'])->withCount('logs');

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

        // Custom-attendance employees: manual entry creates a completed session segment.
        if ($employee->isCustomAttendance() && !empty($validated['check_in_time'])) {
            $attendance = Attendance::firstOrCreate(
                ['employee_id' => $employee->id, 'attendance_date' => $attendanceDate],
                ['status' => 'present', 'required_hours' => $employee->requiredDailyHours()]
            );

            if ($validated['status'] === 'absent') {
                $attendance->update(['status' => 'absent']);
                $this->customService->recalculateDay($attendance->id);
                return response()->json(['success' => true, 'message' => 'تم حفظ سجل الغياب', 'data' => $attendance->fresh('logs')], 201);
            }

            AttendanceLog::create([
                'employee_id' => $employee->id,
                'attendance_id' => $attendance->id,
                'log_date' => $attendanceDate,
                'check_in_time' => $validated['check_in_time'],
                'check_out_time' => $validated['check_out_time'] ?? null,
                'source' => 'admin',
                'notes' => $validated['notes'] ?? null,
            ]);

            $attendance->update(['status' => $validated['status']]);
            $this->customService->recalculateDay($attendance->id);

            return response()->json([
                'success' => true,
                'message' => 'تم حفظ جلسة الحضور وسيتم احتساب الخصم تلقائياً في المرتب',
                'data' => $attendance->fresh(['employee', 'shift', 'logs']),
                'summary' => $this->customService->todaySummary($employee),
            ], 201);
        }

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
        } elseif ($employee->isCustomAttendance()) {
            // No shift rules apply; keep aggregates from existing sessions.
            $this->customService->recalculateDay($record->id);
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

        if ($record->status === 'absent') {
            $record->update(['deduction_amount' => 0]);
        } elseif ($record->employee?->isCustomAttendance()) {
            // Aggregates live in attendance_logs; keep the daily record in sync.
            $this->customService->recalculateDay($record->id);
        } else {
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

    /**
     * Auto-close open attendance records whose check-in is older than the configured
     * threshold (e.g. an employee forgot to check out), by setting the check-out time
     * to check-in + threshold hours. Recomputes penalties/hours afterwards.
     */
    private function autoCloseOpenShifts(int $employeeId): void
    {
        $hours   = (int) Config::get('hr.working_hours.auto_close_after_hours', 20);
        $cutoff  = now()->subHours($hours);

        $open = Attendance::where('employee_id', $employeeId)
            ->whereNotNull('check_in_time')
            ->whereNull('check_out_time')
            ->get();

        foreach ($open as $attendance) {
            $checkIn = $attendance->check_in_time instanceof Carbon
                ? $attendance->check_in_time
                : Carbon::parse($attendance->attendance_date->toDateString() . ' ' . $attendance->check_in_time);

            if ($checkIn->greaterThan($cutoff)) {
                continue;
            }

            $attendance->update(['check_out_time' => $checkIn->copy()->addHours($hours)->toTimeString()]);
            $this->penaltyService->processAttendance($attendance->fresh());
        }
    }

    public function checkIn(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'employee_id' => 'required|exists:employees,id',
            'latitude'    => 'nullable|numeric',
            'longitude'   => 'nullable|numeric',
            'photo'       => 'nullable|image|max:3072',
        ]);

        $employee = Employee::findOrFail($validated['employee_id']);

        // ── Custom flexible attendance: sequential sessions per day ──
        if ($employee->isCustomAttendance()) {
            $photo = $request->hasFile('photo') ? $request->file('photo') : null;
            $locationData = ($validated['latitude'] ?? null) !== null && ($validated['longitude'] ?? null) !== null
                ? $this->detectLocation((float) $validated['latitude'], (float) $validated['longitude'])
                : ['id' => null, 'name' => null, 'within' => false, 'distance' => null];

            $result = $this->customService->startSession(
                $employee,
                [
                    'latitude' => $validated['latitude'] ?? null,
                    'longitude' => $validated['longitude'] ?? null,
                    'photo' => $photo,
                ],
                $this->isAdminUser() ? 'admin' : 'mobile'
            );

            if (!$result['success']) {
                return response()->json(['success' => false, 'message' => $result['message']], 422);
            }

            return response()->json([
                'success'  => true,
                'message'  => $result['message'],
                'data'     => $result['session'],
                'summary'  => array_merge($this->customService->todaySummary($employee), ['location' => $locationData]),
                'location' => $locationData,
                'shift'    => null,
                'late_minutes' => 0,
                'status'   => 'present',
            ]);
        }

        // ── Standard shift-based attendance (unchanged behavior) ──
        $today  = today()->toDateString();

        // If the employee forgot to check out, auto-close any stale open shift so the
        // new check-in isn't blocked and old records don't stay open forever.
        $this->autoCloseOpenShifts($validated['employee_id']);

        $exists = Attendance::where('employee_id', $validated['employee_id'])
                            ->where('attendance_date', $today)
                            ->whereNotNull('check_in_time')
                            ->exists();

        if ($exists) {
            return response()->json(['success' => false, 'message' => 'تم تسجيل الحضور مسبقاً لهذا اليوم'], 422);
        }

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

        $employee = Employee::findOrFail($validated['employee_id']);

        // ── Custom flexible attendance: close the open session & re-aggregate ──
        if ($employee->isCustomAttendance()) {
            $openSession = $this->customService->openSession($employee);

            if (!$openSession) {
                return response()->json(['success' => false, 'message' => 'لا توجد جلسة عمل مفتوحة لتسجيل الانصراف'], 422);
            }

            $result = $this->customService->endSession($openSession, [
                'latitude' => $validated['latitude'] ?? null,
                'longitude' => $validated['longitude'] ?? null,
                'photo' => $request->hasFile('photo') ? $request->file('photo') : null,
            ]);

            if (!$result['success']) {
                return response()->json(['success' => false, 'message' => $result['message']], 422);
            }

            return response()->json([
                'success'          => true,
                'message'          => $result['message'],
                'data'             => $openSession->fresh(),
                'session_duration_minutes' => $result['session_duration_minutes'],
                'summary'          => $result['summary'],
            ]);
        }

        // ── Standard shift-based attendance ──
        // For night shifts that cross midnight, the check-out may happen on the next
        // calendar day while the attendance record was created the previous day.
        // Find the latest attendance record that is still open (checked in, not yet
        // checked out), falling back to today's record for backward compatibility.
        $today  = today()->toDateString();

        $record = Attendance::where('employee_id', $validated['employee_id'])
                            ->whereNotNull('check_in_time')
                            ->whereNull('check_out_time')
                            ->orderByDesc('attendance_date')
                            ->first();

        if (!$record) {
            $record = Attendance::where('employee_id', $validated['employee_id'])
                                ->where('attendance_date', $today)
                                ->first();
        }

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

    /**
     * Live punch summary for custom-attendance employees (current user by default).
     */
    public function customToday(Request $request): JsonResponse
    {
        $employee = $this->currentEmployee();

        if ($request->filled('employee_id')) {
            if (!$this->isAdminUser() && (int) $request->employee_id !== (int) $employee?->id) {
                return response()->json(['success' => false, 'message' => 'غير مصرح بعرض بيانات موظف آخر'], 403);
            }
            $employee = Employee::findOrFail($request->employee_id);
        }

        if (!$employee) {
            return response()->json(['success' => false, 'message' => 'لا يوجد ملف موظف مرتبط بحسابك'], 404);
        }

        if (!$employee->isCustomAttendance()) {
            return response()->json(['success' => false, 'message' => 'هذا الموظف على نظام الورديات وليس الحضور المرن'], 422);
        }

        return response()->json(['success' => true, 'data' => $this->customService->todaySummary($employee)]);
    }

    /**
     * All check-in/check-out segments of a specific day.
     */
    public function daySessions($id): JsonResponse
    {
        $attendance = Attendance::with(['logs', 'employee', 'shift'])->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => [
                'attendance' => $attendance,
                'sessions' => $attendance->logs->map(fn (AttendanceLog $log) => [
                    'id' => $log->id,
                    'check_in_time' => $log->check_in_time ? substr($log->check_in_time, 0, 5) : null,
                    'check_out_time' => $log->check_out_time ? substr($log->check_out_time, 0, 5) : null,
                    'duration_minutes' => $log->duration_minutes,
                    'is_open' => $log->isOpen(),
                    'source' => $log->source,
                    'notes' => $log->notes,
                ])->values(),
                'totals' => [
                    'total_worked_minutes' => $attendance->total_worked_minutes,
                    'total_worked_hours' => $attendance->total_worked_hours,
                    'required_hours' => $attendance->required_hours,
                    'hours_status' => $attendance->hours_status,
                    'deduction_amount' => $attendance->deduction_amount,
                ],
            ],
        ]);
    }

    public function sessionStore(Request $request, $id): JsonResponse
    {
        if (!$this->isAdminUser()) {
            return response()->json(['success' => false, 'message' => 'غير مصرح'], 403);
        }

        $attendance = Attendance::findOrFail($id);
        $validated = $this->validateSessionPayload($request);

        $duration = null;
        if (!empty($validated['check_out_time'])) {
            [$in, $out] = $this->customService->resolveSessionRange(
                $validated['check_in_time'],
                $validated['check_out_time']
            );
            $duration = max(0, (int) $in->diffInMinutes($out));
        }

        AttendanceLog::create([
            'employee_id' => $attendance->employee_id,
            'attendance_id' => $attendance->id,
            'log_date' => $attendance->attendance_date->toDateString(),
            'check_in_time' => $validated['check_in_time'],
            'check_out_time' => $validated['check_out_time'] ?? null,
            'duration_minutes' => $duration,
            'source' => 'admin',
            'notes' => $validated['notes'] ?? null,
        ]);

        if (array_key_exists('required_hours', $validated)) {
            $this->customService->applyRequiredHours(
                $attendance->id,
                $validated['required_hours'] !== null ? (float) $validated['required_hours'] : null
            );
        }

        $updated = $this->customService->recalculateDay($attendance->id);

        return response()->json([
            'success' => true,
            'message' => 'تمت إضافة الجلسة وتحديث الإجماليات',
            'data' => $updated?->load('logs'),
        ], 201);
    }

    public function sessionUpdate(Request $request, $logId): JsonResponse
    {
        if (!$this->isAdminUser()) {
            return response()->json(['success' => false, 'message' => 'غير مصرح'], 403);
        }

        $log = AttendanceLog::findOrFail($logId);
        $validated = $this->validateSessionPayload($request, false);

        $log->update(array_filter([
            'check_in_time' => $validated['check_in_time'] ?? null,
            'check_out_time' => array_key_exists('check_out_time', $validated) ? $validated['check_out_time'] : $log->check_out_time,
            'notes' => $validated['notes'] ?? $log->notes,
        ], fn ($v) => $v !== null));

        if (array_key_exists('required_hours', $validated)) {
            $this->customService->applyRequiredHours(
                $log->attendance_id,
                $validated['required_hours'] !== null ? (float) $validated['required_hours'] : null
            );
        }

        // Recompute duration when both ends exist; clear it for re-opened sessions.
        if (!$log->isOpen()) {
            $duration = max(0, (int) $log->checkInAt()->diffInMinutes($log->checkOutAt()));
            $log->update(['duration_minutes' => $duration]);
        } else {
            $log->update(['duration_minutes' => 0]);
        }

        $updated = $this->customService->recalculateDay($log->attendance_id);

        return response()->json([
            'success' => true,
            'message' => 'تم تحديث الجلسة وتحديث الإجماليات',
            'data' => $updated?->load('logs'),
        ]);
    }

    public function sessionDestroy($logId): JsonResponse
    {
        if (!$this->isAdminUser()) {
            return response()->json(['success' => false, 'message' => 'غير مصرح'], 403);
        }

        $log = AttendanceLog::findOrFail($logId);
        $attendanceId = $log->attendance_id;
        $log->delete();
        $this->customService->recalculateDay($attendanceId);

        return response()->json(['success' => true, 'message' => 'تم حذف الجلسة وتحديث الإجماليات']);
    }

    /**
     * Set the employee's daily required hours from the flexible-attendance widget.
     */
    public function customSetRequiredHours(Request $request): JsonResponse
    {
        if (!$this->isAdminUser()) {
            return response()->json(['success' => false, 'message' => 'غير مصرح'], 403);
        }

        $validated = $request->validate([
            'employee_id'          => ['required', 'integer', 'exists:employees,id'],
            'daily_required_hours' => ['required', 'numeric', 'min:0.5', 'max:24'],
        ]);

        $employee = Employee::findOrFail($validated['employee_id']);

        if (!$employee->isCustomAttendance()) {
            return response()->json(['success' => false, 'message' => 'الموظف ليس على نظام الحضور المخصص'], 422);
        }

        $result = $this->customService->setDailyRequiredHours(
            $employee,
            (float) $validated['daily_required_hours']
        );

        return response()->json([
            'success' => true,
            'message' => $result['message'],
            'data'    => ['daily_required_hours' => (float) $validated['daily_required_hours']],
        ]);
    }

    private function validateSessionPayload(Request $request, bool $requireCheckIn = true): array
    {
        $rules = [
            'check_in_time'  => [$requireCheckIn ? 'required' : 'sometimes', 'nullable', 'date_format:H:i'],
            'check_out_time' => ['nullable', 'date_format:H:i'],
            'required_hours' => ['nullable', 'numeric', 'min:0', 'max:24'],
            'notes'          => ['nullable', 'string'],
        ];

        return $request->validate($rules);
    }

    /**
     * Admin quick manual entry from the flexible-attendance widget:
     * check-in time, check-out time and the day's required hours.
     */
    public function customManualSession(Request $request): JsonResponse
    {
        if (!$this->isAdminUser()) {
            return response()->json(['success' => false, 'message' => 'غير مصرح'], 403);
        }

        $validated = $request->validate([
            'employee_id'    => ['required', 'integer', 'exists:employees,id'],
            'date'           => ['nullable', 'date', 'before_or_equal:today'],
            'check_in_time'  => ['required', 'date_format:H:i'],
            'check_out_time' => ['required', 'date_format:H:i'],
            'required_hours' => ['nullable', 'numeric', 'min:0', 'max:24'],
            'notes'          => ['nullable', 'string', 'max:500'],
        ]);

        $employee = Employee::findOrFail($validated['employee_id']);

        if (!$employee->isCustomAttendance()) {
            return response()->json(['success' => false, 'message' => 'الموظف ليس على نظام الحضور المخصص'], 422);
        }

        $result = $this->customService->manualSession($employee, $validated);

        if (!$result['success']) {
            return response()->json($result, 422);
        }

        return response()->json([
            'success' => true,
            'message' => $result['message'],
            'data'    => $result['summary'],
        ], 201);
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

    public function myDailyLog(Request $request): JsonResponse
    {
        $employee = $this->currentEmployee();

        if (!$employee) {
            return response()->json([
                'success'    => false,
                'message'    => 'لا يوجد ملف موظف مرتبط بحسابك',
            ], 404);
        }

        $month = (int) $request->get('month', now()->month);
        $year  = (int) $request->get('year', now()->year);

        $start = Carbon::createFromDate($year, $month, 1);
        $end   = $start->copy()->endOfMonth();

        $records = Attendance::with('shift')
            ->where('employee_id', $employee->id)
            ->where('attendance_date', '>=', $start->toDateString())
            ->where('attendance_date', '<=', $end->toDateString())
            ->orderBy('attendance_date')
            ->get()
            ->keyBy(fn ($r) => $r->attendance_date instanceof Carbon
                ? $r->attendance_date->toDateString()
                : $r->attendance_date
            );

        $days = [];
        for ($day = $start->copy(); $day->lte($end); $day->addDay()) {
            $key = $day->toDateString();
            $r   = $records->get($key);

            $days[] = [
                'date'              => $key,
                'day_name'          => $day->isoFormat('dddd'),
                'status'            => $r?->status ?? 'absent',
                'check_in_time'     => $r?->check_in_time,
                'check_out_time'    => $r?->check_out_time,
                'shift_name'        => $r?->shift?->name,
                'shift_start'       => $r?->shift?->start_time,
                'shift_end'         => $r?->shift?->end_time,
                'late_minutes'      => $r?->late_minutes ?? 0,
                'early_exit_minutes'=> $r?->early_exit_minutes ?? 0,
                'actual_worked_hours'=> $r?->actual_worked_hours ?? 0.0,
                'deduction_amount'  => $r?->deduction_amount ?? 0.0,
            ];
        }

        $present   = collect($days)->where('status', 'present')->count()
                   + collect($days)->where('status', 'late')->count();
        $absent    = collect($days)->where('status', 'absent')->count();
        $late      = collect($days)->where('status', 'late')->count();
        $onLeave   = collect($days)->where('status', 'on_leave')->count();
        $daysTotal = collect($days);

        $stats = [
            'month'                 => $month,
            'year'                  => $year,
            'working_days'          => $this->getWorkingDaysInMonth($month, $year),
            'present'               => $present,
            'absent'                => $absent,
            'late'                  => $late,
            'on_leave'              => $onLeave,
            'total_hours'           => round($daysTotal->sum('actual_worked_hours'), 2),
            'total_late_minutes'    => $daysTotal->sum('late_minutes'),
            'total_early_exit_minutes' => $daysTotal->sum('early_exit_minutes'),
            'total_deduction_amount'   => round($daysTotal->sum('deduction_amount'), 2),
        ];

        return response()->json([
            'success'    => true,
            'data'       => $days,
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
