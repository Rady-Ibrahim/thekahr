<?php

namespace App\Http\Controllers\Api;

use App\Models\HRSetting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SettingsController
{
    public function attendance(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => [
                'early_exit_deduction_enabled' => (bool) HRSetting::get(
                    HRSetting::EARLY_EXIT_DEDUCTION_ENABLED,
                    true
                ),
            ],
        ]);
    }

    public function updateAttendance(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'early_exit_deduction_enabled' => 'required|boolean',
        ]);

        HRSetting::set(
            HRSetting::EARLY_EXIT_DEDUCTION_ENABLED,
            (bool) $validated['early_exit_deduction_enabled']
        );

        return response()->json([
            'success' => true,
            'message' => (bool) $validated['early_exit_deduction_enabled']
                ? 'تم تفعيل خصم الانصراف المبكر'
                : 'تم إيقاف خصم الانصراف المبكر للجميع',
            'data' => [
                'early_exit_deduction_enabled' => (bool) $validated['early_exit_deduction_enabled'],
            ],
        ]);
    }
}