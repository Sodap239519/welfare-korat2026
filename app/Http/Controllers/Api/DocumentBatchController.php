<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Channel;
use App\Models\DocumentBatch;
use App\Models\Target;
use App\Models\TargetCurrentStatus;
use App\Models\TargetStatusLog;
use App\Models\User;
use App\Notifications\BatchReceived;
use App\Notifications\BatchRecorded;
use App\Notifications\BatchRejected;
use App\Notifications\BatchSubmitted;
use Illuminate\Support\Facades\Notification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Document Batch — กล่องเอกสารลงทะเบียนต่อรอบส่งให้ธนาคาร
 *
 * Lifecycle:
 *   draft → submitted → received → recorded
 *                ↓
 *             rejected (ที่จุดใดก็ได้)
 *
 * Sub-status mapping ของ target ใน batch:
 *   add ลง batch → 4.2.2 ส่งแบบฟอร์มเอกสารแล้ว
 *   bank receive → 4.2.3 ธนาคารรับเอกสารแล้ว
 *   bank record  → 4.2.4 ธนาคารบันทึกข้อมูลลงระบบแล้ว
 *   reject       → null (กลับสถานะ tracker ใหม่ตัดสินใจ)
 */
class DocumentBatchController extends Controller
{
    private const SUB_SUBMITTED = '4.2.2';
    private const SUB_RECEIVED  = '4.2.3';
    private const SUB_RECORDED  = '4.2.4';

    // ─────────────────────────────────────────────────────────────
    // LIST / INDEX
    // ─────────────────────────────────────────────────────────────

    /**
     * GET /api/batches
     *   ?scope=mine | inbox | all (default depends on role)
     *   ?status=draft,submitted,received,recorded,rejected (CSV)
     *   ?channel_id=&sub_channel=
     *   ?from=YYYY-MM-DD&to=YYYY-MM-DD
     *   ?page=1&per_page=20
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $scope = $request->input('scope', $this->defaultScopeFor($user));

        $q = DocumentBatch::query()
            ->with(['tracker:id,name,role', 'channel:id,code,name', 'receivedBy:id,name', 'recordedBy:id,name'])
            ->withCount('targets')
            ->orderByDesc('batch_date')
            ->orderByDesc('id');

        // ─── scope ───
        if ($scope === 'mine') {
            $q->where('tracker_user_id', $user->id);
        } elseif ($scope === 'inbox') {
            // bank/admin: เห็นเฉพาะที่รอเขาทำ (submitted หรือ received)
            $q->whereIn('status', [DocumentBatch::ST_SUBMITTED, DocumentBatch::ST_RECEIVED]);
            // bank_staff: กรองเฉพาะธนาคารตัวเอง (admin/super_admin ไม่กรอง)
            if ($user->hasRole('bank_staff') && !$user->hasAnyRole(['admin', 'super_admin'])) {
                $q->where('channel_id', $user->bank_channel_id)
                  ->where('sub_channel', $user->bank_sub_channel);
            }
        }
        // 'all' = ไม่กรอง (super_admin)

        // ─── filters ───
        if ($request->filled('status')) {
            $codes = array_filter(explode(',', $request->input('status')));
            $q->whereIn('status', $codes);
        }
        if ($request->filled('channel_id'))  $q->where('channel_id', (int) $request->channel_id);
        if ($request->filled('sub_channel')) $q->where('sub_channel', $request->sub_channel);
        if ($request->filled('from')) $q->whereDate('batch_date', '>=', $request->from);
        if ($request->filled('to'))   $q->whereDate('batch_date', '<=', $request->to);

        $perPage = max(1, min((int) $request->input('per_page', 20), 100));
        return response()->json($q->paginate($perPage));
    }

    /** GET /api/batches/{id} — รายละเอียดเต็ม + รายชื่อ targets ใน batch */
    public function show(Request $request, int $id): JsonResponse
    {
        $batch = DocumentBatch::with([
            'tracker:id,name,role,phone',
            'channel:id,code,name',
            'receivedBy:id,name',
            'recordedBy:id,name',
            'targets:id,prefix,first_name,last_name,village_id,tambon_id,amphur_id',
            'targets.village:id,name,moo',
            'targets.tambon:id,name',
            'targets.amphur:id,name',
        ])->findOrFail($id);

        $this->authorizeView($batch, $request->user());

        return response()->json(['data' => $batch]);
    }

    // ─────────────────────────────────────────────────────────────
    // CREATE / UPDATE
    // ─────────────────────────────────────────────────────────────

    /**
     * POST /api/batches
     *   { batch_date, channel_id, sub_channel?, submitter_role, submitter_name?, notes? }
     *   → คืน batch ใหม่ status=draft (batch_no auto-gen)
     */
    public function store(Request $request): JsonResponse
    {
        $data = $this->validateCreate($request);
        $user = $request->user();

        $batch = DocumentBatch::create([
            'batch_no'        => DocumentBatch::generateBatchNo($data['batch_date']),
            'tracker_user_id' => $user->id,
            'batch_date'      => $data['batch_date'],
            'channel_id'      => $data['channel_id'],
            'sub_channel'     => $data['sub_channel'] ?? null,
            'status'          => DocumentBatch::ST_DRAFT,
            'submitter_role'  => $data['submitter_role'],
            'submitter_name'  => $data['submitter_name'] ?? null,
            'notes'           => $data['notes'] ?? null,
        ]);

        return response()->json(['data' => $batch->load(['channel', 'tracker'])], 201);
    }

    /**
     * POST /api/batches/quick-create
     *   { batch_date, channel_id, sub_channel?, submitter_role, submitter_name?, target_ids[] }
     *   → สร้าง batch + add targets ครั้งเดียว (UX shortcut จากหน้า Targets bulk select)
     */
    public function quickCreate(Request $request): JsonResponse
    {
        $base = $this->validateCreate($request);
        $more = $request->validate([
            'target_ids'   => ['required', 'array', 'min:1', 'max:500'],
            'target_ids.*' => ['integer', 'exists:targets,id'],
        ], ['target_ids.max' => 'ใส่ห่อเอกสารได้สูงสุด 500 รายต่อครั้ง']);

        $user = $request->user();

        $batch = DB::transaction(function () use ($base, $more, $user) {
            $batch = DocumentBatch::create([
                'batch_no'        => DocumentBatch::generateBatchNo($base['batch_date']),
                'tracker_user_id' => $user->id,
                'batch_date'      => $base['batch_date'],
                'channel_id'      => $base['channel_id'],
                'sub_channel'     => $base['sub_channel'] ?? null,
                'status'          => DocumentBatch::ST_DRAFT,
                'submitter_role'  => $base['submitter_role'],
                'submitter_name'  => $base['submitter_name'] ?? null,
                'notes'           => $base['notes'] ?? null,
            ]);
            $this->attachTargetsAndAdvance($batch, $more['target_ids'], self::SUB_SUBMITTED, $user, 'add ลง batch');
            return $batch;
        });

        return response()->json([
            'data'    => $batch->loadCount('targets')->load(['channel', 'tracker']),
            'message' => "สร้าง batch + เพิ่ม {$batch->targets_count} ราย เรียบร้อย",
        ], 201);
    }

    /** PATCH /api/batches/{id} — แก้ไข draft เท่านั้น */
    public function update(Request $request, int $id): JsonResponse
    {
        $batch = DocumentBatch::findOrFail($id);
        $this->authorizeEdit($batch, $request->user());
        $this->assertStatus($batch, DocumentBatch::ST_DRAFT, 'แก้ไขได้เฉพาะ draft');

        $data = $request->validate([
            'batch_date'     => ['sometimes', 'date'],
            'channel_id'     => ['sometimes', 'integer', 'exists:channels,id'],
            'sub_channel'    => ['sometimes', 'nullable', 'string', 'max:50'],
            'submitter_role' => ['sometimes', 'string', 'in:'.implode(',', array_keys(DocumentBatch::submitterRoleLabels()))],
            'submitter_name' => ['sometimes', 'nullable', 'string', 'max:150'],
            'notes'          => ['sometimes', 'nullable', 'string', 'max:2000'],
        ]);

        $batch->update($data);
        return response()->json(['data' => $batch->fresh()->load(['channel'])]);
    }

    /** DELETE /api/batches/{id} — ลบ draft ได้อย่างเดียว */
    public function destroy(Request $request, int $id): JsonResponse
    {
        $batch = DocumentBatch::findOrFail($id);
        $this->authorizeEdit($batch, $request->user());
        $this->assertStatus($batch, DocumentBatch::ST_DRAFT, 'ลบได้เฉพาะ draft');

        DB::transaction(function () use ($batch, $request) {
            // rollback sub_status ของ targets กลับ null
            $this->rollbackTargetsSubStatus($batch, $request->user(), 'batch ถูกลบ');
            $batch->delete();
        });
        return response()->json(['message' => 'ลบ batch แล้ว']);
    }

    // ─────────────────────────────────────────────────────────────
    // TARGETS in BATCH (add / remove)
    // ─────────────────────────────────────────────────────────────

    /** POST /api/batches/{id}/targets — bulk add targets ลง draft (auto-advance sub_status → 4.2.2) */
    public function addTargets(Request $request, int $id): JsonResponse
    {
        $batch = DocumentBatch::findOrFail($id);
        $this->authorizeEdit($batch, $request->user());
        $this->assertStatus($batch, DocumentBatch::ST_DRAFT, 'เพิ่มได้เฉพาะ draft');

        $data = $request->validate([
            'target_ids'   => ['required', 'array', 'min:1', 'max:500'],
            'target_ids.*' => ['integer', 'exists:targets,id'],
        ]);

        $added = $this->attachTargetsAndAdvance($batch, $data['target_ids'], self::SUB_SUBMITTED, $request->user(), 'add ลง batch');
        return response()->json([
            'message' => "เพิ่ม {$added} ราย ลง batch",
            'count'   => $added,
        ]);
    }

    /** DELETE /api/batches/{id}/targets/{tid} — ดึง target ออก (draft เท่านั้น) */
    public function removeTarget(Request $request, int $id, int $tid): JsonResponse
    {
        $batch = DocumentBatch::findOrFail($id);
        $this->authorizeEdit($batch, $request->user());
        $this->assertStatus($batch, DocumentBatch::ST_DRAFT, 'เอาออกได้เฉพาะ draft');

        DB::transaction(function () use ($batch, $tid, $request) {
            $batch->targets()->detach($tid);
            // rollback target sub_status
            TargetCurrentStatus::where('target_id', $tid)->update(['sub_status_code' => null]);
            $this->logTargetEvent($tid, $request->user(), null, 'เอาออกจาก batch #'.$batch->batch_no);
        });

        return response()->json(['message' => 'เอาออกจาก batch แล้ว']);
    }

    // ─────────────────────────────────────────────────────────────
    // LIFECYCLE — submit / receive / record / reject
    // ─────────────────────────────────────────────────────────────

    /** POST /api/batches/{id}/submit — tracker ยืนยันส่ง (draft → submitted) */
    public function submit(Request $request, int $id): JsonResponse
    {
        $batch = DocumentBatch::findOrFail($id);
        $this->authorizeEdit($batch, $request->user());
        $this->assertStatus($batch, DocumentBatch::ST_DRAFT, 'ส่งได้เฉพาะ draft');

        if ($batch->targets()->count() === 0) {
            return response()->json(['message' => 'batch ว่าง — เพิ่มรายชื่อก่อนส่ง'], 422);
        }

        $batch->update([
            'status'       => DocumentBatch::ST_SUBMITTED,
            'submitted_at' => now(),
        ]);
        $this->notifyBatchSubmitted($batch->fresh());

        return response()->json([
            'message' => "ส่ง batch #{$batch->batch_no} ({$batch->targets()->count()} ราย) แล้ว — รอธนาคารรับ",
            'data'    => $batch->fresh(),
        ]);
    }

    /** POST /api/batches/{id}/receive — bank/admin: submitted → received */
    public function receive(Request $request, int $id): JsonResponse
    {
        $batch = DocumentBatch::findOrFail($id);
        $this->authorizeBankAction($batch, $request->user());
        $this->assertStatus($batch, DocumentBatch::ST_SUBMITTED, 'รับได้เฉพาะที่ส่งมาแล้ว');

        $user = $request->user();
        DB::transaction(function () use ($batch, $user) {
            $batch->update([
                'status'              => DocumentBatch::ST_RECEIVED,
                'received_at'         => now(),
                'received_by_user_id' => $user->id,
            ]);
            // advance target sub_status → 4.2.3
            $this->advanceBatchTargets($batch, self::SUB_RECEIVED, $user, 'ธนาคารรับเอกสาร batch #'.$batch->batch_no);
        });
        $this->notifyBatchReceived($batch->fresh());

        return response()->json([
            'message' => "รับ batch #{$batch->batch_no} ({$batch->targets_count} ราย) เรียบร้อย",
            'data'    => $batch->fresh(),
        ]);
    }

    /** POST /api/batches/{id}/record — bank/admin: received → recorded */
    public function record(Request $request, int $id): JsonResponse
    {
        $batch = DocumentBatch::findOrFail($id);
        $this->authorizeBankAction($batch, $request->user());
        $this->assertStatus($batch, DocumentBatch::ST_RECEIVED, 'บันทึกได้เฉพาะที่รับมาแล้ว');

        $user = $request->user();
        DB::transaction(function () use ($batch, $user) {
            $batch->update([
                'status'              => DocumentBatch::ST_RECORDED,
                'recorded_at'         => now(),
                'recorded_by_user_id' => $user->id,
            ]);
            // advance target sub_status → 4.2.4 (บันทึกครบ — รอ 4.4 KYC ต่อไป โดย human action)
            $this->advanceBatchTargets($batch, self::SUB_RECORDED, $user, 'ธนาคารบันทึก batch #'.$batch->batch_no);
        });
        $this->notifyBatchRecorded($batch->fresh());

        return response()->json([
            'message' => "บันทึก batch #{$batch->batch_no} ({$batch->targets_count} ราย) ครบแล้ว",
            'data'    => $batch->fresh(),
        ]);
    }

    /** POST /api/batches/{id}/reject — bank/admin: ปฏิเสธ (ต้องระบุเหตุผล) */
    public function reject(Request $request, int $id): JsonResponse
    {
        $batch = DocumentBatch::findOrFail($id);
        $this->authorizeBankAction($batch, $request->user());
        if (in_array($batch->status, [DocumentBatch::ST_DRAFT, DocumentBatch::ST_RECORDED, DocumentBatch::ST_REJECTED])) {
            return response()->json(['message' => 'ปฏิเสธไม่ได้ — สถานะปัจจุบัน: '.$batch->status], 422);
        }

        $data = $request->validate([
            'reject_reason' => ['required', 'string', 'min:5', 'max:500'],
        ]);

        $user = $request->user();
        DB::transaction(function () use ($batch, $data, $user) {
            $batch->update([
                'status'        => DocumentBatch::ST_REJECTED,
                'reject_reason' => $data['reject_reason'],
            ]);
            $this->rollbackTargetsSubStatus($batch, $user, 'batch ถูกปฏิเสธ: '.$data['reject_reason']);
        });
        $this->notifyBatchRejected($batch->fresh());

        return response()->json([
            'message' => "ปฏิเสธ batch #{$batch->batch_no} แล้ว · ผู้ติดตามรับแจ้ง",
            'data'    => $batch->fresh(),
        ]);
    }

    // ─────────────────────────────────────────────────────────────
    // SUMMARY (for dashboard preview — full dashboard ใน Phase E)
    // ─────────────────────────────────────────────────────────────

    /** GET /api/batches/summary — KPI 5 ตัวสำหรับ HERO ของหน้า Batches list */
    public function summary(Request $request): JsonResponse
    {
        $today = today();
        $base = $this->scopedQuery($request->user());

        return response()->json([
            'submitted_today'  => (clone $base)->whereDate('submitted_at', $today)->count(),
            'pending_receive'  => (clone $base)->where('status', DocumentBatch::ST_SUBMITTED)->count(),
            'pending_record'   => (clone $base)->where('status', DocumentBatch::ST_RECEIVED)->count(),
            'recorded_today'   => (clone $base)->whereDate('recorded_at', $today)->count(),
            'rejected_today'   => (clone $base)->whereDate('updated_at', $today)
                                    ->where('status', DocumentBatch::ST_REJECTED)->count(),
        ]);
    }

    /**
     * GET /api/batches/dashboard — รวมข้อมูลสำหรับ executive dashboard
     *   - kpi: 5 ตัวเหมือน summary
     *   - bottleneck: batch ค้าง > 3 วัน (เรียงค้างนานสุด)
     *   - bank_leaderboard: avg ระยะส่ง→บันทึก (เร็วสุดขึ้นก่อน)
     *   - tracker_leaderboard: 7 วันล่าสุด · จำนวน batch + raย (ขยันสุดขึ้นก่อน)
     */
    public function dashboard(Request $request): JsonResponse
    {
        $user = $request->user();
        $today = today();
        $base = $this->scopedQuery($user);

        // ─── 1) KPI ───
        $kpi = [
            'submitted_today' => (clone $base)->whereDate('submitted_at', $today)->count(),
            'pending_receive' => (clone $base)->where('status', DocumentBatch::ST_SUBMITTED)->count(),
            'pending_record'  => (clone $base)->where('status', DocumentBatch::ST_RECEIVED)->count(),
            'recorded_today'  => (clone $base)->whereDate('recorded_at', $today)->count(),
            'rejected_today'  => (clone $base)->whereDate('updated_at', $today)
                                    ->where('status', DocumentBatch::ST_REJECTED)->count(),
        ];

        // ─── 2) Bottleneck — submitted หรือ received แต่ค้างเกิน 3 วัน ───
        $threeDaysAgo = now()->subDays(3);
        $bottleneck = (clone $base)
            ->with(['tracker:id,name', 'channel:id,code,name'])
            ->withCount('targets')
            ->where(function ($q) use ($threeDaysAgo) {
                $q->where(function ($q2) use ($threeDaysAgo) {
                    $q2->where('status', DocumentBatch::ST_SUBMITTED)
                       ->where('submitted_at', '<', $threeDaysAgo);
                })->orWhere(function ($q2) use ($threeDaysAgo) {
                    $q2->where('status', DocumentBatch::ST_RECEIVED)
                       ->where('received_at', '<', $threeDaysAgo);
                });
            })
            ->orderByRaw('LEAST(IFNULL(submitted_at, "9999-12-31"), IFNULL(received_at, "9999-12-31")) ASC')
            ->limit(15)
            ->get(['id', 'batch_no', 'tracker_user_id', 'channel_id', 'sub_channel', 'status', 'submitted_at', 'received_at'])
            ->map(function ($b) {
                $stuckSince = $b->status === DocumentBatch::ST_SUBMITTED ? $b->submitted_at : $b->received_at;
                return [
                    'id'             => $b->id,
                    'batch_no'       => $b->batch_no,
                    'tracker_name'   => $b->tracker?->name ?? '—',
                    'channel_name'   => $b->channel?->name ?? '—',
                    'sub_channel'    => strtoupper((string) $b->sub_channel),
                    'status'         => $b->status,
                    'targets_count'  => $b->targets_count,
                    'days_stuck'     => (int) round(abs(now()->diffInHours($stuckSince)) / 24),
                ];
            });

        // ─── 3) Bank Leaderboard — เร็วสุด (avg ส่ง→บันทึก) ───
        $bankBoard = (clone $base)
            ->where('status', DocumentBatch::ST_RECORDED)
            ->whereNotNull('submitted_at')->whereNotNull('recorded_at')
            ->selectRaw('channel_id, sub_channel,
                         COUNT(*) as total_batches,
                         AVG(TIMESTAMPDIFF(HOUR, submitted_at, recorded_at)) / 24 as avg_days')
            ->groupBy('channel_id', 'sub_channel')
            ->having('total_batches', '>', 0)
            ->orderBy('avg_days', 'ASC')
            ->limit(5)
            ->get();

        $channelMap = \App\Models\Channel::pluck('name', 'id');
        $bankMap = \App\Models\Bank::optionsMap();   // ['KTB' => 'ธ.กรุงไทย', ...]
        $bankLeaderboard = $bankBoard->map(fn ($r) => [
            'channel_name'   => $channelMap[$r->channel_id] ?? '—',
            'sub_channel'    => strtoupper((string) $r->sub_channel),
            'bank_name'      => $bankMap[strtoupper((string) $r->sub_channel)] ?? ($r->sub_channel ?: '—'),
            'avg_days'       => round((float) $r->avg_days, 1),
            'total_batches'  => (int) $r->total_batches,
        ]);

        // ─── 4) Tracker Leaderboard — 7 วันล่าสุด · ขยันสุด ───
        $sevenDaysAgo = now()->subDays(7);
        $trackerBoard = (clone $base)
            ->where('batch_date', '>=', $sevenDaysAgo->toDateString())
            ->where('status', '!=', DocumentBatch::ST_DRAFT)
            ->selectRaw('tracker_user_id, COUNT(*) as batch_count')
            ->groupBy('tracker_user_id')
            ->orderBy('batch_count', 'DESC')
            ->limit(5)
            ->get();

        $userIds = $trackerBoard->pluck('tracker_user_id')->all();
        $users   = User::whereIn('id', $userIds)->get(['id', 'name', 'position_type', 'position_other'])->keyBy('id');
        $targetCountsByTracker = \DB::table('document_batches')
            ->join('document_batch_targets as dbt', 'dbt.batch_id', '=', 'document_batches.id')
            ->whereIn('tracker_user_id', $userIds)
            ->where('batch_date', '>=', $sevenDaysAgo->toDateString())
            ->where('status', '!=', DocumentBatch::ST_DRAFT)
            ->selectRaw('tracker_user_id, COUNT(*) as cnt')
            ->groupBy('tracker_user_id')
            ->pluck('cnt', 'tracker_user_id');

        $trackerLeaderboard = $trackerBoard->map(function ($r) use ($users, $targetCountsByTracker) {
            $u = $users->get($r->tracker_user_id);
            return [
                'tracker_id'    => $r->tracker_user_id,
                'tracker_name'  => $u?->name ?? '—',
                'position'      => $u?->position_other ?: $u?->position_type ?: '—',
                'batch_count'   => (int) $r->batch_count,
                'target_count'  => (int) ($targetCountsByTracker[$r->tracker_user_id] ?? 0),
            ];
        });

        return response()->json([
            'kpi'                 => $kpi,
            'bottleneck'          => $bottleneck->values(),
            'bank_leaderboard'    => $bankLeaderboard->values(),
            'tracker_leaderboard' => $trackerLeaderboard->values(),
        ]);
    }

    /** Helper: scope query ตาม role — bank_staff เห็นแค่ธนาคารตัว, อื่นๆ เห็นทั้งระบบ */
    private function scopedQuery(User $user): \Illuminate\Database\Eloquent\Builder
    {
        $q = DocumentBatch::query();
        if ($user->hasRole('bank_staff') && !$user->hasAnyRole(['admin', 'super_admin'])) {
            $q->where('channel_id', $user->bank_channel_id)
              ->where('sub_channel', $user->bank_sub_channel);
        }
        return $q;
    }

    // ─────────────────────────────────────────────────────────────
    // NOTIFICATION DISPATCHERS
    //   - ส่ง in-app ให้ recipients · ส่ง LINE ครั้งเดียวให้ admin คนแรก
    //   - fail silently ถ้าไม่มี LINE config / ไม่มี admin
    // ─────────────────────────────────────────────────────────────
    private function notifyBatchEvent(DocumentBatch $batch, string $eventClass, array $recipients = []): void
    {
        try {
            // โหลด relations ที่ notification ใช้
            $batch->loadMissing(['tracker:id,name', 'channel:id,name', 'receivedBy:id,name', 'recordedBy:id,name']);
            $batch->loadCount('targets');

            // หา super_admin คนแรกสำหรับยิง LINE ครั้งเดียว
            $firstAdmin = User::role('super_admin')->orderBy('id')->first();

            // ทุก recipient ได้ in-app + admin คนแรกได้ LINE
            $uniqueRecipients = collect($recipients)->filter()->unique('id')->values();
            if ($firstAdmin && !$uniqueRecipients->contains('id', $firstAdmin->id)) {
                $uniqueRecipients->push($firstAdmin);
            }

            foreach ($uniqueRecipients as $user) {
                $sendLine = $firstAdmin && $user->id === $firstAdmin->id;
                $user->notify(new $eventClass($batch, $sendLine));
            }
        } catch (\Throwable $e) {
            \Log::warning("Batch notification failed [{$eventClass}]: " . $e->getMessage());
        }
    }

    private function notifyBatchSubmitted(DocumentBatch $batch): void
    {
        // notify: bank_staff ของธนาคาร + super_admin คนแรก (รับ LINE)
        $bankStaff = User::role('bank_staff')
            ->where('bank_channel_id', $batch->channel_id)
            ->where('bank_sub_channel', $batch->sub_channel)
            ->get();
        $this->notifyBatchEvent($batch, BatchSubmitted::class, $bankStaff->all());
    }

    private function notifyBatchReceived(DocumentBatch $batch): void
    {
        // notify: tracker เจ้าของ batch
        $tracker = User::find($batch->tracker_user_id);
        $this->notifyBatchEvent($batch, BatchReceived::class, $tracker ? [$tracker] : []);
    }

    private function notifyBatchRecorded(DocumentBatch $batch): void
    {
        $tracker = User::find($batch->tracker_user_id);
        $this->notifyBatchEvent($batch, BatchRecorded::class, $tracker ? [$tracker] : []);
    }

    private function notifyBatchRejected(DocumentBatch $batch): void
    {
        $tracker = User::find($batch->tracker_user_id);
        $this->notifyBatchEvent($batch, BatchRejected::class, $tracker ? [$tracker] : []);
    }

    // ─────────────────────────────────────────────────────────────
    // HELPERS — Auth / Validation / Target side-effects
    // ─────────────────────────────────────────────────────────────

    private function defaultScopeFor(User $user): string
    {
        if ($user->hasRole('super_admin') || $user->hasRole('admin')) return 'all';
        if ($user->hasRole('bank_staff')) return 'inbox';
        return 'mine';   // tracker
    }

    private function authorizeView(DocumentBatch $batch, User $user): void
    {
        if ($user->hasRole('super_admin') || $user->hasRole('admin')) return;
        if ($batch->tracker_user_id === $user->id) return;
        // bank_staff: ดูได้ถ้า batch ตรงธนาคารตัว
        if ($user->hasRole('bank_staff')
            && $batch->channel_id === $user->bank_channel_id
            && $batch->sub_channel === $user->bank_sub_channel) return;
        abort(403, 'ไม่มีสิทธิ์ดู batch นี้');
    }

    private function authorizeEdit(DocumentBatch $batch, User $user): void
    {
        if ($user->hasRole('super_admin')) return;
        if ($batch->tracker_user_id === $user->id) return;
        abort(403, 'ไม่มีสิทธิ์แก้ batch นี้ — เป็นของผู้ติดตามคนอื่น');
    }

    private function authorizeBankAction(DocumentBatch $batch, User $user): void
    {
        // admin/super_admin ทำได้ทุก batch
        if ($user->hasRole('super_admin') || $user->hasRole('admin')) return;
        // bank_staff ทำได้เฉพาะ batch ของธนาคารตัวเอง (channel + sub_channel ตรง)
        if ($user->hasRole('bank_staff')
            && $batch->channel_id === $user->bank_channel_id
            && $batch->sub_channel === $user->bank_sub_channel) return;
        abort(403, 'เฉพาะเจ้าหน้าที่ธนาคารของสาขานี้เท่านั้น');
    }

    private function assertStatus(DocumentBatch $batch, string $expected, string $message): void
    {
        if ($batch->status !== $expected) {
            abort(response()->json([
                'message' => "{$message} · สถานะปัจจุบัน: {$batch->status}",
            ], 422));
        }
    }

    private function validateCreate(Request $request): array
    {
        $channelIds = Channel::pluck('id')->all();
        $roles      = array_keys(DocumentBatch::submitterRoleLabels());

        return $request->validate([
            'batch_date'     => ['required', 'date'],
            'channel_id'     => ['required', 'integer', 'in:'.implode(',', $channelIds)],
            'sub_channel'    => ['nullable', 'string', 'max:50'],
            'submitter_role' => ['required', 'string', 'in:'.implode(',', $roles)],
            'submitter_name' => ['nullable', 'string', 'max:150'],
            'notes'          => ['nullable', 'string', 'max:2000'],
        ]);
    }

    /**
     * ผูก targets เข้า batch + ขยับ sub_status_code + log
     * คืนจำนวนที่เพิ่มจริง (กรณีบางคนถูกผูกซ้ำ unique constraint จะ skip)
     */
    private function attachTargetsAndAdvance(DocumentBatch $batch, array $targetIds, string $subCode, User $user, string $note): int
    {
        $now = now();
        $added = 0;
        // กรองเอาเฉพาะที่ยังไม่อยู่ใน batch นี้
        $existing = $batch->targets()->whereIn('targets.id', $targetIds)->pluck('targets.id')->all();
        $newIds   = array_values(array_diff($targetIds, $existing));

        if (empty($newIds)) return 0;

        $pivotRows = [];
        foreach ($newIds as $tid) {
            $pivotRows[$tid] = ['joined_at' => $now, 'created_at' => $now, 'updated_at' => $now];
        }
        $batch->targets()->attach($pivotRows);
        $added = count($newIds);

        // advance sub_status_code (เก็บ status_code เดิม = '4.2' ไม่เปลี่ยน)
        TargetCurrentStatus::whereIn('target_id', $newIds)->update([
            'sub_status_code' => $subCode,
            'updated_by'      => $user->id,
            'updated_by_name' => $user->name,
            'updated_at'      => $now,
        ]);
        // เผื่อบางรายไม่มี target_current_status เลย — สร้างใหม่ที่ 4.2
        $missing = array_diff($newIds, TargetCurrentStatus::whereIn('target_id', $newIds)->pluck('target_id')->all());
        foreach ($missing as $tid) {
            TargetCurrentStatus::create([
                'target_id'       => $tid,
                'status_code'     => '4.2',
                'sub_status_code' => $subCode,
                'channel_id'      => $batch->channel_id,
                'sub_channel'     => $batch->sub_channel,
                'updated_by'      => $user->id,
                'updated_by_name' => $user->name,
                'updated_at'      => $now,
            ]);
        }

        // log per target
        $logs = [];
        foreach ($newIds as $tid) {
            $logs[] = [
                'target_id'          => $tid,
                'status_code'        => '4.2',
                'sub_status_code'    => $subCode,
                'submitter_role'     => $batch->submitter_role,
                'submitter_name'     => $batch->submitter_name,
                'channel_id'         => $batch->channel_id,
                'sub_channel'        => $batch->sub_channel,
                'note'               => $note.' #'.$batch->batch_no,
                'user_id'            => $user->id,
                'user_name_snapshot' => $user->name,
                'changed_at'         => $now,
                'created_at'         => $now,
                'updated_at'         => $now,
            ];
        }
        foreach (array_chunk($logs, 200) as $chunk) {
            TargetStatusLog::insert($chunk);
        }

        return $added;
    }

    /** ขยับ sub_status_code ของทุก target ใน batch ไปค่าใหม่ (ใช้ตอน receive/record) */
    private function advanceBatchTargets(DocumentBatch $batch, string $subCode, User $user, string $note): void
    {
        $now = now();
        $tids = $batch->targets()->pluck('targets.id')->all();
        if (empty($tids)) return;

        TargetCurrentStatus::whereIn('target_id', $tids)->update([
            'sub_status_code' => $subCode,
            'updated_by'      => $user->id,
            'updated_by_name' => $user->name,
            'updated_at'      => $now,
        ]);

        $logs = [];
        foreach ($tids as $tid) {
            $logs[] = [
                'target_id'          => $tid,
                'status_code'        => '4.2',
                'sub_status_code'    => $subCode,
                'submitter_role'     => $batch->submitter_role,
                'submitter_name'     => $batch->submitter_name,
                'channel_id'         => $batch->channel_id,
                'sub_channel'        => $batch->sub_channel,
                'note'               => $note,
                'user_id'            => $user->id,
                'user_name_snapshot' => $user->name,
                'changed_at'         => $now,
                'created_at'         => $now,
                'updated_at'         => $now,
            ];
        }
        foreach (array_chunk($logs, 200) as $chunk) {
            TargetStatusLog::insert($chunk);
        }
    }

    /** Rollback sub_status_code → null (ตอน batch ถูกลบ/reject) */
    private function rollbackTargetsSubStatus(DocumentBatch $batch, User $user, string $note): void
    {
        $tids = $batch->targets()->pluck('targets.id')->all();
        if (empty($tids)) return;

        TargetCurrentStatus::whereIn('target_id', $tids)->update([
            'sub_status_code' => null,
            'updated_by'      => $user->id,
            'updated_by_name' => $user->name,
            'updated_at'      => now(),
        ]);
        foreach ($tids as $tid) {
            $this->logTargetEvent($tid, $user, null, $note);
        }
    }

    private function logTargetEvent(int $targetId, User $user, ?string $subCode, string $note): void
    {
        TargetStatusLog::create([
            'target_id'          => $targetId,
            'status_code'        => '4.2',
            'sub_status_code'    => $subCode,
            'user_id'            => $user->id,
            'user_name_snapshot' => $user->name,
            'note'               => $note,
            'changed_at'         => now(),
        ]);
    }
}
