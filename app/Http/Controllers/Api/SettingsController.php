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
                'overtime_enabled' => (bool) HRSetting::get(HRSetting::OVERTIME_ENABLED, true),
            ],
        ]);
    }

    public function updateAttendance(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'early_exit_deduction_enabled' => 'sometimes|required|boolean',
            'overtime_enabled' => 'sometimes|required|boolean',
        ]);

        if (array_key_exists('early_exit_deduction_enabled', $validated)) {
            HRSetting::set(
                HRSetting::EARLY_EXIT_DEDUCTION_ENABLED,
                (bool) $validated['early_exit_deduction_enabled']
            );
        }

        if (array_key_exists('overtime_enabled', $validated)) {
            HRSetting::set(HRSetting::OVERTIME_ENABLED, (bool) $validated['overtime_enabled']);
        }

        $messages = [];

        if (array_key_exists('early_exit_deduction_enabled', $validated)) {
            $messages[] = (bool) $validated['early_exit_deduction_enabled']
                ? 'تم تفعيل خصم الانصراف المبكر'
                : 'تم إيقاف خصم الانصراف المبكر للجميع';
        }

        if (array_key_exists('overtime_enabled', $validated)) {
            $messages[] = (bool) $validated['overtime_enabled']
                ? 'تم تفعيل الساعات الإضافية'
                : 'تم إيقاف الساعات الإضافية للجميع';
        }

        return response()->json([
            'success' => true,
            'message' => implode(' • ', $messages),
            'data' => [
                'early_exit_deduction_enabled' => (bool) HRSetting::get(HRSetting::EARLY_EXIT_DEDUCTION_ENABLED, true),
                'overtime_enabled' => (bool) HRSetting::get(HRSetting::OVERTIME_ENABLED, true),
            ],
        ]);
    }
}
