<?php

namespace Tests\Feature;

use App\Models\AttendanceRequest;
use App\Models\Employee;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class AttendanceRequestDateTest extends TestCase
{
    use DatabaseTransactions;

    private static int $seq = 0;

    private function makeEmployee(): Employee
    {
        self::$seq++;

        return Employee::create([
            'employee_code' => 'T' . self::$seq . '_' . uniqid(),
            'name'          => 'Test Emp ' . self::$seq,
            'phone'         => '010' . str_pad((string) self::$seq, 8, '0', STR_PAD_LEFT) . substr(uniqid(), -4),
            'position'      => 'Test',
            'department'    => 'Test',
            'joining_date'  => now()->subMonth()->toDateString(),
            'base_salary'   => 5000,
            'status'        => 'active',
        ]);
    }

    public function test_leave_request_dates_are_serialized_without_utc_day_shift(): void
    {
        $emp = $this->makeEmployee();

        $request = AttendanceRequest::create([
            'employee_id'  => $emp->id,
            'request_type' => 'leave',
            'from_date'    => '2026-08-13',
            'to_date'      => '2026-08-13',
            'days_count'   => 1,
            'reason'       => 'test',
            'approval_status' => 'pending',
        ]);

        // Reload from DB to test the full read/serialize path
        $fresh = AttendanceRequest::findOrFail($request->id);

        $array = $fresh->toArray();

        $this->assertSame('2026-08-13', $array['from_date']);
        $this->assertSame('2026-08-13', $array['to_date']);
    }

    public function test_leave_request_dates_are_serialized_without_utc_day_shift_for_early_requests(): void
    {
        $emp = $this->makeEmployee();

        $request = AttendanceRequest::create([
            'employee_id'  => $emp->id,
            'request_type' => 'early',
            'from_date'    => '2026-08-13',
            'to_date'      => '2026-08-13',
            'days_count'   => 1,
            'reason'       => 'test',
            'approval_status' => 'pending',
        ]);

        $json = $request->toJson();

        $this->assertStringContainsString('"from_date":"2026-08-13"', $json);
        $this->assertStringContainsString('"to_date":"2026-08-13"', $json);
        $this->assertStringNotContainsString('2026-08-12', $json);
    }
}
