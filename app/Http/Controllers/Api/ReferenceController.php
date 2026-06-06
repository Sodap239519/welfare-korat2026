<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Amphur;
use App\Models\Channel;
use App\Models\ProjectPhase;
use App\Models\RegistrationStatus;
use App\Models\Tambon;
use App\Models\Village;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ReferenceController extends Controller
{
    public function statuses(): JsonResponse
    {
        return response()->json([
            'data' => RegistrationStatus::orderBy('sort_order')->get(['code', 'label', 'color', 'requires_note', 'requires_channel']),
        ]);
    }

    /** GET /api/ref/sub-statuses[?parent=4.2] — ขั้นย่อยของสถานะหลัก */
    public function subStatuses(\Illuminate\Http\Request $request): JsonResponse
    {
        $q = \App\Models\RegistrationSubStatus::active()->orderBy('parent_code')->orderBy('sort_order');
        if ($request->filled('parent')) $q->childrenOf((string) $request->parent);
        return response()->json([
            'data' => $q->get(['code', 'parent_code', 'label', 'icon', 'color', 'actor_role', 'sort_order']),
        ]);
    }

    /** GET /api/ref/submitter-roles — list ตำแหน่งผู้ส่งเอกสาร */
    public function submitterRoles(): JsonResponse
    {
        return response()->json([
            'data' => collect(\App\Models\DocumentBatch::submitterRoleLabels())
                ->map(fn ($label, $code) => ['code' => $code, 'label' => $label])
                ->values(),
        ]);
    }

    public function channels(): JsonResponse
    {
        return response()->json([
            'data' => Channel::orderBy('sort_order')->get(['id', 'code', 'name', 'icon']),
        ]);
    }

    public function banks(): JsonResponse
    {
        return response()->json([
            'data' => collect(\App\Models\Bank::optionsMap())->map(fn ($name, $code) => [
                'code' => $code,
                'name' => $name,
            ])->values(),
        ]);
    }

    public function amphurs(): JsonResponse
    {
        return response()->json([
            'data' => Amphur::orderBy('name')->get(['id', 'name']),
        ]);
    }

    /**
     * Public stats สำหรับหน้า Login — แสดงตัวเลขกลุ่มเป้าหมาย/ตำบล/อำเภอ
     * Cache 5 นาทีเพื่อกัน hit DB ทุกครั้งที่เปิดหน้า login
     */
    public function publicStats(): JsonResponse
    {
        $stats = \Illuminate\Support\Facades\Cache::remember('login.public_stats', 300, function () {
            return [
                'targets' => (int) DB::table('targets')->where('active', true)->count(),
                'tambons' => (int) Tambon::count(),
                'amphurs' => (int) Amphur::count(),
            ];
        });
        return response()->json($stats);
    }

    /**
     * Public dashboard งานนักศึกษา — สำหรับหน้าแรก (ไม่ต้อง login)
     * อาจารย์/ผู้บริหารติดตามภาพรวมการลงพื้นที่ของนักศึกษาได้
     * Cache 5 นาที กัน DB โดนถล่มจากหน้า public
     */
    public function publicStudentDashboard(): JsonResponse
    {
        $data = \Illuminate\Support\Facades\Cache::remember('public.student_dashboard', 300, function () {
            return \App\Http\Controllers\Api\StudentDashboardController::compute(null);
        });
        return response()->json($data);
    }

    public function tambons(Request $request): JsonResponse
    {
        $q = Tambon::orderBy('name');
        if ($request->filled('amphur_id')) $q->where('amphur_id', (int) $request->amphur_id);
        return response()->json(['data' => $q->get(['id', 'name', 'amphur_id'])]);
    }

    public function villages(Request $request): JsonResponse
    {
        $q = Village::orderBy('name');
        if ($request->filled('tambon_id')) $q->where('tambon_id', (int) $request->tambon_id);
        return response()->json(['data' => $q->get(['id', 'name', 'moo', 'tambon_id'])]);
    }

    /**
     * GET /api/auth/geo/village-names?tambon_id=X
     * คืนหมู่บ้านที่กรุ๊ปด้วย "ชื่อหมู่บ้าน" รวม moo เข้าด้วยกัน
     * ผู้กำกับติดตามดูแลทั้งหมู่บ้าน ไม่ต้องลงลึกถึงหมู่ที่
     */
    public function villageNames(Request $request): JsonResponse
    {
        $rows = Village::query()
            ->when($request->filled('tambon_id'), fn ($q) => $q->where('tambon_id', (int) $request->tambon_id))
            ->orderBy('name')->orderBy('moo')
            ->get(['id', 'name', 'moo', 'tambon_id']);

        $grouped = $rows->groupBy('name')->map(function ($group, $name) {
            $moos = $group->pluck('moo')->filter()->all();
            return [
                'name'        => $name,
                'tambon_id'   => $group->first()->tambon_id,
                'village_ids' => $group->pluck('id')->all(),
                'moo_count'   => count($moos),
                'moo_label'   => count($moos) > 0 ? 'ม.' . implode(', ม.', $moos) : null,
            ];
        })->values();

        return response()->json(['data' => $grouped]);
    }

    public function projectPhases(): JsonResponse
    {
        $resp = response()->json([
            'data' => ProjectPhase::orderBy('sort_order')->get(['id', 'name', 'sop_level', 'icon', 'description', 'details', 'is_current']),
        ]);
        // กัน browser HTTP cache — ข้อมูลนี้ต้องสด เพราะ admin แก้แล้วต้องเห็นทันที
        return $resp->header('Cache-Control', 'no-store, no-cache, must-revalidate')
                    ->header('Pragma', 'no-cache');
    }

    /** POST /api/admin/phases/{id}/set-current — Super Admin only · คืน phases ทั้งหมดใน response เลย (เลี่ยง cache GET ที่ตามมา) */
    public function setCurrentPhase(int $id): JsonResponse
    {
        $phase = ProjectPhase::findOrFail($id);

        DB::transaction(function () use ($phase) {
            ProjectPhase::query()->update(['is_current' => false]);
            $phase->update(['is_current' => true]);
        });

        activity('sop_phase')
            ->causedBy(auth()->user())
            ->performedOn($phase)
            ->log("ตั้งขั้น SOP ปัจจุบันเป็น ชั้น {$phase->sop_level} — {$phase->name}");

        return response()->json([
            'message' => "ตั้งขั้นปัจจุบันเป็น ชั้น {$phase->sop_level} เรียบร้อย",
            'phase'   => $phase->fresh(),   // fresh() รับ relations ไม่ใช่ columns — ส่ง model ทั้งตัว
            // ส่ง phases สดทุกตัวกลับ — frontend จะแทน phases.value ทันที ไม่ต้อง GET ตามมา
            'phases'  => ProjectPhase::orderBy('sort_order')->get(['id', 'name', 'sop_level', 'icon', 'description', 'details', 'is_current']),
        ])->header('Cache-Control', 'no-store, no-cache, must-revalidate');
    }

    private static function detailsRules(): array
    {
        return [
            'details'                  => ['sometimes', 'nullable', 'array'],
            'details.summary'          => ['sometimes', 'nullable', 'string', 'max:500'],
            'details.footer'           => ['sometimes', 'nullable', 'string', 'max:500'],
            'details.bullets'          => ['sometimes', 'nullable', 'array', 'max:20'],
            'details.bullets.*.icon'   => ['sometimes', 'nullable', 'string', 'max:80'],
            'details.bullets.*.text'   => ['required_with:details.bullets', 'string', 'max:300'],
        ];
    }

    /** POST /api/admin/phases — สร้างขั้น SOP ใหม่ */
    public function storePhase(Request $request): JsonResponse
    {
        $data = $request->validate(array_merge([
            'name'        => ['required', 'string', 'max:150'],
            'description' => ['nullable', 'string', 'max:500'],
            'sop_level'   => ['nullable', 'integer', 'min:1', 'max:99'],
            'icon'        => ['nullable', 'string', 'max:80'],
        ], self::detailsRules()));

        $maxLevel = (int) ProjectPhase::max('sop_level');
        $maxSort  = (int) ProjectPhase::max('sort_order');

        $phase = ProjectPhase::create([
            'name'        => $data['name'],
            'description' => $data['description'] ?? null,
            'details'     => $data['details'] ?? null,
            'sop_level'   => $data['sop_level'] ?? ($maxLevel + 1),
            'icon'        => $data['icon'] ?? 'fi-rr-circle',
            'sort_order'  => $maxSort + 1,
            'is_current'  => false,
        ]);

        activity('sop_phase')->causedBy(auth()->user())->performedOn($phase)
            ->log("เพิ่มขั้น SOP ใหม่: ชั้น {$phase->sop_level} — {$phase->name}");

        return response()->json([
            'data'   => $phase,
            'phases' => ProjectPhase::orderBy('sort_order')->get(['id', 'name', 'sop_level', 'icon', 'description', 'details', 'is_current']),
        ], 201)->header('Cache-Control', 'no-store');
    }

    /** PATCH /api/admin/phases/{id} — แก้ไขชื่อ/รายละเอียด/sop_level/details */
    public function updatePhase(Request $request, int $id): JsonResponse
    {
        $phase = ProjectPhase::findOrFail($id);
        $data = $request->validate(array_merge([
            'name'        => ['sometimes', 'string', 'max:150'],
            'description' => ['sometimes', 'nullable', 'string', 'max:500'],
            'sop_level'   => ['sometimes', 'integer', 'min:1', 'max:99'],
            'icon'        => ['sometimes', 'nullable', 'string', 'max:80'],
        ], self::detailsRules()));
        $phase->update($data);

        activity('sop_phase')->causedBy(auth()->user())->performedOn($phase)
            ->log("แก้ไขขั้น SOP: ชั้น {$phase->sop_level} — {$phase->name}");

        return response()->json([
            'data'   => $phase->fresh(),
            'phases' => ProjectPhase::orderBy('sort_order')->get(['id', 'name', 'sop_level', 'icon', 'description', 'details', 'is_current']),
        ])->header('Cache-Control', 'no-store');
    }

    /** DELETE /api/admin/phases/{id} — ลบขั้น SOP (ไม่ลบขั้นที่กำลังเป็นปัจจุบัน) */
    public function destroyPhase(int $id): JsonResponse
    {
        $phase = ProjectPhase::findOrFail($id);

        if ($phase->is_current) {
            return response()->json([
                'message' => 'ลบขั้นที่กำลังเป็นปัจจุบันไม่ได้ — โปรดตั้งขั้นอื่นเป็นปัจจุบันก่อน',
            ], 422);
        }

        $info = "ชั้น {$phase->sop_level} — {$phase->name}";
        $phase->delete();

        activity('sop_phase')->causedBy(auth()->user())
            ->log("ลบขั้น SOP: $info");

        return response()->json([
            'message' => "ลบ $info เรียบร้อย",
            'phases'  => ProjectPhase::orderBy('sort_order')->get(['id', 'name', 'sop_level', 'icon', 'description', 'details', 'is_current']),
        ])->header('Cache-Control', 'no-store');
    }

    /** Quick aggregate for Overview page — รับ filter ?amphur_id=&tambon_id=&village_id= */
    public function overviewMetrics(Request $request): JsonResponse
    {
        $applyScope = fn ($q) => $q
            ->when($request->filled('amphur_id'),  fn ($x) => $x->where('targets.amphur_id',  (int) $request->amphur_id))
            ->when($request->filled('tambon_id'),  fn ($x) => $x->where('targets.tambon_id',  (int) $request->tambon_id))
            ->when($request->filled('village_id'), fn ($x) => $x->where('targets.village_id', (int) $request->village_id));

        $rows = $applyScope(
                DB::table('target_current_status as tcs')
                    ->join('targets', 'targets.id', '=', 'tcs.target_id')
                    ->where('targets.active', true)
            )
            ->selectRaw('tcs.status_code, COUNT(*) as n')
            ->groupBy('tcs.status_code')
            ->pluck('n', 'status_code');

        $total = (int) \App\Models\Target::query()->where('active', true)
            ->when($request->filled('amphur_id'),  fn ($q) => $q->where('amphur_id',  (int) $request->amphur_id))
            ->when($request->filled('tambon_id'),  fn ($q) => $q->where('tambon_id',  (int) $request->tambon_id))
            ->when($request->filled('village_id'), fn ($q) => $q->where('village_id', (int) $request->village_id))
            ->count();
        $registered = (int) collect($rows)->except(['4.1'])->sum();

        return response()->json([
            'total'      => $total,
            'registered' => $registered,
            'pct_done'   => $total > 0 ? round(($registered / $total) * 100, 1) : 0,
            'level_4' => [
                'check_rights' => (int) ($rows['4.3'] ?? 0),
                'extra_docs'   => (int) ($rows['4.4'] ?? 0),
                'kyc_waiting'  => (int) ($rows['4.6'] ?? 0),
                'kyc_done'     => (int) ($rows['4.7'] ?? 0),
            ],
            'by_channel' => $applyScope(
                    DB::table('target_current_status as tcs')
                        ->join('targets', 'targets.id', '=', 'tcs.target_id')
                        ->leftJoin('channels', 'channels.id', '=', 'tcs.channel_id')
                        ->where('targets.active', true)
                        ->whereNotNull('channels.name')
                )
                ->groupBy('channels.name')
                ->pluck(DB::raw('COUNT(*)'), 'channels.name'),
            'by_bank'    => $this->byBankBreakdown($request),
        ]);
    }

    private function byBankBreakdown(Request $request): array
    {
        $bankCh = \App\Models\Channel::where('code', 'bank')->first();
        if (!$bankCh) return [];

        $counts = DB::table('target_current_status as tcs')
            ->join('targets', 'targets.id', '=', 'tcs.target_id')
            ->where('targets.active', true)
            ->where('tcs.channel_id', $bankCh->id)
            ->whereNotNull('tcs.sub_channel')
            ->when($request->filled('amphur_id'),  fn ($q) => $q->where('targets.amphur_id',  (int) $request->amphur_id))
            ->when($request->filled('tambon_id'),  fn ($q) => $q->where('targets.tambon_id',  (int) $request->tambon_id))
            ->when($request->filled('village_id'), fn ($q) => $q->where('targets.village_id', (int) $request->village_id))
            ->groupBy('tcs.sub_channel')
            ->pluck(DB::raw('COUNT(*)'), 'tcs.sub_channel');

        $banks  = \App\Models\Bank::optionsMap();
        $logos  = \App\Models\Bank::logoMap();
        $result = [];
        foreach ($banks as $code => $name) {
            $logoPath = $logos[$code] ?? null;
            $logoUrl  = $logoPath
                ? (str_starts_with($logoPath, 'http') || str_starts_with($logoPath, '/')
                    ? $logoPath
                    : '/img/banks/' . $logoPath)
                : null;
            $result[] = [
                'code'     => $code,
                'name'     => $name,
                'count'    => (int) ($counts[$code] ?? 0),
                'logo_url' => $logoUrl,
            ];
        }
        return $result;
    }
}
