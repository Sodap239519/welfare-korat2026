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
            // ขอบเขตที่รับผิดชอบ — ส่ง tambon_id + village_name (ครอบทุก ม. ของหมู่บ้านนั้น)
            'tambon_id'      => ['required', 'integer', 'exists:tambons,id'],
            'village_name'   => ['required', 'string', 'max:150'],
        ], [
            'phone.regex'           => 'เบอร์โทรต้องเป็นตัวเลข 9-10 หลัก',
            'phone.unique'          => 'เบอร์โทรนี้ลงทะเบียนแล้ว',
            'password.min'          => 'รหัสผ่านต้องมีอย่างน้อย 6 ตัวอักษร',
            'tambon_id.required'    => 'กรุณาเลือกตำบลที่รับผิดชอบ',
            'village_name.required' => 'กรุณาเลือกหมู่บ้านที่รับผิดชอบ',
        ]);

        // ค้นหา villages ทั้งหมดที่มีชื่อเดียวกันใน tambon นี้ (ทุก ม.)
        $villages = \App\Models\Village::where('tambon_id', (int) $data['tambon_id'])
            ->where('name', $data['village_name'])
            ->get();
        if ($villages->isEmpty()) {
            return response()->json(['errors' => ['village_name' => ['ไม่พบหมู่บ้านนี้ในตำบลที่เลือก']]], 422);
        }

        $user = \DB::transaction(function () use ($data, $villages) {
            $user = User::create([
                'name'           => $data['name'],
                'phone'          => $data['phone'],
                'email'          => $data['email'] ?? null,
                'password'       => Hash::make($data['password']),
                'position_type'  => $data['position_type'] ?? null,
                'position_other' => $data['position_other'] ?? null,
                'active'         => false,
            ]);
            $user->assignRole('tracker');

            // 1) สร้าง UserScope ทุก village_id ที่อยู่ในชื่อหมู่บ้านเดียวกัน
            foreach ($villages as $v) {
                \App\Models\UserScope::create([
                    'user_id'    => $user->id,
                    'scope_type' => 'village',
                    'scope_id'   => $v->id,
                ]);
            }

            // 2) สร้าง Tracker record ต่อ moo (ผูกกลับมาที่ user เดียวกัน)
            foreach ($villages as $v) {
                \App\Models\Tracker::create([
                    'user_id'        => $user->id,
                    'village_id'     => $v->id,
                    'full_name'      => $data['name'],
                    'position'       => $data['position_type'] ?? 'อื่นๆ',
                    'position_other' => $data['position_other'] ?? null,
                    'phone'          => $data['phone'],
                    'active'         => true,
                ]);
            }

            return $user;
        });

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

    /** PATCH /api/auth/profile — แก้ไขข้อมูลส่วนตัวของผู้ใช้เอง */
    public function updateProfile(Request $request): JsonResponse
    {
        $user = $request->user();

        $data = $request->validate([
            'name'           => ['sometimes', 'string', 'max:150'],
            'phone'          => ['sometimes', 'string', 'regex:/^[0-9]{9,10}$/', 'unique:users,phone,'.$user->id],
            'email'          => ['sometimes', 'nullable', 'email', 'max:255', 'unique:users,email,'.$user->id],
            'position_type'  => ['sometimes', 'nullable', 'string', 'max:40'],
            'position_other' => ['sometimes', 'nullable', 'string', 'max:100'],
            'current_password' => ['required_with:password', 'nullable', 'string'],
            'password'       => ['sometimes', 'nullable', \Illuminate\Validation\Rules\Password::min(6)],
        ], [
            'phone.regex'    => 'เบอร์โทรต้องเป็นตัวเลข 9-10 หลัก',
            'phone.unique'   => 'เบอร์โทรนี้มีคนใช้แล้ว',
            'password.min'   => 'รหัสผ่านต้องมีอย่างน้อย 6 ตัวอักษร',
            'current_password.required_with' => 'กรุณาระบุรหัสผ่านปัจจุบันก่อนเปลี่ยน',
        ]);

        // ถ้าเปลี่ยน password — ตรวจสอบ current_password ก่อน
        if (!empty($data['password'])) {
            if (!\Illuminate\Support\Facades\Hash::check($data['current_password'], $user->password)) {
                return response()->json([
                    'message' => 'รหัสผ่านปัจจุบันไม่ถูกต้อง',
                    'errors'  => ['current_password' => ['รหัสผ่านปัจจุบันไม่ถูกต้อง']],
                ], 422);
            }
            $data['password'] = \Illuminate\Support\Facades\Hash::make($data['password']);
        } else {
            unset($data['password']);
        }
        unset($data['current_password']);

        $user->update($data);

        return response()->json([
            'message' => 'อัปเดตข้อมูลส่วนตัวเรียบร้อย — ข้อมูลเก่าในประวัติยังคงแสดงด้วยชื่อเดิม',
            'user'    => $this->presentUser($user->fresh('roles')),
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
            'position_type'   => $user->position_type,
            'position_other'  => $user->position_other,
            'bank_channel_id' => $user->bank_channel_id,
            'bank_sub_channel'=> $user->bank_sub_channel,
            'active'          => (bool) $user->active,
            'last_login_at'   => $user->last_login_at,
            'roles'           => $user->roles->pluck('name')->all(),
        ];
    }
}
