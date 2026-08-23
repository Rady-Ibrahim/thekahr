<?php

namespace App\Http\Controllers\Api;

use App\Models\ChatGroup;
use App\Models\ChatGroupMember;
use App\Models\Employee;
use App\Models\EmployeeMessage;
use App\Models\GroupMessageRead;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class ChatGroupController
{
    private function currentEmployee(): Employee
    {
        $user = Auth::user();
        if (!$user) {
            abort(401, 'غير مصرح');
        }

        $employee = $user->employee
            ?? Employee::where('user_id', $user->id)
                ->orWhere('email', $user->email)
                ->first();

        if (!$employee) {
            $employee = Employee::firstOrCreate(
                ['email' => $user->email],
                [
                    'user_id'       => $user->id,
                    'name'          => $user->name,
                    'employee_code' => 'ADM-' . $user->id,
                    'phone'         => $user->phone ?? ('0000000' . $user->id),
                    'joining_date'  => now()->toDateString(),
                    'position'      => 'System Admin',
                    'department'    => 'Management',
                    'salary_type'   => 'monthly',
                    'base_salary'   => 0,
                    'status'        => 'active',
                ]
            );
        }

        return $employee;
    }

    public function index(Request $request): JsonResponse
    {
        $query = ChatGroup::withCount('members');

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('employee_id')) {
            $query->whereHas('members', fn($q) => $q->where('employee_id', $request->employee_id));
        }

        $groups = $query->with(['creator:id,name,employee_code'])
            ->orderByDesc('created_at')
            ->paginate($request->get('per_page', 15));

        return response()->json(['success' => true, 'data' => $groups]);
    }

    public function myGroups(): JsonResponse
    {
        $me = $this->currentEmployee();

        // Get all groups the employee is a member of
        $groupIds = ChatGroupMember::where('employee_id', $me->id)
            ->pluck('group_id');

        $groups = ChatGroup::whereIn('id', $groupIds)
            ->withCount(['members', 'messages'])
            ->with(['creator:id,name,employee_code'])
            ->orderByDesc('created_at')
            ->get();

        // Attach last message and unread count for each group
        $result = $groups->map(function ($group) use ($me) {
            $lastMessage = EmployeeMessage::where('group_id', $group->id)
                ->with(['sender:id,name,employee_code'])
                ->orderByDesc('created_at')
                ->first();

            $readRecord = GroupMessageRead::where('group_id', $group->id)
                ->where('employee_id', $me->id)
                ->first();

            $unreadCount = 0;
            if ($readRecord?->last_read_at) {
                $unreadCount = EmployeeMessage::where('group_id', $group->id)
                    ->where('sender_id', '!=', $me->id)
                    ->where('created_at', '>', $readRecord->last_read_at)
                    ->count();
            } else {
                $unreadCount = EmployeeMessage::where('group_id', $group->id)
                    ->where('sender_id', '!=', $me->id)
                    ->count();
            }

            return [
                'group' => $group,
                'last_message' => $lastMessage ? [
                    'id' => $lastMessage->id,
                    'message' => $lastMessage->message,
                    'sender_name' => $lastMessage->sender?->name,
                    'sent_at' => $lastMessage->created_at->toIso8601String(),
                ] : null,
                'unread_count' => $unreadCount,
                'role' => $group->members->firstWhere('employee_id', $me->id)?->role ?? 'member',
            ];
        });

        return response()->json(['success' => true, 'data' => $result->values()]);
    }

    public function store(Request $request): JsonResponse
    {
        $me = $this->currentEmployee();

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:2000',
            'avatar' => 'nullable|string|max:255',
            'member_ids' => 'required|array|min:1',
            'member_ids.*' => 'exists:employees,id',
        ]);

        return DB::transaction(function () use ($validated, $me) {
            $group = ChatGroup::create([
                'name' => $validated['name'],
                'description' => $validated['description'] ?? null,
                'avatar' => $validated['avatar'] ?? null,
                'created_by_id' => $me->id,
                'status' => 'active',
            ]);

            $memberIds = $validated['member_ids'];
            if (!in_array($me->id, $memberIds)) {
                $memberIds[] = $me->id;
            }

            $now = now();
            $pivotData = [];
            foreach ($memberIds as $empId) {
                $pivotData[$empId] = [
                    'role' => $empId === $me->id ? 'admin' : 'member',
                    'joined_at' => $now,
                ];
            }
            $group->employees()->attach($pivotData);

            return response()->json([
                'success' => true,
                'message' => 'تم إنشاء المجموعة بنجاح',
                'data' => $group->load([
                    'creator:id,name,employee_code',
                    'employees:id,name,employee_code,position',
                    'members',
                ]),
            ], 201);
        });
    }

    public function show($id): JsonResponse
    {
        $group = ChatGroup::with([
            'creator:id,name,employee_code',
            'employees:id,name,employee_code,position',
            'members',
        ])->withCount('messages')->findOrFail($id);

        return response()->json(['success' => true, 'data' => $group]);
    }

    public function update(Request $request, $id): JsonResponse
    {
        $group = ChatGroup::findOrFail($id);

        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'description' => 'nullable|string|max:2000',
            'avatar' => 'nullable|string|max:255',
            'status' => 'sometimes|in:active,archived',
            'member_ids' => 'sometimes|array',
            'member_ids.*' => 'exists:employees,id',
        ]);

        $memberIds = $validated['member_ids'] ?? null;
        unset($validated['member_ids']);

        $group->update($validated);

        if (is_array($memberIds)) {
            if (!in_array($group->created_by_id, $memberIds)) {
                $memberIds[] = $group->created_by_id;
            }

            $existingMembers = $group->employees()->pluck('employee_id')->toArray();

            $toAttach = array_diff($memberIds, $existingMembers);
            if (!empty($toAttach)) {
                $now = now();
                $pivotData = [];
                foreach ($toAttach as $empId) {
                    $pivotData[$empId] = [
                        'role' => $empId === $group->created_by_id ? 'admin' : 'member',
                        'joined_at' => $now,
                    ];
                }
                $group->employees()->attach($pivotData);
            }

            $toDetach = array_diff($existingMembers, $memberIds);
            if (!empty($toDetach)) {
                $group->employees()->detach($toDetach);
            }
        }

        return response()->json([
            'success' => true,
            'message' => 'تم تحديث المجموعة بنجاح',
            'data' => $group->fresh([
                'creator:id,name,employee_code',
                'employees:id,name,employee_code,position',
                'members',
            ]),
        ]);
    }

    public function destroy($id): JsonResponse
    {
        $group = ChatGroup::findOrFail($id);
        $group->delete();

        return response()->json([
            'success' => true,
            'message' => 'تم أرشفة المجموعة بنجاح',
        ]);
    }

    public function addMembers(Request $request, $id): JsonResponse
    {
        $group = ChatGroup::findOrFail($id);

        $validated = $request->validate([
            'employee_ids' => 'required|array|min:1',
            'employee_ids.*' => 'exists:employees,id',
        ]);

        $existing = $group->employees()->pluck('employee_id')->toArray();
        $newIds = array_diff($validated['employee_ids'], $existing);

        if (empty($newIds)) {
            return response()->json([
                'success' => false,
                'message' => 'جميع الموظفين مضافون بالفعل',
            ], 422);
        }

        $now = now();
        $pivotData = [];
        foreach ($newIds as $empId) {
            $pivotData[$empId] = [
                'role' => 'member',
                'joined_at' => $now,
            ];
        }
        $group->employees()->attach($pivotData);

        return response()->json([
            'success' => true,
            'message' => 'تم إضافة الأعضاء بنجاح',
            'data' => $group->fresh(['employees:id,name,employee_code']),
        ]);
    }

    public function removeMember($id, $employeeId): JsonResponse
    {
        $group = ChatGroup::findOrFail($id);

        $member = ChatGroupMember::where('group_id', $id)
            ->where('employee_id', $employeeId)
            ->first();

        if (!$member) {
            return response()->json([
                'success' => false,
                'message' => 'هذا الموظف ليس عضواً في المجموعة',
            ], 404);
        }

        $member->delete();
        $group->employees()->detach($employeeId);

        return response()->json([
            'success' => true,
            'message' => 'تم إزالة العضو من المجموعة بنجاح',
        ]);
    }

    public function updateMemberRole(Request $request, $id, $employeeId): JsonResponse
    {
        $group = ChatGroup::findOrFail($id);

        $validated = $request->validate([
            'role' => 'required|in:admin,member',
        ]);

        $updated = ChatGroupMember::where('group_id', $id)
            ->where('employee_id', $employeeId)
            ->update(['role' => $validated['role']]);

        if (!$updated) {
            return response()->json([
                'success' => false,
                'message' => 'هذا الموظف ليس عضواً في المجموعة',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'message' => 'تم تحديث دور العضو بنجاح',
        ]);
    }

    public function messages(Request $request, $id): JsonResponse
    {
        $group = ChatGroup::findOrFail($id);
        $me = $this->currentEmployee();

        $perPage = (int) $request->get('per_page', 30);
        $perPage = min($perPage, 100);

        $messages = EmployeeMessage::with(['sender:id,name,employee_code'])
            ->where('group_id', $id)
            ->orderByDesc('created_at')
            ->paginate($perPage);

        $formatted = $messages->getCollection()->map(function ($msg) use ($me) {
            return [
                'id' => $msg->id,
                'message' => $msg->message,
                'message_type' => $msg->message_type ?? 'text',
                'is_mine' => $msg->sender_id === $me->id,
                'sender' => $msg->sender ? [
                    'id' => $msg->sender->id,
                    'name' => $msg->sender->name,
                    'employee_code' => $msg->sender->employee_code,
                ] : null,
                'sent_at' => $msg->created_at->toIso8601String(),
            ];
        });

        return response()->json([
            'success' => true,
            'data' => [
                'group' => [
                    'id' => $group->id,
                    'name' => $group->name,
                ],
                'messages' => $formatted->values(),
                'pagination' => [
                    'current_page' => $messages->currentPage(),
                    'last_page' => $messages->lastPage(),
                    'per_page' => $messages->perPage(),
                    'total' => $messages->total(),
                    'has_more' => $messages->hasMorePages(),
                ],
            ],
        ]);
    }

    public function markRead($id): JsonResponse
    {
        $me = $this->currentEmployee();
        $group = ChatGroup::findOrFail($id);

        GroupMessageRead::updateOrCreate(
            [
                'group_id' => $id,
                'employee_id' => $me->id,
            ],
            [
                'last_read_at' => now(),
            ]
        );

        return response()->json([
            'success' => true,
            'message' => 'تم تحديد رسائل المجموعة كمقروءة',
            'data' => ['unread_messages_remaining' => 0],
        ]);
    }
}
