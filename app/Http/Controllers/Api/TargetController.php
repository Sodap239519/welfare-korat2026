<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Channel;
use App\Models\RegistrationStatus;
use App\Models\Target;
use App\Models\TargetCurrentStatus;
use App\Models\TargetStatusLog;
use App\Models\Tracker;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TargetController extends Controller
{
    /** GET /api/targets — paginated list with filters */
    public function index(Request $request): JsonResponse
    {
        $q = Target::query()
            ->select('targets.*')
            ->with(['village:id,name,moo,tambon_id', 'village.tambon:id,name,amphur_id', 'village.tambon.amphur:id,name'])
            ->where('targets.active', true);

        // Search by name
        if ($request->filled('q')) {
            $term = trim($request->q);
            $q->where(function ($w) use ($term) {
                $w->where('first_name', 'like', "%$term%")
                  ->orWhere('last_name', 'like', "%$term%");
            });
        }

        $q->when($request->filled('amphur_id'),  fn ($q) => $q->where('amphur_id',  (int) $request->amphur_id))
          ->when($request->filled('tambon_id'),  fn ($q) => $q->where('tambon_id',  (int) $request->tambon_id))
          ->when($request->filled('village_id'), fn ($q) => $q->where('village_id', (int) $request->village_id));

        // Filter by current status code
        if ($request->filled('status')) {
            $code = (string) $request->status;
            $q->leftJoin('target_current_status as tcs', 'tcs.target_id', '=', 'targets.id');
            if ($code === '0' || $code === 'none') {
                $q->whereNull('tcs.status_code');
            } else {
                $q->where('tcs.status_code', $code);
            }
        }

        $perPage = max(10, min((int) $request->input('per_page', 50), 200));
        $page = $q->orderBy('targets.id')->paginate($perPage)->withQueryString();

        // Attach current status to each
        $ids = collect($page->items())->pluck('id');
        $statuses = TargetCurrentStatus::whereIn('target_id', $ids)->get()->keyBy('target_id');

        $banks = config('banks.banks', []);
        $channelNames = Channel::pluck('name', 'id');

        $page->getCollection()->transform(function ($t) use ($statuses, $banks, $channelNames) {
            $cs = $statuses->get($t->id);
            $sub = $cs?->sub_channel;
            return [
                'id'         => $t->id,
                'name'       => trim(($t->prefix ?? '').' '.$t->first_name.' '.($t->last_name ?? '')),
                'first_name' => $t->first_name,
                'last_name'  => $t->last_name,
                'poverty_level' => $t->poverty_level,
                'annual_income' => $t->annual_income,
                'has_old_welfare' => (bool) $t->has_old_welfare,
                'year'       => $t->year,
                'village'    => $t->village?->name,
                'moo'        => $t->village?->moo,
                'tambon'     => $t->village?->tambon?->name,
                'amphur'     => $t->village?->tambon?->amphur?->name,
                'status_code'  => $cs?->status_code,
                'channel_id'   => $cs?->channel_id,
                'channel'      => $cs?->channel_id ? ($channelNames[$cs->channel_id] ?? null) : null,
                'sub_channel'  => $sub,
                'sub_channel_label' => $sub ? ($banks[$sub] ?? $sub) : null,
                'note'         => $cs?->note,
                'updated_at'   => $cs?->updated_at,
            ];
        });

        return response()->json($page);
    }

    /** GET /api/targets/{id} — detail + tracker + status history */
    public function show(int $id): JsonResponse
    {
        $t = Target::with([
                'village:id,name,moo,tambon_id',
                'village.tambon:id,name,amphur_id',
                'village.tambon.amphur:id,name',
                'household:id,address_no,village_id',
                'currentStatus',
                'currentStatus.channel:id,name',
                'currentStatus.updater:id,name',
            ])
            ->findOrFail($id);

        $banks = config('banks.banks', []);

        $logs = TargetStatusLog::with(['channel:id,name', 'user:id,name'])
            ->where('target_id', $id)
            ->orderByDesc('changed_at')
            ->limit(50)
            ->get()
            ->map(fn ($l) => [
                'id'                => $l->id,
                'status_code'       => $l->status_code,
                'channel'           => $l->channel?->name,
                'sub_channel'       => $l->sub_channel,
                'sub_channel_label' => $l->sub_channel ? ($banks[$l->sub_channel] ?? $l->sub_channel) : null,
                'note'              => $l->note,
                'user'              => $l->user?->name,
                'changed_at'        => $l->changed_at?->toIso8601String(),
            ]);

        // Tracker of this target's village (first active)
        $tracker = Tracker::where('village_id', $t->village_id)
            ->where('active', true)
            ->orderBy('id')
            ->first();

        return response()->json([
            'id'         => $t->id,
            'name'       => trim(($t->prefix ?? '').' '.$t->first_name.' '.($t->last_name ?? '')),
            'prefix'     => $t->prefix,
            'first_name' => $t->first_name,
            'last_name'  => $t->last_name,
            'member_seq' => $t->member_seq,
            'year'       => $t->year,
            'poverty_level' => $t->poverty_level,
            'annual_income' => $t->annual_income,
            'has_old_welfare' => (bool) $t->has_old_welfare,
            'address_no' => $t->household?->address_no,
            'village'    => $t->village?->name,
            'moo'        => $t->village?->moo,
            'tambon'     => $t->village?->tambon?->name,
            'amphur'     => $t->village?->tambon?->amphur?->name,
            'current'    => $t->currentStatus ? [
                'status_code'       => $t->currentStatus->status_code,
                'channel_id'        => $t->currentStatus->channel_id,
                'channel'           => $t->currentStatus->channel?->name,
                'sub_channel'       => $t->currentStatus->sub_channel,
                'sub_channel_label' => $t->currentStatus->sub_channel ? ($banks[$t->currentStatus->sub_channel] ?? $t->currentStatus->sub_channel) : null,
                'note'              => $t->currentStatus->note,
                'updated_by'        => $t->currentStatus->updater?->name,
                'updated_at'        => $t->currentStatus->updated_at?->toIso8601String(),
            ] : null,
            'logs' => $logs,
            'tracker' => $tracker ? [
                'name'     => $tracker->full_name,
                'position' => $tracker->position,
                'phone'    => $tracker->phone,
            ] : null,
        ]);
    }

    /** POST /api/targets/bulk-status — อัปเดตหลายคนพร้อมกัน */
    public function bulkUpdateStatus(Request $request): JsonResponse
    {
        $statusCodes = RegistrationStatus::pluck('code')->all();
        $channelIds  = Channel::pluck('id')->all();
        $bankCodes   = array_keys(config('banks.banks', []));

        $data = $request->validate([
            'target_ids'   => ['required', 'array', 'min:1', 'max:500'],
            'target_ids.*' => ['integer', 'exists:targets,id'],
            'status_code'  => ['required', 'string', 'in:'.implode(',', $statusCodes)],
            'channel_id'   => ['nullable', 'integer', 'in:'.implode(',', $channelIds)],
            'sub_channel'  => ['nullable', 'string', 'max:20'],
            'note'         => ['nullable', 'string', 'max:500'],
        ], [
            'target_ids.max' => 'อัปเดตได้สูงสุด 500 รายการต่อครั้ง',
        ]);

        $rs = RegistrationStatus::where('code', $data['status_code'])->first();
        if ($rs->requires_note && empty($data['note'])) {
            return response()->json(['message' => 'สถานะนี้ต้องระบุหมายเหตุ',
                'errors' => ['note' => ['สถานะ '.$rs->code.' ต้องระบุหมายเหตุ']]], 422);
        }
        if ($rs->requires_channel && empty($data['channel_id'])) {
            return response()->json(['message' => 'สถานะนี้ต้องเลือกช่องทาง',
                'errors' => ['channel_id' => ['สถานะ '.$rs->code.' ต้องเลือกช่องทาง']]], 422);
        }

        $selectedChannel = $data['channel_id'] ? Channel::find($data['channel_id']) : null;
        $isBankChannel = $selectedChannel && $selectedChannel->code === 'bank';
        if ($isBankChannel) {
            if (empty($data['sub_channel'])) {
                return response()->json(['message' => 'ช่องทางธนาคาร ต้องเลือกธนาคารย่อย',
                    'errors' => ['sub_channel' => ['กรุณาเลือกธนาคารที่ใช้ลงทะเบียน']]], 422);
            }
            if (!in_array($data['sub_channel'], $bankCodes, true)) {
                return response()->json(['message' => 'รหัสธนาคารไม่ถูกต้อง',
                    'errors' => ['sub_channel' => ['รหัสธนาคารต้องเป็น: '.implode(', ', $bankCodes)]]], 422);
            }
        } else {
            $data['sub_channel'] = null;
        }

        $userId = $request->user()->id;
        $now = now();
        $logsBatch = [];
        $count = 0;

        DB::transaction(function () use ($data, $userId, $now, &$logsBatch, &$count) {
            foreach ($data['target_ids'] as $tid) {
                $logsBatch[] = [
                    'target_id'   => $tid,
                    'status_code' => $data['status_code'],
                    'channel_id'  => $data['channel_id'] ?? null,
                    'sub_channel' => $data['sub_channel'] ?? null,
                    'note'        => $data['note'] ?? null,
                    'user_id'     => $userId,
                    'changed_at'  => $now,
                    'created_at'  => $now,
                    'updated_at'  => $now,
                ];
                TargetCurrentStatus::updateOrCreate(
                    ['target_id' => $tid],
                    [
                        'status_code' => $data['status_code'],
                        'channel_id'  => $data['channel_id'] ?? null,
                        'sub_channel' => $data['sub_channel'] ?? null,
                        'note'        => $data['note'] ?? null,
                        'updated_by'  => $userId,
                        'updated_at'  => $now,
                    ]
                );
                $count++;
            }
            // Bulk insert logs (เร็วกว่า loop create)
            foreach (array_chunk($logsBatch, 200) as $chunk) {
                TargetStatusLog::insert($chunk);
            }
        });

        return response()->json([
            'message' => "อัปเดตสถานะ {$count} ราย เป็น \"{$rs->label}\" เรียบร้อย",
            'updated' => $count,
        ]);
    }

    /** PATCH /api/targets/{id}/status */
    public function updateStatus(Request $request, int $id): JsonResponse
    {
        $statusCodes = RegistrationStatus::pluck('code')->all();
        $channelIds  = Channel::pluck('id')->all();
        $bankCodes   = array_keys(config('banks.banks', []));

        $data = $request->validate([
            'status_code' => ['required', 'string', 'in:'.implode(',', $statusCodes)],
            'channel_id'  => ['nullable', 'integer', 'in:'.implode(',', $channelIds)],
            'sub_channel' => ['nullable', 'string', 'max:20'],
            'note'        => ['nullable', 'string', 'max:500'],
        ]);

        $rs = RegistrationStatus::where('code', $data['status_code'])->first();
        if ($rs->requires_note && empty($data['note'])) {
            return response()->json([
                'message' => 'สถานะนี้ต้องระบุหมายเหตุ',
                'errors'  => ['note' => ['สถานะ '.$rs->code.' ต้องระบุหมายเหตุ']],
            ], 422);
        }
        if ($rs->requires_channel && empty($data['channel_id'])) {
            return response()->json([
                'message' => 'สถานะนี้ต้องเลือกช่องทาง',
                'errors'  => ['channel_id' => ['สถานะ '.$rs->code.' ต้องเลือกช่องทาง']],
            ], 422);
        }

        // ถ้าช่องทาง = ธนาคารรับลงทะเบียน (code='bank') → ต้องเลือกธนาคารย่อย
        $selectedChannel = $data['channel_id'] ? Channel::find($data['channel_id']) : null;
        $isBankChannel = $selectedChannel && $selectedChannel->code === 'bank';

        if ($isBankChannel) {
            if (empty($data['sub_channel'])) {
                return response()->json([
                    'message' => 'ช่องทางธนาคาร ต้องเลือกธนาคารที่ใช้ลงทะเบียน',
                    'errors'  => ['sub_channel' => ['กรุณาเลือกธนาคารที่ใช้ลงทะเบียน']],
                ], 422);
            }
            if (!in_array($data['sub_channel'], $bankCodes, true)) {
                return response()->json([
                    'message' => 'รหัสธนาคารไม่ถูกต้อง',
                    'errors'  => ['sub_channel' => ['รหัสธนาคารต้องเป็น: '.implode(', ', $bankCodes)]],
                ], 422);
            }
        } else {
            // ถ้าไม่ใช่ช่องทางธนาคาร — ล้าง sub_channel ออก
            $data['sub_channel'] = null;
        }

        $target = Target::findOrFail($id);
        $userId = $request->user()->id;

        DB::transaction(function () use ($target, $data, $userId) {
            TargetStatusLog::create([
                'target_id'   => $target->id,
                'status_code' => $data['status_code'],
                'channel_id'  => $data['channel_id'] ?? null,
                'sub_channel' => $data['sub_channel'] ?? null,
                'note'        => $data['note'] ?? null,
                'user_id'     => $userId,
                'changed_at'  => now(),
            ]);

            TargetCurrentStatus::updateOrCreate(
                ['target_id' => $target->id],
                [
                    'status_code' => $data['status_code'],
                    'channel_id'  => $data['channel_id'] ?? null,
                    'sub_channel' => $data['sub_channel'] ?? null,
                    'note'        => $data['note'] ?? null,
                    'updated_by'  => $userId,
                    'updated_at'  => now(),
                ]
            );
        });

        return $this->show($id);
    }
}
