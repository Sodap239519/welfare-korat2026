<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Channel;
use App\Models\DocumentBatch;
use App\Models\Target;
use App\Models\TargetCurrentStatus;
use App\Models\TargetStatusLog;
use App\Models\User;
use App\Notifications\BatchDistrictReceived;
use App\Notifications\BatchForwardedToBank;
use App\Notifications\BatchReceived;
use App\Notifications\BatchRecorded;
use App\Notifications\BatchRejected;
use App\Notifications\BatchSubmitted;
use Illuminate\Support\Facades\Notification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Document Batch — Path A (ผ่านอำเภอ) ใช้ batch
 *   Path B (walk-in) ไม่ใช้ batch — bank_staff อัปเดต target ตรงเป็น 4.2.7
 *
 * Lifecycle Path A (5 จุดยืนยัน):
 *   draft
 *     → submitted_to_district  (tracker กดส่ง)
 *     → district_received       (อำเภอยืนยันรับ)
 *     → forwarded_to_bank       (อำเภอกดส่งต่อ + เลือกธนาคารปลายทาง)
 *     → bank_received           (ธนาคารยืนยันรับ)
 *     → bank_recorded ✓         (ธนาคารยืนยันบันทึก)
 *     → rejected (ที่จุดใดก็ได้)
 *
 * Sub-status mapping ของ target ใน batch:
 *   add ลง batch                → 4.2.2 ผู้นำชุมชน/อปท. รับเอกสาร
 *   tracker submit              → 4.2.3 ส่งให้อำเภอแล้ว
 *   district receive            → 4.2.4 อำเภอรับเอกสาร
 *   district forward to bank    → 4.2.5 อำเภอส่งต่อให้ธนาคาร
 *   bank receive                → 4.2.6 ธนาคารรับเอกสาร (จากอำเภอ)
 *   bank record                 → 4.2.7 ธนาคารบันทึกข้อมูลลงระบบ ✓
 *   reject                      → null (กลับ tracker ตัดสินใจใหม่)
 */
class DocumentBatchController extends Controller
{
    private const SUB_TRACKER_RECEIVED   = '4.2.2';
    private const SUB_SENT_TO_DISTRICT   = '4.2.3';
    private const SUB_DISTRICT_RECEIVED  = '4.2.4';
    private const SUB_FORWARDED_TO_BANK  = '4.2.5';
    private const SUB_BANK_RECEIVED      = '4.2.6';
    private const SUB_BANK_RECORDED      = '4.2.7';
    private const SUB_WALKIN_BANK_RECV   = '4.2.1';   // walk-in (Path B)

    // backward-compat alias (used in some legacy code paths)
    private const SUB_SUBMITTED = self::SUB_TRACKER_RECEIVED;
    private const SUB_RECEIVED  = self::SUB_BANK_RECEIVED;
    private const SUB_RECORDED  = self::SUB_BANK_RECORDED;

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
            ->with([
                'tracker:id,name', 'channel:id,code,name', 'targetAmphur:id,name',
                'districtReceivedBy:id,name', 'forwardedToChannel:id,code,name',
                'receivedBy:id,name', 'recordedBy:id,name',
            ])
            ->withCount('targets')
            ->orderByDesc('batch_date')
            ->orderByDesc('id');

        // ─── scope ───
        $isSuperOrSysAdmin = $user->hasRole('super_admin');
        $isDistrictAdmin   = $user->hasRole('admin') && !$isSuperOrSysAdmin;
        $isBankStaff       = $user->hasRole('bank_staff') && !$user->hasAnyRole(['admin','super_admin']);

        if ($scope === 'mine') {
            $q->where('tracker_user_id', $user->id);
        } elseif ($scope === 'inbox') {
            // แต่ละ role เห็น batch รอ action ของตัวเอง
            if ($isBankStaff) {
                // ธนาคารเห็น batch ที่ forward มาถึงสาขา หรือรับแล้วยังไม่บันทึก — เฉพาะอำเภอที่ตนดูแล
                $q->whereIn('status', [DocumentBatch::ST_FORWARDED_TO_BANK, DocumentBatch::ST_BANK_RECEIVED])
                  ->where('forwarded_to_channel_id', $user->bank_channel_id)
                  ->where('forwarded_to_sub_channel', $user->bank_sub_channel)
                  ->when($user->amphur_id, fn ($w) => $w->where('target_amphur_id', $user->amphur_id));
            } elseif ($isDistrictAdmin) {
                // admin อำเภอเห็น batch ของอำเภอตัว ที่ส่งมาให้หรือรับแล้วยังไม่ส่งต่อ
                $q->whereIn('status', [DocumentBatch::ST_SUBMITTED_TO_DISTRICT, DocumentBatch::ST_DISTRICT_RECEIVED])
                  ->where('target_amphur_id', $user->amphur_id);
            } else {
                // super_admin: เห็นทุก batch ที่อยู่ในกระบวนการ
                $q->whereIn('status', [
                    DocumentBatch::ST_SUBMITTED_TO_DISTRICT,
                    DocumentBatch::ST_DISTRICT_RECEIVED,
                    DocumentBatch::ST_FORWARDED_TO_BANK,
                    DocumentBatch::ST_BANK_RECEIVED,
                ]);
            }
        }
        // 'all' = เห็นทั้งหมด · แต่ admin อำเภอ จำกัดเฉพาะอำเภอตัวเอง (+ ห่อที่ตัวเองสร้าง)
        if ($scope === 'all' && $isDistrictAdmin && $user->amphur_id) {
            $q->where(function ($w) use ($user) {
                $w->where('target_amphur_id', $user->amphur_id)
                  ->orWhere('tracker_user_id', $user->id);
            });
        }

        // ร่าง (draft) เห็นได้เฉพาะเจ้าของ + super_admin — อำเภอ/ธนาคารห้ามเห็น draft ของ tracker คนอื่น
        if (!$isSuperOrSysAdmin) {
            $q->where(function ($w) use ($user) {
                $w->where('status', '!=', DocumentBatch::ST_DRAFT)
                  ->orWhere('tracker_user_id', $user->id);
            });
        }

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
            'tracker:id,name,phone',
            'channel:id,code,name',
            'targetAmphur:id,name',
            'districtReceivedBy:id,name',
            'forwardedBy:id,name',
            'forwardedToChannel:id,code,name',
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
            // derive target_amphur_id จาก first target (อำเภอปลายทาง สำหรับ scope admin)
            $targetAmphurId = \DB::table('targets')->whereIn('id', $more['target_ids'])->value('amphur_id');

            $batch = DocumentBatch::create([
                'batch_no'         => DocumentBatch::generateBatchNo($base['batch_date']),
                'tracker_user_id'  => $user->id,
                'batch_date'       => $base['batch_date'],
                'channel_id'       => $base['channel_id'],
                'sub_channel'      => $base['sub_channel'] ?? null,
                'target_amphur_id' => $targetAmphurId,
                'status'           => DocumentBatch::ST_DRAFT,
                'submitter_role'   => $base['submitter_role'],
                'submitter_name'   => $base['submitter_name'] ?? null,
                'notes'            => $base['notes'] ?? null,
            ]);
            $this->attachTargetsAndAdvance($batch, $more['target_ids'], self::SUB_TRACKER_RECEIVED, $user, 'ผู้นำชุมชน/อปท. รับเอกสาร');
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

        $added = $this->attachTargetsAndAdvance($batch, $data['target_ids'], self::SUB_TRACKER_RECEIVED, $request->user(), 'ผู้นำชุมชน/อปท. รับเอกสาร');
        // ถ้า batch ยังไม่มี target_amphur_id → ตั้งจาก first target ที่เพิ่มเข้ามา
        if (!$batch->target_amphur_id && $batch->fresh()->targets()->count() > 0) {
            $firstAmphur = \DB::table('targets')->whereIn('id', $data['target_ids'])->value('amphur_id');
            if ($firstAmphur) $batch->update(['target_amphur_id' => $firstAmphur]);
        }
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

    /**
     * POST /api/batches/{id}/targets/{tid}/bounce
     *   อำเภอ/ธนาคาร ปฏิเสธรายคน (เอกสารไม่ตรง/เกินมา) → ตีกลับให้ tracker จัดการใหม่
     *   - เอาออกจาก batch · status คง 4.2 · sub_status → null (รอใส่ห่อรอบใหม่)
     *   - batch ที่เหลือเดินหน้าต่อได้ตามปกติ
     */
    public function bounceTarget(Request $request, int $id, int $tid): JsonResponse
    {
        $batch = DocumentBatch::findOrFail($id);
        $user  = $request->user();

        // ปฏิเสธรายคนได้เฉพาะช่วงที่ห่ออยู่ในมืออำเภอ/ธนาคาร (จุดที่ตรวจรับเอกสารจริง)
        $atDistrict = in_array($batch->status, [DocumentBatch::ST_SUBMITTED_TO_DISTRICT, DocumentBatch::ST_DISTRICT_RECEIVED], true);
        $atBank     = in_array($batch->status, [DocumentBatch::ST_FORWARDED_TO_BANK, DocumentBatch::ST_BANK_RECEIVED], true);

        if ($atDistrict) {
            $this->authorizeDistrictAction($batch, $user);
        } elseif ($atBank) {
            $this->authorizeBankAction($batch, $user);
        } else {
            return response()->json([
                'message' => 'ปฏิเสธรายคนได้เฉพาะตอนที่ห่ออยู่ระหว่างอำเภอ/ธนาคารตรวจรับ',
            ], 422);
        }

        $data = $request->validate([
            'reason' => ['required', 'string', 'max:500'],
        ], [
            'reason.required' => 'กรุณาระบุเหตุผลที่ปฏิเสธรายนี้',
        ]);

        if (!$batch->targets()->where('targets.id', $tid)->exists()) {
            return response()->json(['message' => 'ไม่พบรายนี้ใน batch'], 404);
        }

        DB::transaction(function () use ($batch, $tid, $user, $data) {
            $batch->targets()->detach($tid);
            // ตีกลับ tracker: status คง 4.2 · sub_status → null (รอจัดการใหม่)
            TargetCurrentStatus::where('target_id', $tid)->update([
                'sub_status_code' => null,
                'updated_by'      => $user->id,
                'updated_by_name' => $user->name,
                'updated_at'      => now(),
            ]);
            $this->logTargetEvent($tid, $user, null,
                'ปฏิเสธรายคนออกจาก batch #'.$batch->batch_no.' — '.$data['reason']);
        });

        return response()->json([
            'message' => 'ปฏิเสธรายนี้ออกจากห่อแล้ว — ตีกลับให้ผู้กำกับติดตามจัดการใหม่',
            'data'    => $batch->fresh()->loadCount('targets'),
        ]);
    }

    // ─────────────────────────────────────────────────────────────
    // LIFECYCLE — submit / receive / record / reject
    // ─────────────────────────────────────────────────────────────

    /**
     * POST /api/batches/{id}/submit
     *   - tracker เจ้าของ: draft → submitted_to_district (รออำเภอรับ)
     *   - อำเภอเจ้าของ (admin): ส่งตรงธนาคาร draft → forwarded_to_bank (ไม่ส่งให้ตัวเองรับ)
     */
    public function submit(Request $request, int $id): JsonResponse
    {
        $batch = DocumentBatch::findOrFail($id);
        $this->authorizeEdit($batch, $request->user());
        $this->assertStatus($batch, DocumentBatch::ST_DRAFT, 'ส่งได้เฉพาะ draft');

        if ($batch->targets()->count() === 0) {
            return response()->json(['message' => 'batch ว่าง — เพิ่มรายชื่อก่อนส่ง'], 422);
        }
        if (!$batch->target_amphur_id) {
            return response()->json(['message' => 'batch ไม่มีอำเภอปลายทาง — เพิ่ม target ก่อน'], 422);
        }

        $user  = $request->user();
        $owner = User::find($batch->tracker_user_id);
        // เจ้าของห่อเป็นอำเภอ (admin) → ส่งตรงธนาคาร ข้ามขั้นอำเภอรับ
        $ownerIsDistrict = $owner && $owner->hasRole('admin') && !$owner->hasRole('super_admin');

        if ($ownerIsDistrict) {
            if (!$batch->sub_channel) {
                return response()->json(['message' => 'ห่อนี้ยังไม่มีธนาคารปลายทาง — ระบุธนาคารก่อนส่ง'], 422);
            }
            DB::transaction(function () use ($batch, $user) {
                $now = now();
                $batch->update([
                    'status'                       => DocumentBatch::ST_FORWARDED_TO_BANK,
                    'submitted_at'                 => $now,
                    'district_received_at'         => $now,
                    'district_received_by_user_id' => $user->id,
                    'forwarded_at'                 => $now,
                    'forwarded_by_user_id'         => $user->id,
                    'forwarded_to_channel_id'      => $batch->channel_id,
                    'forwarded_to_sub_channel'     => strtolower($batch->sub_channel),
                ]);
                $this->advanceBatchTargets($batch, self::SUB_FORWARDED_TO_BANK, $user, 'อำเภอส่งตรงให้ธนาคาร batch #'.$batch->batch_no);
            });
            $this->notifyBatchForwardedToBank($batch->fresh());

            return response()->json([
                'message' => "ส่ง batch #{$batch->batch_no} ตรงให้ธนาคารแล้ว — รอธนาคารรับ",
                'data'    => $batch->fresh(),
            ]);
        }

        // tracker ปกติ → ส่งให้อำเภอ
        DB::transaction(function () use ($batch, $user) {
            $batch->update([
                'status'       => DocumentBatch::ST_SUBMITTED_TO_DISTRICT,
                'submitted_at' => now(),
            ]);
            // ทุกราย sub_status → 4.2.3 ส่งให้อำเภอแล้ว
            $this->advanceBatchTargets($batch, self::SUB_SENT_TO_DISTRICT, $user, 'ส่ง batch ให้อำเภอ #'.$batch->batch_no);
        });
        $this->notifyBatchSubmitted($batch->fresh());

        return response()->json([
            'message' => "ส่ง batch #{$batch->batch_no} ({$batch->targets()->count()} ราย) ให้อำเภอแล้ว — รออำเภอรับ",
            'data'    => $batch->fresh(),
        ]);
    }

    /** POST /api/batches/{id}/district-receive — admin อำเภอ: submitted_to_district → district_received */
    public function districtReceive(Request $request, int $id): JsonResponse
    {
        $batch = DocumentBatch::findOrFail($id);
        $this->authorizeDistrictAction($batch, $request->user());
        $this->assertStatus($batch, DocumentBatch::ST_SUBMITTED_TO_DISTRICT, 'อำเภอรับได้เฉพาะ batch ที่ tracker ส่งมา');

        $user = $request->user();
        DB::transaction(function () use ($batch, $user) {
            $batch->update([
                'status'                       => DocumentBatch::ST_DISTRICT_RECEIVED,
                'district_received_at'         => now(),
                'district_received_by_user_id' => $user->id,
            ]);
            $this->advanceBatchTargets($batch, self::SUB_DISTRICT_RECEIVED, $user, 'อำเภอรับ batch #'.$batch->batch_no);
        });
        $this->notifyBatchDistrictReceived($batch->fresh());

        return response()->json([
            'message' => "อำเภอรับ batch #{$batch->batch_no} ({$batch->targets_count} ราย) เรียบร้อย — กดส่งต่อธนาคารได้",
            'data'    => $batch->fresh(),
        ]);
    }

    /** POST /api/batches/{id}/district-forward — admin อำเภอ: district_received → forwarded_to_bank */
    public function districtForward(Request $request, int $id): JsonResponse
    {
        $batch = DocumentBatch::findOrFail($id);
        $this->authorizeDistrictAction($batch, $request->user());
        $this->assertStatus($batch, DocumentBatch::ST_DISTRICT_RECEIVED, 'ส่งต่อได้เฉพาะ batch ที่อำเภอรับแล้ว');

        $bankCodes = array_keys(\App\Models\Bank::optionsMap());
        $data = $request->validate([
            'forwarded_to_channel_id'  => ['required', 'integer', 'exists:channels,id'],
            'forwarded_to_sub_channel' => ['required', 'string', 'in:'.implode(',', array_map('strtolower', $bankCodes))],
        ]);

        $user = $request->user();
        DB::transaction(function () use ($batch, $user, $data) {
            $batch->update([
                'status'                  => DocumentBatch::ST_FORWARDED_TO_BANK,
                'forwarded_at'            => now(),
                'forwarded_by_user_id'    => $user->id,
                'forwarded_to_channel_id' => $data['forwarded_to_channel_id'],
                'forwarded_to_sub_channel'=> strtolower($data['forwarded_to_sub_channel']),
            ]);
            $this->advanceBatchTargets($batch, self::SUB_FORWARDED_TO_BANK, $user, 'อำเภอส่งต่อให้ธนาคาร batch #'.$batch->batch_no);
        });
        $this->notifyBatchForwardedToBank($batch->fresh());

        return response()->json([
            'message' => "ส่งต่อ batch #{$batch->batch_no} ให้ธนาคาร".strtoupper($data['forwarded_to_sub_channel'])." แล้ว",
            'data'    => $batch->fresh(),
        ]);
    }

    /**
     * POST /api/batches/{id}/undo-forward — อำเภอยกเลิกการส่งต่อ (ธนาคารยังไม่รับ)
     *   forwarded_to_bank → district_received · ล้างปลายทาง · พร้อมส่งใหม่
     */
    public function undoForward(Request $request, int $id): JsonResponse
    {
        $batch = DocumentBatch::findOrFail($id);
        $this->authorizeDistrictAction($batch, $request->user());
        $this->assertStatus($batch, DocumentBatch::ST_FORWARDED_TO_BANK, 'ยกเลิกได้เฉพาะ batch ที่ส่งต่อธนาคารแล้วและธนาคารยังไม่รับ');

        $user = $request->user();
        DB::transaction(function () use ($batch, $user) {
            $batch->update([
                'status'                   => DocumentBatch::ST_DISTRICT_RECEIVED,
                'forwarded_at'             => null,
                'forwarded_by_user_id'     => null,
                'forwarded_to_channel_id'  => null,
                'forwarded_to_sub_channel' => null,
            ]);
            // ขยับ target กลับสถานะ "อำเภอรับเอกสาร" (4.2.4)
            $this->advanceBatchTargets($batch, self::SUB_DISTRICT_RECEIVED, $user, 'อำเภอยกเลิกส่งต่อ batch #'.$batch->batch_no.' (ธนาคารยังไม่รับ)');
        });

        return response()->json([
            'message' => "ยกเลิกการส่งต่อ batch #{$batch->batch_no} แล้ว — กลับสู่สถานะอำเภอรับ พร้อมส่งใหม่",
            'data'    => $batch->fresh(),
        ]);
    }

    /** POST /api/batches/{id}/receive — bank/admin: forwarded_to_bank → bank_received */
    public function receive(Request $request, int $id): JsonResponse
    {
        $batch = DocumentBatch::findOrFail($id);
        $this->authorizeBankAction($batch, $request->user());
        $this->assertStatus($batch, DocumentBatch::ST_FORWARDED_TO_BANK, 'ธนาคารรับได้เฉพาะ batch ที่อำเภอส่งต่อมา');

        $user = $request->user();
        DB::transaction(function () use ($batch, $user) {
            $batch->update([
                'status'              => DocumentBatch::ST_BANK_RECEIVED,
                'received_at'         => now(),
                'received_by_user_id' => $user->id,
            ]);
            $this->advanceBatchTargets($batch, self::SUB_BANK_RECEIVED, $user, 'ธนาคารรับเอกสาร batch #'.$batch->batch_no);
        });
        $this->notifyBatchReceived($batch->fresh());

        return response()->json([
            'message' => "รับ batch #{$batch->batch_no} ({$batch->targets_count} ราย) เรียบร้อย",
            'data'    => $batch->fresh(),
        ]);
    }

    /** POST /api/batches/{id}/record — bank/admin: bank_received → bank_recorded */
    public function record(Request $request, int $id): JsonResponse
    {
        $batch = DocumentBatch::findOrFail($id);
        $this->authorizeBankAction($batch, $request->user());
        $this->assertStatus($batch, DocumentBatch::ST_BANK_RECEIVED, 'บันทึกได้เฉพาะที่รับมาแล้ว');

        $user = $request->user();
        DB::transaction(function () use ($batch, $user) {
            $batch->update([
                'status'              => DocumentBatch::ST_BANK_RECORDED,
                'recorded_at'         => now(),
                'recorded_by_user_id' => $user->id,
            ]);
            $this->advanceBatchTargets($batch, self::SUB_BANK_RECORDED, $user, 'ธนาคารบันทึก batch #'.$batch->batch_no);
        });
        $this->notifyBatchRecorded($batch->fresh());

        return response()->json([
            'message' => "บันทึก batch #{$batch->batch_no} ({$batch->targets_count} ราย) ครบแล้ว",
            'data'    => $batch->fresh(),
        ]);
    }

    /** POST /api/batches/{id}/reject — district/bank/admin ปฏิเสธ ทุกจุดยกเว้น draft/recorded/rejected */
    public function reject(Request $request, int $id): JsonResponse
    {
        $batch = DocumentBatch::findOrFail($id);
        $user = $request->user();

        // ใครปฏิเสธได้ตามสถานะ
        $atDistrict = in_array($batch->status, [DocumentBatch::ST_SUBMITTED_TO_DISTRICT, DocumentBatch::ST_DISTRICT_RECEIVED]);
        $atBank     = in_array($batch->status, [DocumentBatch::ST_FORWARDED_TO_BANK, DocumentBatch::ST_BANK_RECEIVED]);

        if ($atDistrict) $this->authorizeDistrictAction($batch, $user);
        elseif ($atBank) $this->authorizeBankAction($batch, $user);
        else return response()->json(['message' => 'ปฏิเสธไม่ได้ — สถานะปัจจุบัน: '.$batch->status], 422);

        $data = $request->validate([
            'reject_reason' => ['required', 'string', 'min:5', 'max:500'],
        ]);

        DB::transaction(function () use ($batch, $data, $user) {
            $batch->update([
                'status'        => DocumentBatch::ST_REJECTED,
                'reject_reason' => $data['reject_reason'],
            ]);
            $this->rollbackTargetsSubStatus($batch, $user, 'batch ถูกปฏิเสธ: '.$data['reject_reason']);
        });
        $this->notifyBatchRejected($batch->fresh());

        return response()->json([
            'message' => "ปฏิเสธ batch #{$batch->batch_no} แล้ว · tracker รับแจ้ง",
            'data'    => $batch->fresh(),
        ]);
    }

    /**
     * POST /api/batches/walkin-record — bank_staff: bulk mark 4.2.7 walk-in (Path B)
     *   ไม่สร้าง batch · target อัปเดต status_code=4.2 + sub_status_code=4.2.7 ทันที
     */
    public function walkInBulkRecord(Request $request): JsonResponse
    {
        $user = $request->user();
        if (!$user->hasRole('bank_staff') && !$user->hasAnyRole(['admin','super_admin'])) {
            abort(403, 'เฉพาะเจ้าหน้าที่ธนาคารหรือ admin เท่านั้น');
        }

        $data = $request->validate([
            'target_ids'   => ['required', 'array', 'min:1', 'max:500'],
            'target_ids.*' => ['integer', 'exists:targets,id'],
            'note'         => ['nullable', 'string', 'max:500'],
        ], ['target_ids.max' => 'อัปเดต walk-in ได้สูงสุด 500 รายต่อครั้ง']);

        $now = now();
        $bankChannelId  = $user->bank_channel_id;
        $bankSubChannel = $user->bank_sub_channel;
        // ถ้า admin/super_admin → ใช้ channel เริ่มต้น (bank)
        if (!$bankChannelId) {
            $bankCh = \App\Models\Channel::where('code', 'bank')->first();
            $bankChannelId = $bankCh?->id;
            $bankSubChannel = $bankSubChannel ?: 'ktb';   // fallback default
        }

        DB::transaction(function () use ($data, $user, $now, $bankChannelId, $bankSubChannel) {
            foreach ($data['target_ids'] as $tid) {
                TargetCurrentStatus::updateOrCreate(
                    ['target_id' => $tid],
                    [
                        'status_code'     => '4.2',
                        'sub_status_code' => self::SUB_BANK_RECORDED,  // 4.2.7 จบ
                        'channel_id'      => $bankChannelId,
                        'sub_channel'     => $bankSubChannel,
                        'note'            => $data['note'] ?? 'Walk-in · ธนาคารบันทึกตรง',
                        'updated_by'      => $user->id,
                        'updated_by_name' => $user->name,
                        'updated_at'      => $now,
                    ]
                );
                TargetStatusLog::create([
                    'target_id'          => $tid,
                    'status_code'        => '4.2',
                    'sub_status_code'    => self::SUB_BANK_RECORDED,
                    'channel_id'         => $bankChannelId,
                    'sub_channel'        => $bankSubChannel,
                    'note'               => 'Walk-in · ' . ($data['note'] ?? 'ธนาคารบันทึกตรง'),
                    'user_id'            => $user->id,
                    'user_name_snapshot' => $user->name,
                    'changed_at'         => $now,
                ]);
            }
        });

        $count = count($data['target_ids']);
        return response()->json([
            'message' => "บันทึก walk-in {$count} ราย เรียบร้อย",
            'count'   => $count,
        ]);
    }

    // ─────────────────────────────────────────────────────────────
    // SUMMARY (for dashboard preview — full dashboard ใน Phase E)
    // ─────────────────────────────────────────────────────────────

    /** GET /api/batches/summary — KPI สำหรับ HERO ของหน้า Batches list */
    public function summary(Request $request): JsonResponse
    {
        $today = today();
        $base = $this->scopedQuery($request->user());

        return response()->json([
            'submitted_today'   => (clone $base)->whereDate('submitted_at', $today)->count(),
            'pending_district'  => (clone $base)->where('status', DocumentBatch::ST_SUBMITTED_TO_DISTRICT)->count(),
            'pending_forward'   => (clone $base)->where('status', DocumentBatch::ST_DISTRICT_RECEIVED)->count(),
            'pending_bank_recv' => (clone $base)->where('status', DocumentBatch::ST_FORWARDED_TO_BANK)->count(),
            'pending_record'    => (clone $base)->where('status', DocumentBatch::ST_BANK_RECEIVED)->count(),
            'recorded_today'    => (clone $base)->whereDate('recorded_at', $today)->count(),
            'rejected_today'    => (clone $base)->whereDate('updated_at', $today)
                                       ->where('status', DocumentBatch::ST_REJECTED)->count(),
            // backward-compat (frontend ใช้อยู่)
            'pending_receive'   => (clone $base)->where('status', DocumentBatch::ST_FORWARDED_TO_BANK)->count(),
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

        // ─── 1) KPI — รองรับ 5-step lifecycle ───
        $kpi = [
            'submitted_today'   => (clone $base)->whereDate('submitted_at', $today)->count(),
            'pending_district'  => (clone $base)->where('status', DocumentBatch::ST_SUBMITTED_TO_DISTRICT)->count(),
            'pending_forward'   => (clone $base)->where('status', DocumentBatch::ST_DISTRICT_RECEIVED)->count(),
            'pending_bank_recv' => (clone $base)->where('status', DocumentBatch::ST_FORWARDED_TO_BANK)->count(),
            'pending_record'    => (clone $base)->where('status', DocumentBatch::ST_BANK_RECEIVED)->count(),
            'recorded_today'    => (clone $base)->whereDate('recorded_at', $today)->count(),
            'rejected_today'    => (clone $base)->whereDate('updated_at', $today)
                                       ->where('status', DocumentBatch::ST_REJECTED)->count(),
            // backward-compat
            'pending_receive'   => (clone $base)->where('status', DocumentBatch::ST_FORWARDED_TO_BANK)->count(),
        ];

        // ─── 2) Bottleneck — ค้างเกิน 3 วันในกระบวนการ ───
        $threeDaysAgo = now()->subDays(3);
        $bottleneck = (clone $base)
            ->with(['tracker:id,name', 'channel:id,code,name', 'targetAmphur:id,name'])
            ->withCount('targets')
            ->whereIn('status', [
                DocumentBatch::ST_SUBMITTED_TO_DISTRICT,
                DocumentBatch::ST_DISTRICT_RECEIVED,
                DocumentBatch::ST_FORWARDED_TO_BANK,
                DocumentBatch::ST_BANK_RECEIVED,
            ])
            ->where(function ($q) use ($threeDaysAgo) {
                $q->where(function ($q2) use ($threeDaysAgo) {
                    $q2->where('status', DocumentBatch::ST_SUBMITTED_TO_DISTRICT)
                       ->where('submitted_at', '<', $threeDaysAgo);
                })->orWhere(function ($q2) use ($threeDaysAgo) {
                    $q2->where('status', DocumentBatch::ST_DISTRICT_RECEIVED)
                       ->where('district_received_at', '<', $threeDaysAgo);
                })->orWhere(function ($q2) use ($threeDaysAgo) {
                    $q2->where('status', DocumentBatch::ST_FORWARDED_TO_BANK)
                       ->where('forwarded_at', '<', $threeDaysAgo);
                })->orWhere(function ($q2) use ($threeDaysAgo) {
                    $q2->where('status', DocumentBatch::ST_BANK_RECEIVED)
                       ->where('received_at', '<', $threeDaysAgo);
                });
            })
            ->orderByRaw('LEAST(IFNULL(submitted_at, "9999-12-31"), IFNULL(district_received_at, "9999-12-31"), IFNULL(forwarded_at, "9999-12-31"), IFNULL(received_at, "9999-12-31")) ASC')
            ->limit(15)
            ->get()
            ->map(function ($b) {
                $stuckSince = match ($b->status) {
                    DocumentBatch::ST_SUBMITTED_TO_DISTRICT => $b->submitted_at,
                    DocumentBatch::ST_DISTRICT_RECEIVED     => $b->district_received_at,
                    DocumentBatch::ST_FORWARDED_TO_BANK     => $b->forwarded_at,
                    DocumentBatch::ST_BANK_RECEIVED         => $b->received_at,
                    default => $b->submitted_at,
                };
                return [
                    'id'             => $b->id,
                    'batch_no'       => $b->batch_no,
                    'tracker_name'   => $b->tracker?->name ?? '—',
                    'amphur_name'    => $b->targetAmphur?->name ?? '—',
                    'channel_name'   => $b->forwardedToChannel?->name ?? $b->channel?->name ?? '—',
                    'sub_channel'    => strtoupper((string) ($b->forwarded_to_sub_channel ?: $b->sub_channel)),
                    'status'         => $b->status,
                    'targets_count'  => $b->targets_count,
                    'days_stuck'     => $stuckSince ? (int) round(abs(now()->diffInHours($stuckSince)) / 24) : 0,
                ];
            });

        // ─── 3) Bank Leaderboard — เร็วสุด (avg ส่ง→บันทึก) ใช้ forwarded_to_* เพราะเป็นธนาคารปลายทาง ───
        $bankBoard = (clone $base)
            ->where('status', DocumentBatch::ST_BANK_RECORDED)
            ->whereNotNull('forwarded_at')->whereNotNull('recorded_at')
            ->whereNotNull('forwarded_to_channel_id')
            ->selectRaw('forwarded_to_channel_id AS channel_id, forwarded_to_sub_channel AS sub_channel,
                         COUNT(*) as total_batches,
                         AVG(TIMESTAMPDIFF(HOUR, forwarded_at, recorded_at)) / 24 as avg_days')
            ->groupBy('forwarded_to_channel_id', 'forwarded_to_sub_channel')
            ->having('total_batches', '>', 0)
            ->orderBy('avg_days', 'ASC')
            ->limit(5)
            ->get();

        $channelMap = \App\Models\Channel::pluck('name', 'id');
        $bankMap = \App\Models\Bank::optionsMap();
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

    /** Helper: scope query ตาม role */
    private function scopedQuery(User $user): \Illuminate\Database\Eloquent\Builder
    {
        $q = DocumentBatch::query();
        if ($user->hasRole('super_admin')) return $q;
        // district admin → กรองเฉพาะอำเภอตัว
        if ($user->hasRole('admin') && $user->amphur_id) {
            $q->where('target_amphur_id', $user->amphur_id);
        }
        // bank_staff → กรองเฉพาะ batch ที่ forward มาธนาคารตัว
        elseif ($user->hasRole('bank_staff') && !$user->hasAnyRole(['admin', 'super_admin'])) {
            $q->where('forwarded_to_channel_id', $user->bank_channel_id)
              ->where('forwarded_to_sub_channel', $user->bank_sub_channel);
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
        // tracker ส่งให้อำเภอ → notify admin ของอำเภอนั้น
        $districtAdmins = User::role('admin')->where('amphur_id', $batch->target_amphur_id)->get();
        $this->notifyBatchEvent($batch, BatchSubmitted::class, $districtAdmins->all());
    }

    private function notifyBatchDistrictReceived(DocumentBatch $batch): void
    {
        // อำเภอรับ → notify tracker เจ้าของ batch
        $tracker = User::find($batch->tracker_user_id);
        $this->notifyBatchEvent($batch, BatchDistrictReceived::class, $tracker ? [$tracker] : []);
    }

    private function notifyBatchForwardedToBank(DocumentBatch $batch): void
    {
        // อำเภอส่งต่อ → notify bank_staff ของธนาคารปลายทาง (อำเภอนั้น) + tracker
        $bankStaff = User::role('bank_staff')
            ->where('bank_channel_id', $batch->forwarded_to_channel_id)
            ->where('bank_sub_channel', $batch->forwarded_to_sub_channel)
            ->where(fn ($w) => $w->whereNull('amphur_id')->orWhere('amphur_id', $batch->target_amphur_id))
            ->get();
        $tracker = User::find($batch->tracker_user_id);
        $recipients = $bankStaff->all();
        if ($tracker) $recipients[] = $tracker;
        $this->notifyBatchEvent($batch, BatchForwardedToBank::class, $recipients);
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
        if ($user->hasRole('super_admin')) return 'all';
        // district admin → inbox (ของอำเภอตัว) · global admin → all
        if ($user->hasRole('admin')) return $user->amphur_id ? 'inbox' : 'all';
        if ($user->hasRole('bank_staff')) return 'inbox';
        return 'mine';   // tracker
    }

    private function authorizeView(DocumentBatch $batch, User $user): void
    {
        if ($user->hasRole('super_admin')) return;
        // global admin (no amphur scope) เห็นทุก batch
        if ($user->hasRole('admin') && !$user->amphur_id) return;
        // district admin เห็น batch ของอำเภอตัว
        if ($user->hasRole('admin') && $batch->target_amphur_id === $user->amphur_id) return;
        // tracker เห็นของตัว
        if ($batch->tracker_user_id === $user->id) return;
        // bank_staff: เห็น batch ที่ forward มาธนาคารตัว + อำเภอที่ตนดูแล (รวม recorded/rejected ที่จบแล้ว)
        if ($user->hasRole('bank_staff')
            && $batch->forwarded_to_channel_id === $user->bank_channel_id
            && $batch->forwarded_to_sub_channel === $user->bank_sub_channel
            && (!$user->amphur_id || $batch->target_amphur_id === $user->amphur_id)) return;
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
        if ($user->hasRole('super_admin')) return;
        // admin (district) ถ้า batch.target_amphur_id ตรงกับอำเภอตัว → ทำแทนได้
        if ($user->hasRole('admin') && !$user->amphur_id) return;  // admin ที่ไม่ผูกอำเภอ = global admin
        if ($user->hasRole('admin') && $batch->target_amphur_id === $user->amphur_id) return;
        // bank_staff ต้องตรง forwarded_to_channel/sub_channel + อำเภอที่ตนดูแล
        if ($user->hasRole('bank_staff')
            && $batch->forwarded_to_channel_id === $user->bank_channel_id
            && $batch->forwarded_to_sub_channel === $user->bank_sub_channel
            && (!$user->amphur_id || $batch->target_amphur_id === $user->amphur_id)) return;
        abort(403, 'เฉพาะเจ้าหน้าที่ธนาคารปลายทาง (อำเภอที่ดูแล) เท่านั้น');
    }

    /** อำเภอ (admin ที่ผูก amphur_id ตรง batch.target_amphur_id) — super_admin/admin ทั่วไปทำได้ทุกอำเภอ */
    private function authorizeDistrictAction(DocumentBatch $batch, User $user): void
    {
        if ($user->hasRole('super_admin')) return;
        if ($user->hasRole('admin') && !$user->amphur_id) return;  // global admin
        if ($user->hasRole('admin') && $batch->target_amphur_id === $user->amphur_id) return;
        abort(403, 'เฉพาะ admin ของอำเภอปลายทางเท่านั้น');
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
