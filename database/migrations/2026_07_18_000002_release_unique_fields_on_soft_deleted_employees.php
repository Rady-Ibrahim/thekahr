<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Soft-deleted employees still occupy unique phone/email/code indexes.
        // Free those values so the same phone can be reused for a new employee.
        $deleted = DB::table('employees')->whereNotNull('deleted_at')->get();

        foreach ($deleted as $employee) {
            DB::table('employees')->where('id', $employee->id)->update([
                'phone' => mb_substr('del' . $employee->id . '_' . ($employee->phone ?? ''), 0, 255),
                'email' => null,
                'employee_code' => mb_substr('DEL' . $employee->id . '_' . ($employee->employee_code ?? ''), 0, 255),
                'national_id' => null,
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        // Irreversible data fix — original unique values are not restored.
    }
};
