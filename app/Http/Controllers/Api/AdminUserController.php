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
            'name'           => ['required', 'string', 'max:150'],
            'phone'          => ['required', 'string', 'regex:/^[0-9]{9,10}$/', 'unique:users,phone'],
            'email'          => ['nullable', 'email', 'max:255', 'unique:users,email'],
            'position_type'  => ['nullable', 'string', 'max:40'],
            'position_other' => ['nullable', 'string', 'max:100'],
            'password'       => ['required', Password::min(6)],
            'role'           => ['required', 'string', 'in:super_admin,admin,tracker'],
            'active'         => ['sometimes', 'boolean'],
            // optional scope (สำหรับ admin/tracker — Super Admin ไม่ต้องผูก scope)
            'scope_type'     => ['nullable', 'string', 'in:amphur,tambon,village'],
            'scope_id'       => ['nullable', 'integer'],
        ]);

        $user = User::create([
            'name'           => $data['name'],
            'phone'          => $data['phone'],
            'email'          => $data['email'] ?? null,
            'position_type'  => $data['position_type'] ?? null,
            'position_other' => $data['position_other'] ?? null,
            'password'       => Hash::make($data['password']),
            'active'         => $data['active'] ?? true,
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
            'name'           => ['sometimes', 'string', 'max:150'],
            'phone'          => ['sometimes', 'string', 'regex:/^[0-9]{9,10}$/', 'unique:users,phone,'.$id],
            'email'          => ['sometimes', 'nullable', 'email', 'max:255', 'unique:users,email,'.$id],
            'position_type'  => ['sometimes', 'nullable', 'string', 'max:40'],
            'position_other' => ['sometimes', 'nullable', 'string', 'max:100'],
            'active'         => ['sometimes', 'boolean'],
            'password'       => ['sometimes', 'nullable', Password::min(6)],
            'role'           => ['sometimes', 'string', 'in:super_admin,admin,tracker'],
        ]);

        if (!empty($data['password'])) {
            $data['password'] = Hash::make($data['password']);
        } else {
            unset($data['password']);
        }

        $role = $data['role'] ?? null;
        unset($data['role']);

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
            'pending'      => User::where('active', false)->count(),
        ]);
    }

    private function present(User $u): array
    {
        return [
            'id'             => $u->id,
            'name'           => $u->name,
            'phone'          => $u->phone,
            'email'          => $u->email,
            'position_type'  => $u->position_type,
            'position_other' => $u->position_other,
            'active'         => (bool) $u->active,
            'last_login_at'  => $u->last_login_at?->toIso8601String(),
            'created_at'     => $u->created_at?->toIso8601String(),
            'roles'          => $u->roles->pluck('name')->all(),
        ];
    }
}
