<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Target;
use App\Models\TargetCurrentStatus;
use App\Models\Tracker;
use App\Models\User;
use App\Models\UserScope;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;

class TrackerController extends Controller
{
    /** GET /api/trackers — list grouped by user_id (1 row per person · multiple villages rolled up) */
    public function index(Request $request): JsonResponse
    {
        $q = Tracker::query()
            ->with(['village:id,name,moo,tambon_id', 'village.tambon:id,name,amphur_id', 'village.tambon.amphur:id,name'])
            ->where('active', true);

        if ($request->filled('q')) {
            $term = trim($request->q);
            $q->where(fn ($w) => $w->where('full_name', 'like', "%$term%")->orWhere('phone', 'like', "%$term%"));
        }
        if ($request->filled('position')) {
            $q->where('position', $request->position);
        }
        if ($request->filled('village_id')) {
            $q->where('village_id', (int) $request->village_id);
        }

        $trackers = $q->orderBy('full_name')->orderBy('id')->get();

        // Stats per village
        $villageIds = $trackers->pluck('village_id')->unique();
        $stats = DB::table('targets')
            ->leftJoin('target_current_status as tcs', 'tcs.target_id', '=', 'targets.id')
            ->whereIn('targets.village_id', $villageIds)
            ->where('targets.active', true)
            ->selectRaw('
                targets.village_id,
                COUNT(*) as total,
                COUNT(CASE WHEN tcs.status_code IS NOT NULL AND tcs.status_code <> "4.1" THEN 1 END) as done
            ')
            ->groupBy('targets.village_id')
            ->get()
            ->keyBy('village_id');

        // Group by user_id (if exists) — ถ้าไม่มี user_id ให้ตัวเองเป็น 1 กลุ่ม
        $groupKey = fn ($t) => $t->user_id ?? "no-user-{$t->id}";
        $groups = $trackers->groupBy($groupKey);

        $rows = $groups->map(function ($group) use ($stats) {
            $first = $group->first();
            $villages = $group->map(function ($t) use ($stats) {
                $s = $stats->get($t->village_id);
                return [
                    'tracker_id' => $t->id,
                    'village_id' => $t->village_id,
                    'name'       => $t->village?->name,
                    'moo'        => $t->village?->moo,
                    'tambon'     => $t->village?->tambon?->name,
                    'amphur'     => $t->village?->tambon?->amphur?->name,
                    'tambon_id'  => $t->village?->tambon_id,
                    'amphur_id'  => $t->village?->tambon?->amphur_id,
                    'total'      => (int) ($s->total ?? 0),
                    'done'       => (int) ($s->done ?? 0),
                ];
            })->sortBy(fn ($v) => sprintf('%s-%05d', $v['name'], (int) ($v['moo'] ?? 0)))->values();

            $total = $villages->sum('total');
            $done  = $villages->sum('done');

            return [
                'id'             => $first->id,
                'tracker_ids'    => $group->pluck('id')->values()->all(),
                'user_id'        => $first->user_id,
                'has_account'    => !is_null($first->user_id),
                'full_name'      => $first->full_name,
                'position'       => $first->position,
                'position_other' => $first->position_other,
                'phone'          => $first->phone,
                'villages'       => $villages,
                'village_count'  => $villages->count(),
                'total'          => $total,
                'done'           => $done,
                'pct'            => $total > 0 ? round(($done / $total) * 100) : 0,
                // เผื่อ frontend ยังใช้ field เดิม (backward compat)
                'village_id'     => $first->village_id,
                'village'        => $first->village?->name,
                'moo'            => $first->village?->moo,
                'tambon'         => $first->village?->tambon?->name,
                'tambon_id'      => $first->village?->tambon_id,
                'amphur'         => $first->village?->tambon?->amphur?->name,
                'amphur_id'      => $first->village?->tambon?->amphur_id,
            ];
        })->values();

        return response()->json([
            'data'         => $rows,
            'total'        => $rows->count(),
            'current_page' => 1,
            'last_page'    => 1,
        ]);
    }

    /** POST /api/trackers */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'full_name'      => ['required', 'string', 'max:150'],
            'position'       => ['required', 'string', 'max:40'],
            'position_other' => ['nullable', 'string', 'max:100'],
            'phone'          => ['nullable', 'string', 'max:20'],
            'village_id'     => ['required', 'integer', 'exists:villages,id'],
        ]);
        $tracker = Tracker::create($data + ['active' => true]);
        return response()->json(['data' => $tracker], 201);
    }

    /** PATCH /api/trackers/{id} — ถ้ามี user_id จะ cascade ข้อมูลคน (ไม่รวม village_id) ไปทุก record ในกลุ่ม */
    public function update(Request $request, int $id): JsonResponse
    {
        $tracker = Tracker::findOrFail($id);
        $data = $request->validate([
            'full_name'      => ['sometimes', 'string', 'max:150'],
            'position'       => ['sometimes', 'string', 'max:40'],
            'position_other' => ['nullable', 'string', 'max:100'],
            'phone'          => ['nullable', 'string', 'max:20'],
            'village_id'     => ['sometimes', 'integer', 'exists:villages,id'],
            'active'         => ['sometimes', 'boolean'],
        ]);

        // แยก fields: person-level (เกี่ยวกับคน) vs row-level (เฉพาะ record)
        $personFields = collect($data)->only(['full_name', 'position', 'position_other', 'phone'])->toArray();
        $rowFields    = collect($data)->only(['village_id', 'active'])->toArray();

        if ($tracker->user_id && !empty($personFields)) {
            // อัปเดต person-level ทุก tracker ในกลุ่ม + sync User ที่ link อยู่
            Tracker::where('user_id', $tracker->user_id)->update($personFields);
            if (isset($personFields['full_name']) || isset($personFields['phone'])) {
                $userUpdate = [];
                if (isset($personFields['full_name'])) $userUpdate['name'] = $personFields['full_name'];
                if (isset($personFields['phone']))     $userUpdate['phone'] = $personFields['phone'];
                User::where('id', $tracker->user_id)->update($userUpdate);
            }
        } elseif (!empty($personFields)) {
            $tracker->update($personFields);
        }

        if (!empty($rowFields)) $tracker->update($rowFields);

        return response()->json(['data' => $tracker->fresh()]);
    }

    /** DELETE /api/trackers/{id} — ถ้ามี user_id ลบทั้งกลุ่ม + UserScope ทั้งหมด */
    public function destroy(int $id): JsonResponse
    {
        $tracker = Tracker::findOrFail($id);

        if ($tracker->user_id) {
            $count = Tracker::where('user_id', $tracker->user_id)->update(['active' => false]);
            UserScope::where('user_id', $tracker->user_id)->delete();
            return response()->json(['message' => "ลบกลุ่มแล้ว ($count หมู่บ้าน)"]);
        }

        $tracker->update(['active' => false]);
        return response()->json(['message' => 'Tracker deactivated']);
    }

    /**
     * POST /api/trackers/{id}/create-user
     * เปิดบัญชี User ให้ผู้กำกับติดตามคนนี้ (เฉพาะ Super Admin)
     * - สร้าง User (role=tracker) ด้วยข้อมูลจาก tracker
     * - สร้าง UserScope (village) เพื่อล็อกขอบเขตเห็นเฉพาะหมู่บ้านนั้น
     * - link tracker.user_id = user.id
     */
    public function createUser(Request $request, int $id): JsonResponse
    {
        $tracker = Tracker::findOrFail($id);

        if ($tracker->user_id) {
            return response()->json(['message' => 'ผู้กำกับติดตามคนนี้มีบัญชีอยู่แล้ว'], 422);
        }
        if (!$tracker->phone) {
            return response()->json(['message' => 'ต้องใส่เบอร์โทรในข้อมูลผู้กำกับติดตามก่อนเปิดบัญชี'], 422);
        }

        $data = $request->validate([
            'password' => ['required', Password::min(6)],
        ]);

        // ตรวจซ้ำเบอร์โทรในระบบ
        if (User::where('phone', $tracker->phone)->exists()) {
            return response()->json([
                'message' => "เบอร์ {$tracker->phone} ถูกใช้แล้วในระบบ — กรุณาเปลี่ยนเบอร์ใน tracker ก่อน",
            ], 422);
        }

        $user = DB::transaction(function () use ($tracker, $data) {
            $user = User::create([
                'name'           => $tracker->full_name,
                'phone'          => $tracker->phone,
                'position_type'  => $tracker->position,
                'position_other' => $tracker->position_other,
                'password'       => Hash::make($data['password']),
                'active'         => true,
            ]);
            $user->assignRole('tracker');

            UserScope::create([
                'user_id'    => $user->id,
                'scope_type' => 'village',
                'scope_id'   => $tracker->village_id,
            ]);

            $tracker->update(['user_id' => $user->id]);
            return $user;
        });

        return response()->json([
            'message' => "เปิดบัญชีให้ {$tracker->full_name} เรียบร้อย — login ด้วยเบอร์ {$tracker->phone}",
            'user_id' => $user->id,
        ]);
    }
}
