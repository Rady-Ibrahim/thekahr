<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\AttendanceLog;
use App\Models\Employee;
use App\Models\Role;
use App\Models\User;
use App\Services\CustomAttendanceService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * End-to-end validation of the attendance flow AFTER the hours/overtime rework.
 *
 * Reproduces the exact defect report for
 * "مصطفى السيد عبدالحليم فكري شادي" (one 4h session / a record with no times /
 * four sessions in a single day that previously rendered 20.00 and 104.73
 * hours) and drives the REAL HTTP endpoints to inspect the actual JSON.
 *
 * It also asserts the three flow cases and that the legacy front-end/mobile
 * endpoints kept their original key structure.
 */
class AttendanceE2EValidationTest extends TestCase
{
    use DatabaseTransactions;

    private const TARGET_NAME = 'مصطفى السيد عبدالحليم فكري شادي';

    private const NOW = '2026-09-10 12:00:00';

    /** @var array<int,string> */
    private static array $report = [];

    private static function line(string $s = ''): void
    {
        self::$report[] = $s;
        fwrite(STDERR, $s . PHP_EOL);
    }

    // ─── Scenario fixtures ────────────────────────────────────────────────

    private function makeTarget(): Employee
    {
        return Employee::create([
            'employee_code'        => 'E2E-' . uniqid(),
            'name'                 => self::TARGET_NAME,
            'email'                => 'mustafa_' . uniqid() . '@example.com',
            'phone'                => '010' . substr(uniqid(), -8),
            'position'             => 'Sales',
            'department'           => 'Sales',
            'joining_date'         => '2025-01-01',
            'base_salary'          => 8000,
            'status'               => 'active',
            'is_custom_attendance' => true,
            'daily_required_hours' => 8.0,
            'overtime_enabled'     => true,
        ]);
    }

    private function admin(): User
    {
        Role::firstOrCreate(['name' => 'super_admin'], ['description' => 'System admin']);

        $admin = User::create([
            'name'     => 'E2E Admin ' . uniqid(),
            'email'    => 'e2e_admin_' . uniqid() . '@example.com',
            'password' => 'password',
        ]);
        $admin->giveRole('super_admin');

        return $admin;
    }

    private function record(Employee $e, string $date, string $status = 'present', ?float $req = 8.0): Attendance
    {
        return Attendance::create([
            'employee_id'     => $e->id,
            'attendance_date' => $date,
            'status'          => $status,
            'required_hours'  => $req,
        ]);
    }

    private function addSession(Attendance $a, ?string $in, ?string $out, ?string $logDate = null): AttendanceLog
    {
        return AttendanceLog::create([
            'employee_id'      => $a->employee_id,
            'attendance_id'    => $a->id,
            'log_date'         => $logDate ?: $a->attendance_date->toDateString(),
            'check_in_time'    => $in,
            'check_out_time'   => $out,
            'duration_minutes' => 0,
            'source'           => 'mobile',
        ]);
    }

    /**
     * Recursively collect every numeric leaf as "path => value" so we can scan
     * the whole payload for phantom hours (20.00 / 104.73 / >24h).
     *
     * @return array<string,float>
     */
    private function numericLeaves(mixed $node, string $path = '$'): array
    {
        if (is_array($node) || is_object($node)) {
            $out = [];
            foreach ((array) $node as $k => $v) {
                $out += $this->numericLeaves($v, $path . '.' . $k);
            }
            return $out;
        }

        if (is_numeric($node)) {
            return [$path => (float) $node];
        }

        return [];
    }

    /**
     * Assert the payload contains no phantom/auto-close hours anywhere.
     *
     * Units are read from the key name so that a legitimate 600 MINUTES is not
     * mistaken for 600 hours.
     */
    private function assertNoPhantomHours(array $payload, string $label): void
    {
        $leaves = $this->numericLeaves($payload);

        $this->assertNotEmpty($leaves, $label . ': payload should contain numbers');

        $raw = (string) json_encode($payload, JSON_UNESCAPED_UNICODE);

        // The reported bug values must not exist anywhere in the response.
        $this->assertStringNotContainsString('104.73', $raw, $label . ': 104.73 leaked into the response');
        $this->assertStringNotContainsString('104,73', $raw, $label . ': 104.73 leaked into the response');

        foreach ($leaves as $path => $value) {
            $isMinutes = (bool) preg_match('/minute/i', $path);
            $isHours   = ! $isMinutes && (bool) preg_match('/hour|worked|duration|overtime/i', $path);

            if ($isMinutes && $value > 1440.0) {
                $this->fail($label . ": impossible minutes value {$value} at {$path} (max 1440)");
            }

            if ($isHours) {
                if ($value > 24.0) {
                    $this->fail($label . ": impossible hours value {$value} at {$path} (max 24)");
                }

                // 20.00 is the auto-close window and must never be a worked value.
                if (abs($value - 20.0) < 0.0001) {
                    $this->fail($label . ": auto-close fallback 20.00 found at {$path}");
                }
            }
        }
    }

    // ══════════════════════════════════════════════════════════════════════
    // 1) The reported employee: 4h session / empty record / 4 sessions a day
    // ══════════════════════════════════════════════════════════════════════

    public function test_reported_employee_scenarios_render_real_hours(): void
    {
        Carbon::setTestNow(Carbon::parse(self::NOW));

        $employee = $this->makeTarget();

        // ── Day 1: exactly one 4-hour session (the "4 hours" case) ────────
        $day1 = $this->record($employee, '2026-09-06');
        $this->addSession($day1, '10:00:00', '14:00:00');

        // ── Day 2: record with NO times and NO sessions at all ───────────
        $day2 = $this->record($employee, '2026-09-07');

        // ── Day 3: FOUR sessions in one day ───────────────────────────────
        $day3 = $this->record($employee, '2026-09-08');
        $this->addSession($day3, '08:00:00', '12:00:00'); // 240
        $this->addSession($day3, '12:00:00', '16:00:00'); // 240
        $this->addSession($day3, '16:00:00', '20:00:00'); // 240
        $this->addSession($day3, '20:00:00', '23:30:00'); // 210

        // ── Day 4: absent, but with a leftover session (must stay 0) ─────
        $day4 = $this->record($employee, '2026-09-05', 'absent');
        $this->addSession($day4, '08:00:00', '20:00:00');

        // A poison row living on ANOTHER day but attached to the day-3 record.
        $this->addSession($day3, '06:00:00', '22:00:00', '2026-09-09'); // 960 min, must be ignored

        $service = app(CustomAttendanceService::class);
        foreach ([$day1, $day2, $day3, $day4] as $rec) {
            $service->recalculateDay($rec->id);
        }

        Sanctum::actingAs($this->admin());

        self::line('=' . str_repeat('=', 78));
        self::line('1) ' . self::TARGET_NAME . '  (custom/flexible, required 8h)');
        self::line('=' . str_repeat('=', 78));

        // ---- GET /api/attendance/{id}/sessions (per-day session view) ----
        $expected = [
            $day1->id => ['sessions' => 1, 'minutes' => 240, 'hours' => 4.0,  'overtime' => 0],
            $day2->id => ['sessions' => 0, 'minutes' => 0,   'hours' => 0.0,  'overtime' => 0],
            $day3->id => ['sessions' => 4, 'minutes' => 930, 'hours' => 15.5, 'overtime' => 450],
            $day4->id => ['sessions' => 1, 'minutes' => 0,   'hours' => 0.0,  'overtime' => 0],
        ];

        foreach ($expected as $id => $exp) {
            $res  = $this->getJson("/api/attendance/{$id}/sessions");
            $res->assertOk();
            $json = $res->json();
            $tot  = $json['data']['totals'];

            $this->assertNoPhantomHours($json, "daySessions#{$id}");

            self::line(sprintf(
                '  %s  sessions=%d  total=%d min (%.2f h)  required=%.2f h  status=%-9s  OT=%d min (%.2f h)',
                $json['data']['date'],
                $tot['sessions_count'],
                $tot['total_worked_minutes'],
                $tot['total_worked_hours'],
                $tot['required_hours'],
                (string) $tot['hours_status'],
                $tot['overtime_minutes'],
                $tot['overtime_hours'],
            ));

            $this->assertSame($exp['sessions'], $tot['sessions_count']);
            $this->assertSame($exp['minutes'], $tot['total_worked_minutes']);
            $this->assertEqualsWithDelta($exp['hours'], $tot['total_worked_hours'], 0.001);
            $this->assertSame($exp['overtime'], $tot['overtime_minutes']);

            // Per-session durations must equal the real pair diff.
            $sum = 0;
            foreach ($json['data']['sessions'] as $s) {
                $sum += $s['duration_minutes'];
                self::line(sprintf(
                    '        session #%d  %s -> %s  = %d min  (open=%s)',
                    $s['id'],
                    (string) $s['check_in_time'],
                    $s['check_out_time'] ?? '—',
                    $s['duration_minutes'],
                    $s['is_open'] ? 'yes' : 'no',
                ));
            }

            // On a counted day the total is exactly the sum of the session
            // durations. On absent / on_leave / excused days the total is
            // deliberately forced to 0 so stale rows cannot manufacture hours.
            $notCounted = in_array($attendance_status = Attendance::find($id)?->status, ['absent', 'on_leave', 'excused'], true);

            if ($notCounted) {
                $this->assertSame(0, $tot['total_worked_minutes'], 'Non-counted day must report 0 minutes');
                self::line(sprintf('        (absent/leave day: %d session minutes ignored -> total forced to 0)', $sum));
            } else {
                $this->assertSame($tot['total_worked_minutes'], $sum, 'Total must equal the sum of session durations');
            }
        }

        // ---- GET /api/attendance (admin list) ---------------------------
        $list = $this->getJson('/api/attendance?employee_id=' . $employee->id . '&month=9&year=2026&per_page=50');
        $list->assertOk();
        $this->assertNoPhantomHours($list->json(), 'GET /api/attendance');

        self::line();
        self::line('  GET /api/attendance (list) rows:');
        foreach ($list->json('data.data') as $row) {
            self::line(sprintf(
                '    %s  status=%-8s in=%-8s out=%-8s worked=%5.2f h  OT=%.2f h  logs_count=%d',
                $row['attendance_date'],
                $row['status'],
                $row['check_in_time'] ?? '—',
                $row['check_out_time'] ?? '—',
                $row['worked_hours_display'],
                $row['overtime_hours_display'],
                $row['logs_count'],
            ));
        }

        // ---- GET /api/employees/{id}/attendance (mobile per-employee) ----
        $empRes = $this->getJson("/api/employees/{$employee->id}/attendance?month=9&year=2026");
        $empRes->assertOk();
        $this->assertNoPhantomHours($empRes->json(), 'GET /api/employees/{id}/attendance');

        self::line();
        self::line('  GET /api/employees/{id}/attendance statistics: '
            . json_encode($empRes->json('statistics'), JSON_UNESCAPED_UNICODE));

        // ---- GET /api/reports/attendance (monthly report) ---------------
        $rep = $this->getJson('/api/reports/attendance?month=9&year=2026');
        $rep->assertOk();
        $this->assertNoPhantomHours($rep->json(), 'GET /api/reports/attendance');

        $row = collect($rep->json('data'))->firstWhere('name', self::TARGET_NAME);
        self::line('  GET /api/reports/attendance row: ' . json_encode($row, JSON_UNESCAPED_UNICODE));
        $this->assertNotNull($row, 'Target employee must appear in the attendance report');
        $this->assertEqualsWithDelta(19.5, $row['working_hours'], 0.01, '4.0 + 0.0 + 15.5 = 19.5 h');
        $this->assertEqualsWithDelta(7.5, $row['overtime_hours'], 0.01);

        // ---- GET /api/attendance/{id}/penalty-details --------------------
        $pen = $this->getJson("/api/attendance/{$day3->id}/penalty-details");
        $pen->assertOk();
        $this->assertNoPhantomHours($pen->json(), 'penalty-details');
        self::line('  penalty-details (4-session day): '
            . json_encode([
                'total_worked_hours' => $pen->json('data.total_worked_hours'),
                'hours_status'       => $pen->json('data.hours_status'),
                'overtime'           => $pen->json('data.overtime'),
            ], JSON_UNESCAPED_UNICODE));

        Carbon::setTestNow(null);
    }

    // ══════════════════════════════════════════════════════════════════════
    // 2) Flow cases
    // ══════════════════════════════════════════════════════════════════════

    public function test_flow_a_open_session_without_checkout_is_zero(): void
    {
        Carbon::setTestNow(Carbon::parse(self::NOW));

        $employee = $this->makeTarget();
        $day = $this->record($employee, '2026-09-10');

        // Check-in only, 3 hours before "now" so the 20h auto-close cannot fire.
        $log = $this->addSession($day, '09:00:00', null);

        $updated = app(CustomAttendanceService::class)->recalculateDay($day->id);

        $this->assertSame(0, (int) $updated->total_worked_minutes);
        $this->assertEqualsWithDelta(0.0, (float) $updated->total_worked_hours, 0.001);
        $this->assertSame(0, (int) $updated->overtime_minutes);
        $this->assertEqualsWithDelta(0.0, (float) $updated->overtime_hours, 0.001);
        $this->assertNotNull($log->fresh()->check_out_time === null ? 'open' : 'closed');

        Sanctum::actingAs($this->admin());

        $res = $this->getJson("/api/attendance/{$day->id}/sessions");
        $res->assertOk();
        $json = $res->json();
        $this->assertNoPhantomHours($json, 'open-session');

        $tot = $json['data']['totals'];
        self::line();
        self::line('=' . str_repeat('=', 78));
        self::line('2A) Open session (check-in only, no check-out)');
        self::line('=' . str_repeat('=', 78));
        self::line(sprintf(
            '  sessions=%d (open=%d)  total=%d min (%.2f h)  status=%-9s  OT=%.2f h',
            $tot['sessions_count'],
            $tot['open_sessions_count'],
            $tot['total_worked_minutes'],
            $tot['total_worked_hours'],
            (string) $tot['hours_status'],
            $tot['overtime_hours'],
        ));

        $this->assertSame(0, $tot['total_worked_minutes']);
        $this->assertEqualsWithDelta(0.0, $tot['total_worked_hours'], 0.001);
        $this->assertSame(0, $tot['overtime_minutes']);
        $this->assertEqualsWithDelta(0.0, $tot['overtime_hours'], 0.001);
        $this->assertSame(1, $tot['open_sessions_count']);

        // The mobile summary must agree.
        $mobile = $this->mobileUserFor($employee);
        Sanctum::actingAs($mobile);
        $sum = $this->getJson('/api/attendance/custom/today');
        $sum->assertOk();
        $this->assertNoPhantomHours($sum->json(), 'custom/today');
        self::line('  custom/today: ' . json_encode([
            'total_worked_minutes' => $sum->json('data.total_worked_minutes'),
            'total_worked_hours'   => $sum->json('data.total_worked_hours'),
            'overtime_minutes'     => $sum->json('data.overtime_minutes'),
        ], JSON_UNESCAPED_UNICODE));
        $this->assertSame(0, $sum->json('data.total_worked_minutes'));
        $this->assertSame(0, $sum->json('data.overtime_minutes'));

        Carbon::setTestNow(null);
    }

    public function test_flow_b_multi_sessions_are_scoped_to_their_own_day(): void
    {
        Carbon::setTestNow(Carbon::parse(self::NOW));

        $employee = $this->makeTarget();
        $day = $this->record($employee, '2026-09-08');

        // 3 real sessions on the target day.
        $this->addSession($day, '08:00:00', '10:30:00'); // 150
        $this->addSession($day, '12:00:00', '13:00:00'); //  60
        $this->addSession($day, '15:00:00', '19:45:00'); // 285

        // Rows on other days attached to the SAME attendance record.
        $this->addSession($day, '00:00:00', '23:00:00', '2026-09-07'); // 1380
        $this->addSession($day, '00:00:00', '23:00:00', '2026-09-09'); // 1380

        app(CustomAttendanceService::class)->recalculateDay($day->id);

        $fresh = $day->fresh();
        $this->assertSame(495, (int) $fresh->total_worked_minutes, '150+60+285 only');
        $this->assertEqualsWithDelta(8.25, (float) $fresh->total_worked_hours, 0.001);
        $this->assertSame(15, (int) $fresh->overtime_minutes, '495 - 480 = 15');

        Sanctum::actingAs($this->admin());
        $res = $this->getJson("/api/attendance/{$day->id}/sessions");
        $res->assertOk();
        $json = $res->json();
        $this->assertNoPhantomHours($json, 'multi-session');

        self::line();
        self::line('=' . str_repeat('=', 78));
        self::line('2B) Multi-session day (whereDate scoping)');
        self::line('=' . str_repeat('=', 78));
        self::line('  returned sessions:');
        foreach ($json['data']['sessions'] as $s) {
            self::line(sprintf(
                '    log_date=%s  %s -> %s = %d min',
                $s['log_date'],
                (string) $s['check_in_time'],
                (string) $s['check_out_time'],
                $s['duration_minutes'],
            ));
        }
        self::line(sprintf(
            '  total = %d min (%.2f h)  sessions=%d  OT=%d min',
            $json['data']['totals']['total_worked_minutes'],
            $json['data']['totals']['total_worked_hours'],
            $json['data']['totals']['sessions_count'],
            $json['data']['totals']['overtime_minutes'],
        ));

        // Only the target day may be returned, despite 5 rows existing.
        $this->assertCount(3, $json['data']['sessions'], 'Only same-day sessions');
        $this->assertSame(5, $day->logs()->count(), 'But 5 rows really exist in the table');
        foreach ($json['data']['sessions'] as $s) {
            $this->assertSame('2026-09-08', $s['log_date']);
        }
        $this->assertSame(495, $json['data']['totals']['total_worked_minutes']);
        $this->assertEqualsWithDelta(8.25, $json['data']['totals']['total_worked_hours'], 0.001);
        $this->assertSame(15, $json['data']['totals']['overtime_minutes']);

        Carbon::setTestNow(null);
    }

    public function test_flow_c_no_punches_and_absent_are_zero_without_fallback(): void
    {
        Carbon::setTestNow(Carbon::parse(self::NOW));

        $employee = $this->makeTarget();

        // Present, no times, no sessions.
        $present = $this->record($employee, '2026-09-09', 'present');
        // Absent, no times, no sessions.
        $absent = $this->record($employee, '2026-09-08', 'absent');

        $service = app(CustomAttendanceService::class);
        $p = $service->recalculateDay($present->id);
        $a = $service->recalculateDay($absent->id);

        self::line();
        self::line('=' . str_repeat('=', 78));
        self::line('2C) Present-without-punches / Absent (no fallback)');
        self::line('=' . str_repeat('=', 78));
        self::line(sprintf(
            '  present  %s: %d min (%.2f h)  working_hours=%d  status=%s  OT=%d min',
            $present->attendance_date->toDateString(),
            (int) $p->total_worked_minutes,
            (float) $p->total_worked_hours,
            (int) $p->working_hours,
            (string) $p->hours_status,
            (int) $p->overtime_minutes,
        ));
        self::line(sprintf(
            '  absent   %s: %d min (%.2f h)  working_hours=%d  status=%s  OT=%d min',
            $absent->attendance_date->toDateString(),
            (int) $a->total_worked_minutes,
            (float) $a->total_worked_hours,
            (int) $a->working_hours,
            (string) $a->hours_status,
            (int) $a->overtime_minutes,
        ));

        foreach ([$p, $a] as $rec) {
            $this->assertSame(0, (int) $rec->total_worked_minutes);
            $this->assertEqualsWithDelta(0.0, (float) $rec->total_worked_hours, 0.001);
            $this->assertEqualsWithDelta(0.0, (float) $rec->actual_worked_hours, 0.001);
            $this->assertSame(0, (int) $rec->working_hours);
            $this->assertSame(0, (int) $rec->overtime_minutes);
            $this->assertNotEquals(20.0, (float) $rec->total_worked_hours);
            $this->assertNotEquals(20, (int) $rec->working_hours);
        }
        $this->assertNull($a->hours_status, 'Absent day must not be classified as shortfall/overtime');

        Carbon::setTestNow(null);
    }

    // ══════════════════════════════════════════════════════════════════════
    // 3) Backward compatibility of the legacy endpoints
    // ══════════════════════════════════════════════════════════════════════

    public function test_legacy_endpoints_keep_their_key_structure(): void
    {
        Carbon::setTestNow(Carbon::parse(self::NOW));

        $employee = $this->makeTarget();
        $day = $this->record($employee, '2026-09-08');
        $this->addSession($day, '08:00:00', '12:00:00');
        $this->addSession($day, '13:00:00', '19:00:00');
        app(CustomAttendanceService::class)->recalculateDay($day->id);

        Sanctum::actingAs($this->admin());

        // --- GET /api/attendance : paginator envelope + attendance columns --
        $list = $this->getJson('/api/attendance?employee_id=' . $employee->id);
        $list->assertOk()->assertJsonStructure([
            'success',
            'data' => ['current_page', 'data', 'first_page_url', 'from', 'per_page', 'to', 'total'],
        ]);
        // Keys that existed BEFORE the rework must still be present.
        $list->assertJsonStructure([
            'data' => ['data' => [['id', 'employee_id', 'attendance_date', 'check_in_time', 'check_out_time',
                'status', 'late_minutes', 'working_hours', 'total_worked_minutes', 'total_worked_hours',
                'required_hours', 'hours_status', 'overtime_minutes', 'overtime_hours',
                'early_exit_minutes', 'actual_worked_hours', 'deduction_amount',
                'salary_deduction_amount', 'salary_deduction_label', 'overtime_enabled',
                'worked_hours_display', 'overtime_hours_display', 'logs_count']]],
        ]);
        self::line();
        self::line('=' . str_repeat('=', 78));
        self::line('3) Backward compatibility of legacy endpoints');
        self::line('=' . str_repeat('=', 78));
        self::line('  GET /api/attendance            keys OK (paginator + all legacy columns)');

        // --- GET /api/employees/{id}/attendance ----------------------------
        $emp = $this->getJson("/api/employees/{$employee->id}/attendance?month=9&year=2026");
        $emp->assertOk()->assertJsonStructure([
            'success', 'data' => [['id', 'attendance_date', 'check_in_time', 'check_out_time', 'status',
                'total_worked_minutes', 'total_worked_hours', 'overtime_minutes', 'overtime_hours']],
            'statistics' => ['present', 'absent', 'late', 'on_leave'],
        ]);
        self::line('  GET /api/employees/{id}/attendance  keys OK (data[] + statistics)');

        // --- GET /api/reports/attendance ----------------------------------
        $rep = $this->getJson('/api/reports/attendance?month=9&year=2026');
        $rep->assertOk()->assertJsonStructure([
            'success',
            'data' => [['employee_code', 'name', 'department', 'present', 'absent', 'late', 'on_leave',
                'late_minutes', 'working_hours']],
            'month', 'year',
        ]);
        self::line('  GET /api/reports/attendance  keys OK (9 legacy keys preserved)');

        // --- GET /api/dashboard/attendance-chart --------------------------
        $chart = $this->getJson('/api/dashboard/attendance-chart');
        $chart->assertOk()->assertJsonStructure(['data' => ['present', 'absent', 'late', 'on_leave']]);
        self::line('  GET /api/dashboard/attendance-chart  keys OK (untouched)');

        // --- GET /api/attendance/{id}/sessions ----------------------------
        $sess = $this->getJson("/api/attendance/{$day->id}/sessions");
        $sess->assertOk()->assertJsonStructure([
            'success',
            'data' => [
                'attendance', 'date', 'sessions' => [['id', 'log_date', 'check_in_time', 'check_out_time',
                    'duration_minutes', 'is_open', 'source', 'notes']],
                'totals' => ['total_worked_minutes', 'total_worked_hours', 'required_hours',
                    'sessions_count', 'hours_status', 'deduction_amount'],
            ],
        ]);
        self::line('  GET /api/attendance/{id}/sessions  keys OK (sessions[] + totals)');

        // --- Mobile endpoints (user linked to the employee) ---------------
        Sanctum::actingAs($this->mobileUserFor($employee));

        $mine = $this->getJson('/api/attendance/my-records?month=9&year=2026');
        $mine->assertOk();
        $this->assertNoPhantomHours($mine->json(), 'my-records');
        self::line('  GET /api/attendance/my-records  keys OK, no phantom hours');

        $daily = $this->getJson('/api/attendance/my-daily-log?month=9&year=2026');
        $daily->assertOk()->assertJsonStructure([
            'success',
            'data' => [['date', 'day_name', 'status', 'check_in_time', 'check_out_time',
                'late_minutes', 'total_worked_minutes', 'actual_worked_hours', 'overtime_minutes',
                'overtime_hours', 'sessions_count']],
            'statistics' => ['total_hours', 'total_overtime_hours', 'total_late_minutes'],
        ]);
        $this->assertNoPhantomHours($daily->json(), 'my-daily-log');
        $row = collect($daily->json('data'))->firstWhere('date', '2026-09-08');
        self::line(sprintf(
            '  GET /api/attendance/my-daily-log  2026-09-08: %d min (%.2f h), OT %d min',
            $row['total_worked_minutes'], $row['actual_worked_hours'], $row['overtime_minutes'],
        ));
        $this->assertSame(600, $row['total_worked_minutes']);
        $this->assertEqualsWithDelta(10.0, $row['actual_worked_hours'], 0.001);
        $this->assertSame(120, $row['overtime_minutes']);
        $this->assertEqualsWithDelta(10.0, $daily->json('statistics.total_hours'), 0.001);

        Carbon::setTestNow(null);
    }

    // ══════════════════════════════════════════════════════════════════════
    // 4) Mixed mode: punch pair + attendance logs through dayTotals()
    // ══════════════════════════════════════════════════════════════════════

    public function test_mixed_punch_pair_and_log_modes(): void
    {
        Carbon::setTestNow(Carbon::parse(self::NOW));

        $employee = $this->makeTarget();
        $employee->update(['is_custom_attendance' => false, 'daily_required_hours' => null]);

        // (a) Pure punch-pair record (no logs at all).
        $pair = $this->record($employee, '2026-09-08', 'present', null);
        $pair->update(['check_in_time' => '08:00:00', 'check_out_time' => '18:00:00']);
        app(\App\Services\AttendancePenaltyService::class)->processAttendance($pair);

        // (b) Log-driven record on the same employee.
        $logs = $this->record($employee, '2026-09-07', 'present', 8.0);
        $this->addSession($logs, '09:00:00', '18:00:00');
        app(CustomAttendanceService::class)->recalculateDay($logs->id);

        Sanctum::actingAs($this->admin());

        self::line();
        self::line('=' . str_repeat('=', 78));
        self::line('4) Mixed mode through dayTotals()');
        self::line('=' . str_repeat('=', 78));

        $pairRes = $this->getJson("/api/attendance/{$pair->id}/sessions");
        $pairRes->assertOk();
        $this->assertNoPhantomHours($pairRes->json(), 'punch-pair');
        $pt = $pairRes->json('data.totals');
        self::line(sprintf(
            '  punch-pair day %s (0 log rows): %d min (%.2f h)  status=%-9s  OT=%d min (%.2f h)',
            $pairRes->json('data.date'),
            $pt['total_worked_minutes'], $pt['total_worked_hours'],
            (string) $pt['hours_status'], $pt['overtime_minutes'], $pt['overtime_hours'],
        ));
        $this->assertSame(0, $pair->logs()->count());
        $this->assertSame(600, $pt['total_worked_minutes'], 'A real punch pair must not collapse to 0');
        $this->assertEqualsWithDelta(10.0, $pt['total_worked_hours'], 0.001);
        $this->assertSame(120, $pt['overtime_minutes']);

        $logRes = $this->getJson("/api/attendance/{$logs->id}/sessions");
        $logRes->assertOk();
        $this->assertNoPhantomHours($logRes->json(), 'log-driven');
        $lt = $logRes->json('data.totals');
        self::line(sprintf(
            '  log-driven day %s (1 log row): %d min (%.2f h)  status=%-9s  OT=%d min (%.2f h)',
            $logRes->json('data.date'),
            $lt['total_worked_minutes'], $lt['total_worked_hours'],
            (string) $lt['hours_status'], $lt['overtime_minutes'], $lt['overtime_hours'],
        ));
        $this->assertSame(540, $lt['total_worked_minutes']);
        $this->assertSame(60, $lt['overtime_minutes']);

        Carbon::setTestNow(null);
    }

    private function mobileUserFor(Employee $employee): User
    {
        $user = User::create([
            'name'     => 'Mobile ' . uniqid(),
            'email'    => $employee->email,
            'password' => 'password',
        ]);

        $employee->update(['user_id' => $user->id]);

        return $user;
    }
}
