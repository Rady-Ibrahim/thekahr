<?php

namespace App\Http\Controllers\Api;

use App\Models\Employee;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController
{
    public function login(Request $request): JsonResponse
    {
        $request->validate([
            'email'    => 'required|email',
            'password' => 'required',
        ]);

        $user = User::where('email', $request->email)->first();

        if (! $user || ! Hash::check($request->password, $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['البريد الإلكتروني أو كلمة المرور غير صحيحة.'],
            ]);
        }

        if (! $user->is_active) {
            return response()->json(['success' => false, 'message' => 'الحساب غير نشط. تواصل مع الإدارة.'], 403);
        }

        $user->tokens()->delete();
        $token = $user->createToken('api-token')->plainTextToken;

        $employee = Employee::where('user_id', $user->id)->first();

        return response()->json([
            'success' => true,
            'message' => 'تم تسجيل الدخول بنجاح',
            'data'    => [
                'user'     => [
                    'id'       => $user->id,
                    'name'     => $user->name,
                    'email'    => $user->email,
                    'phone'    => $user->phone,
                    'roles'    => $user->roles->pluck('name'),
                ],
                'employee' => $employee,
                'token'    => $token,
            ],
        ]);
    }

    public function updateProfile(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'nullable|string|max:255',
            'phone' => 'nullable|string|max:20',
            'onesignal_player_id' => 'nullable|string|max:255',
        ]);

        $user = $request->user();

        $updateData = array_filter($validated, fn($value) => $value !== null);

        if (!empty($updateData)) {
            $user->update($updateData);
        }

        return response()->json([
            'success' => true,
            'message' => 'تم تحديث الملف الشخصي بنجاح',
            'data' => [
                'user' => $user->fresh(),
            ],
        ]);
    }

    public function storeDeviceToken(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'onesignal_player_id' => 'required|string|max:255',
            'device_type'         => 'nullable|string|in:android,ios,web',
        ]);

        $user = $request->user();
        $user->update([
            'onesignal_player_id' => $validated['onesignal_player_id'],
        ]);

        return response()->json([
            'success' => true,
            'message' => 'تم تسجيل رمز الجهاز بنجاح',
            'data'    => [
                'user_id'             => $user->id,
                'onesignal_player_id' => $user->onesignal_player_id,
            ],
        ]);
    }

    public function destroyDeviceToken(Request $request): JsonResponse
    {
        $user = $request->user();
        $user->update([
            'onesignal_player_id' => null,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'تم إلغاء تسجيل الجهاز بنجاح',
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();
        return response()->json(['success' => true, 'message' => 'تم تسجيل الخروج بنجاح']);
    }

    public function me(Request $request): JsonResponse
    {
        $user     = $request->user()->load('roles', 'permissions');
        $employee = Employee::where('user_id', $user->id)->first();

        return response()->json([
            'success' => true,
            'data'    => [
                'user'        => $user,
                'employee'    => $employee,
                'permissions' => $this->getAllPermissions($user),
                'onesignal_player_id' => $user->onesignal_player_id,
            ],
        ]);
    }

    public function changePassword(Request $request): JsonResponse
    {
        $request->validate([
            'current_password' => 'required',
            'new_password'     => 'required|min:8|confirmed',
        ]);

        if (! Hash::check($request->current_password, $request->user()->password)) {
            return response()->json(['success' => false, 'message' => 'كلمة المرور الحالية غير صحيحة'], 422);
        }

        $request->user()->update(['password' => Hash::make($request->new_password)]);

        return response()->json(['success' => true, 'message' => 'تم تغيير كلمة المرور بنجاح']);
    }

    private function getAllPermissions($user): array
    {
        $rolePermissions = [];
        foreach ($user->roles as $role) {
            foreach ($role->permissions as $permission) {
                $rolePermissions[] = $permission->name;
            }
        }
        $directPermissions = $user->permissions->pluck('name')->toArray();
        return array_unique(array_merge($rolePermissions, $directPermissions));
    }
}
