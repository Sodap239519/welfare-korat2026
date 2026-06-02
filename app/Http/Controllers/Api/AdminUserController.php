<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\UserScope;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;

class AdminUserController extends Controller
{
    /** GET /api/admin/users */
    public function index(Request $request): JsonResponse
    {
        $q = User::query()->with('roles:id,name');

        if ($request->filled('q')) {
            $term = trim($request->q);
            $q->where(fn ($w) => $w->where('name', 'like', "%$term%")
                                  ->orWhere('phone', 'like', "%$term%")
                                  ->orWhere('email', 'like', "%$term%"));
        }
        if ($request->filled('role')) {
            $q->whereHas('roles', fn ($r) => $r->where('name', $request->role));
        }
        if ($request->filled('status')) {
            $q->where('active', $request->status === 'active');
        }

        $perPage = max(10, min((int) $request->input('per_page', 50), 200));
        $page = $q->orderByDesc('id')->paginate($perPage)->withQueryString();

        $page->getCollection()->transform(fn ($u) => $this->present($u));
        return response()->json($page);
    }

    /** POST /api/admin/users — สร้างผู้ใช้ใหม่ */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name'             => ['required', 'string', 'max:150'],
            'phone'            => ['required', 'string', 'regex:/^[0-9]{9,10}$/', 'unique:users,phone'],
            'email'            => ['nullable', 'email', 'max:255', 'unique:users,email'],
            'position_type'    => ['nullable', 'string', 'max:40'],
            'position_other'   => ['nullable', 'string', 'max:100'],
            'password'         => ['required', Password::min(6)],
            'role'             => ['required', 'string', 'in:super_admin,admin,tracker,bank_staff'],
            'active'           => ['sometimes', 'boolean'],
            // optional scope (สำหรับ admin/tracker)
            'scope_type'       => ['nullable', 'string', 'in:amphur,tambon,village'],
            'scope_id'         => ['nullable', 'integer'],
            // bank scope (สำหรับ bank_staff — บังคับถ้า role = bank_staff)
            'bank_channel_id'  => ['required_if:role,bank_staff', 'nullable', 'integer', 'exists:channels,id'],
            'bank_sub_channel' => ['required_if:role,bank_staff', 'nullable', 'string', 'max:50'],
            // amphur scope (สำหรับ admin ระดับอำเภอ — บังคับถ้า role = admin)
            'amphur_id'        => ['required_if:role,admin', 'nullable', 'integer', 'exists:amphurs,id'],
        ], [
            'bank_channel_id.required_if'  => 'ต้องเลือกช่องทางธนาคารสำหรับเจ้าหน้าที่ธนาคาร',
            'bank_sub_channel.required_if' => 'ต้องเลือกธนาคารย่อยสำหรับเจ้าหน้าที่ธนาคาร',
            'amphur_id.required_if'        => 'ต้องเลือกอำเภอที่ Admin ดูแล',
        ]);

        $user = User::create([
            'name'             => $data['name'],
            'phone'            => $data['phone'],
            'email'            => $data['email'] ?? null,
            'position_type'    => $data['position_type'] ?? null,
            'position_other'   => $data['position_other'] ?? null,
            'password'         => Hash::make($data['password']),
            'active'           => $data['active'] ?? true,
            'bank_channel_id'  => $data['role'] === 'bank_staff' ? $data['bank_channel_id']  : null,
            'bank_sub_channel' => $data['role'] === 'bank_staff' ? strtolower($data['bank_sub_channel']) : null,
            'amphur_id'        => $data['role'] === 'admin' ? $data['amphur_id'] : null,
        ]);
        $user->assignRole($data['role']);

        if (!empty($data['scope_type']) && !empty($data['scope_id'])) {
            UserScope::create([
                'user_id'    => $user->id,
                'scope_type' => $data['scope_type'],
                'scope_id'   => (int) $data['scope_id'],
            ]);
        }

        return response()->json(['data' => $this->present($user->fresh('roles'))], 201);
    }

    /** PATCH /api/admin/users/{id} */
    public function update(Request $request, int $id): JsonResponse
    {
        $user = User::findOrFail($id);

        $data = $request->validate([
            'name'             => ['sometimes', 'string', 'max:150'],
            'phone'            => ['sometimes', 'string', 'regex:/^[0-9]{9,10}$/', 'unique:users,phone,'.$id],
            'email'            => ['sometimes', 'nullable', 'email', 'max:255', 'unique:users,email,'.$id],
            'position_type'    => ['sometimes', 'nullable', 'string', 'max:40'],
            'position_other'   => ['sometimes', 'nullable', 'string', 'max:100'],
            'active'           => ['sometimes', 'boolean'],
            'password'         => ['sometimes', 'nullable', Password::min(6)],
            'role'             => ['sometimes', 'string', 'in:super_admin,admin,tracker,bank_staff'],
            'bank_channel_id'  => ['sometimes', 'nullable', 'integer', 'exists:channels,id'],
            'bank_sub_channel' => ['sometimes', 'nullable', 'string', 'max:50'],
            'amphur_id'        => ['sometimes', 'nullable', 'integer', 'exists:amphurs,id'],
        ]);

        if (!empty($data['password'])) {
            $data['password'] = Hash::make($data['password']);
        } else {
            unset($data['password']);
        }

        $role = $data['role'] ?? null;
        unset($data['role']);

        // ถ้าเปลี่ยน role ออกจาก bank_staff → เคลียร์ bank fields
        if ($role && $role !== 'bank_staff') {
            $data['bank_channel_id'] = null;
            $data['bank_sub_channel'] = null;
        }
        // ถ้าเปลี่ยน role ออกจาก admin → เคลียร์ amphur_id
        if ($role && $role !== 'admin') {
            $data['amphur_id'] = null;
        }
        // lower-case bank_sub_channel เพื่อให้ match กับ batch.sub_channel
        if (!empty($data['bank_sub_channel'])) {
            $data['bank_sub_channel'] = strtolower($data['bank_sub_channel']);
        }

        $user->update($data);
        if ($role) $user->syncRoles([$role]);

        return response()->json(['data' => $this->present($user->fresh('roles'))]);
    }

    /** POST /api/admin/users/{id}/approve */
    public function approve(int $id): JsonResponse
    {
        $user = User::findOrFail($id);
        $user->update(['active' => true]);
        return response()->json(['data' => $this->present($user->fresh('roles'))]);
    }

    /** POST /api/admin/users/{id}/suspend */
    public function suspend(int $id): JsonResponse
    {
        $user = User::findOrFail($id);
        $user->update(['active' => false]);
        return response()->json(['data' => $this->present($user->fresh('roles'))]);
    }

    /** DELETE /api/admin/users/{id} */
    public function destroy(int $id, Request $request): JsonResponse
    {
        $user = User::findOrFail($id);
        if ($user->id === $request->user()->id) {
            return response()->json(['message' => 'ไม่สามารถลบบัญชีตัวเองได้'], 422);
        }
        $user->delete();
        return response()->json(['message' => 'ลบผู้ใช้แล้ว']);
    }

    /** GET /api/admin/users/stats */
    public function stats(): JsonResponse
    {
        return response()->json([
            'total'        => User::count(),
            'super_admin'  => User::whereHas('roles', fn ($r) => $r->where('name', 'super_admin'))->count(),
            'admin'        => User::whereHas('roles', fn ($r) => $r->where('name', 'admin'))->count(),
            'tracker'      => User::whereHas('roles', fn ($r) => $r->where('name', 'tracker'))->count(),
            'bank_staff'   => User::whereHas('roles', fn ($r) => $r->where('name', 'bank_staff'))->count(),
            'pending'      => User::where('active', false)->count(),
        ]);
    }

    private function present(User $u): array
    {
        // โหลด scopes + แปลงเป็น human-readable ("ต.X · อ.Y" หรือ "อ.X")
        $scopes = UserScope::where('user_id', $u->id)->get()->map(function ($s) {
            $label = null;
            if ($s->scope_type === 'village') {
                $v = \App\Models\Village::with('tambon.amphur')->find($s->scope_id);
                if ($v) $label = sprintf('%s ม.%s · ต.%s · อ.%s',
                    $v->name, $v->moo ?? '?', $v->tambon?->name, $v->tambon?->amphur?->name);
            } elseif ($s->scope_type === 'tambon') {
                $t = \App\Models\Tambon::with('amphur')->find($s->scope_id);
                if ($t) $label = "ต.{$t->name} · อ.{$t->amphur?->name}";
            } elseif ($s->scope_type === 'amphur') {
                $a = \App\Models\Amphur::find($s->scope_id);
                if ($a) $label = "อ.{$a->name}";
            }
            return ['type' => $s->scope_type, 'id' => $s->scope_id, 'label' => $label];
        })->filter(fn($s) => $s['label'])->values();

        return [
            'id'               => $u->id,
            'name'             => $u->name,
            'phone'            => $u->phone,
            'email'            => $u->email,
            'position_type'    => $u->position_type,
            'position_other'   => $u->position_other,
            'bank_channel_id'  => $u->bank_channel_id,
            'bank_sub_channel' => $u->bank_sub_channel,
            'amphur_id'        => $u->amphur_id,
            'amphur_name'      => $u->amphur?->name,
            'active'           => (bool) $u->active,
            'last_login_at'    => $u->last_login_at?->toIso8601String(),
            'created_at'       => $u->created_at?->toIso8601String(),
            'roles'            => $u->roles->pluck('name')->all(),
            'scopes'           => $scopes,
        ];
    }
}
