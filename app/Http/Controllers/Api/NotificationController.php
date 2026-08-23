<?php

namespace App\Http\Controllers\Api;

use App\Models\Employee;
use App\Models\Notification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationController
{
    public function index(Request $request): JsonResponse
    {
        $tab = $request->get('tab', 'inbox');
        $userId = auth()->id();

        if ($tab === 'sent') {
            $query = Notification::with('recipientEmployee:id,name,employee_code')
                ->where('sender_id', $userId);

            if ($request->filled('is_read')) {
                $query->where('is_read', filter_var($request->is_read, FILTER_VALIDATE_BOOLEAN));
            }

            $notifications = $query->orderByDesc('created_at')->paginate($request->get('per_page', 20));

            return response()->json([
                'success' => true,
                'data' => $notifications,
            ]);
        }

        $query = Notification::with('sender:id,name')
            ->where('user_id', $userId);

        if ($request->filled('is_read')) {
            $query->where('is_read', filter_var($request->is_read, FILTER_VALIDATE_BOOLEAN));
        }

        $notifications = $query->orderByDesc('created_at')->paginate($request->get('per_page', 20));
        $unreadCount = Notification::where('user_id', $userId)->where('is_read', false)->count();

        return response()->json([
            'success' => true,
            'data' => $notifications,
            'unread_count' => $unreadCount,
        ]);
    }

    public function send(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'employee_ids' => 'required|array|min:1',
            'employee_ids.*' => 'exists:employees,id',
            'title' => 'required|string|max:255',
            'message' => 'required|string',
            'notification_type' => 'nullable|string|max:50',
        ]);

        $employees = Employee::whereIn('id', $validated['employee_ids'])
            ->whereNotNull('user_id')
            ->get(['id', 'user_id', 'name']);

        if ($employees->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' => 'لا يوجد موظفون مرتبطون بحساب مستخدم لإرسال الإشعار',
            ], 422);
        }

        $created = [];
        foreach ($employees as $employee) {
            $created[] = Notification::create([
                'sender_id' => auth()->id(),
                'user_id' => $employee->user_id,
                'title' => $validated['title'],
                'message' => $validated['message'],
                'notification_type' => $validated['notification_type'] ?? 'hr_direct',
                'related_model' => Employee::class,
                'related_id' => $employee->id,
                'is_read' => false,
            ]);
        }

        $skipped = count($validated['employee_ids']) - $employees->count();

        return response()->json([
            'success' => true,
            'message' => 'تم إرسال الإشعار إلى ' . count($created) . ' موظف'
                . ($skipped > 0 ? " (تم تخطي {$skipped} بدون حساب مستخدم)" : ''),
            'data' => [
                'sent_count' => count($created),
                'skipped_count' => $skipped,
                'recipients' => $employees->pluck('name'),
            ],
        ], 201);
    }

    public function markRead($id): JsonResponse
    {
        $notification = Notification::where('user_id', auth()->id())->findOrFail($id);
        $notification->update(['is_read' => true, 'read_at' => now()]);

        return response()->json(['success' => true, 'message' => 'تم تحديد الإشعار كمقروء']);
    }

    public function markAllRead(): JsonResponse
    {
        Notification::where('user_id', auth()->id())
            ->where('is_read', false)
            ->update(['is_read' => true, 'read_at' => now()]);

        return response()->json(['success' => true, 'message' => 'تم تحديد جميع الإشعارات كمقروءة']);
    }

    public function destroy($id): JsonResponse
    {
        Notification::where(function ($q) {
            $q->where('user_id', auth()->id())->orWhere('sender_id', auth()->id());
        })->findOrFail($id)->delete();

        return response()->json(['success' => true, 'message' => 'تم حذف الإشعار']);
    }

    public function unreadCount(): JsonResponse
    {
        $count = Notification::where('user_id', auth()->id())->where('is_read', false)->count();

        return response()->json(['success' => true, 'count' => $count]);
    }
}
