<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Notifications\UserPendingApproval;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Notification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;

class AuthController extends Controller
{
    /**
     * Register — Username = phone, password >= 6.
     * บัญชีใหม่จะ active=false รออนุมัติจาก Super Admin
     */
    public function register(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name'           => ['required', 'string', 'max:150'],
            'phone'          => ['required', 'string', 'regex:/^[0-9]{9,10}$/', 'unique:users,phone'],
            'password'       => ['required', 'confirmed', Password::min(6)],
            'position_type'  => ['nullable', 'string', 'max:40'],
            'position_other' => ['nullable', 'string', 'max:100'],
            'email'          => ['nullable', 'email', 'max:255', 'unique:users,email'],
        ], [
            'phone.regex'    => 'เบอร์โทรต้องเป็นตัวเลข 9-10 หลัก',
            'phone.unique'   => 'เบอร์โทรนี้ลงทะเบียนแล้ว',
            'password.min'   => 'รหัสผ่านต้องมีอย่างน้อย 6 ตัวอักษร',
        ]);

        $user = User::create([
            'name'           => $data['name'],
            'phone'          => $data['phone'],
            'email'          => $data['email'] ?? null,
            'password'       => Hash::make($data['password']),
            'position_type'  => $data['position_type'] ?? null,
            'position_other' => $data['position_other'] ?? null,
            'active'         => false,   // รออนุมัติ
        ]);
        $user->assignRole('tracker');    // default role

        // แจ้งเตือน Super Admin ทุกคน
        $superAdmins = User::role('super_admin')->get();
        Notification::send($superAdmins, new UserPendingApproval($user));

        return response()->json([
            'message' => 'ลงทะเบียนสำเร็จ — รอ Super Admin อนุมัติบัญชีก่อนเข้าใช้งาน',
            'user'    => $user->only(['id', 'name', 'phone', 'position_type', 'active']),
        ], 201);
    }

    /**
     * Login — by phone + password (SPA session via Sanctum)
     */
    public function login(Request $request): JsonResponse
    {
        $data = $request->validate([
            'phone'    => ['required', 'string'],
            'password' => ['required', 'string'],
        ]);

        if (!Auth::attempt(['phone' => $data['phone'], 'password' => $data['password']], $request->boolean('remember'))) {
            return response()->json([
                'message' => 'เบอร์โทรหรือรหัสผ่านไม่ถูกต้อง',
            ], 422);
        }

        /** @var User $user */
        $user = Auth::user();

        if (!$user->active) {
            Auth::logout();
            return response()->json([
                'message' => 'บัญชีนี้ยังไม่ได้รับการอนุมัติ หรือถูกระงับการใช้งาน',
            ], 403);
        }

        $request->session()->regenerate();
        $user->forceFill(['last_login_at' => now()])->save();

        return response()->json([
            'user' => $this->presentUser($user),
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        return response()->json(['message' => 'ออกจากระบบสำเร็จ']);
    }

    public function me(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        return response()->json(['user' => $this->presentUser($user)]);
    }

    private function presentUser(User $user): array
    {
        $user->loadMissing('roles:id,name');
        return [
            'id'             => $user->id,
            'name'           => $user->name,
            'phone'          => $user->phone,
            'email'          => $user->email,
            'position_type'  => $user->position_type,
            'position_other' => $user->position_other,
            'active'         => (bool) $user->active,
            'last_login_at'  => $user->last_login_at,
            'roles'          => $user->roles->pluck('name')->all(),
        ];
    }
}
