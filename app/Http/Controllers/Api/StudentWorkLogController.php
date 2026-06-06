<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\StudentWorkLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * บันทึกการปฏิบัติงานรายวันของนักศึกษา — เจ้าของเท่านั้น (scope user_id = auth)
 * รับ entries (กิจกรรม) + cases (ปัญหารายกรณี) แบบ nested ในคำขอเดียว
 */
class StudentWorkLogController extends Controller
{
    /** GET /api/student/work-logs */
    public function index(Request $request): JsonResponse
    {
        $logs = StudentWorkLog::where('user_id', $request->user()->id)
            ->withCount(['entries', 'cases'])
            ->withSum('entries as service_total', 'service_count')
            ->orderByDesc('work_date')
            ->orderByDesc('id')
            ->get();

        return response()->json(['data' => $logs]);
    }

    /** GET /api/student/work-logs/{id} */
    public function show(Request $request, int $id): JsonResponse
    {
        $log = StudentWorkLog::where('user_id', $request->user()->id)
            ->with(['entries', 'cases'])
            ->findOrFail($id);

        return response()->json(['data' => $log]);
    }

    /** POST /api/student/work-logs */
    public function store(Request $request): JsonResponse
    {
        $data = $this->validateData($request);

        $log = DB::transaction(function () use ($data, $request) {
            $log = StudentWorkLog::create([
                'user_id'             => $request->user()->id,
                'work_date'           => $data['work_date'],
                'time_start'          => $data['time_start'] ?? null,
                'time_end'            => $data['time_end'] ?? null,
                'registered_success'  => $data['registered_success'] ?? 0,
                'registered_fail'     => $data['registered_fail'] ?? 0,
                'supervisor_name'     => $data['supervisor_name'] ?? null,
                'supervisor_position' => $data['supervisor_position'] ?? null,
                'supervisor_date'     => $data['supervisor_date'] ?? null,
            ]);
            $this->syncChildren($log, $data, $request->user()->id);
            return $log;
        });

        return response()->json([
            'message' => 'บันทึกการปฏิบัติงานเรียบร้อย',
            'data'    => $log->load(['entries', 'cases']),
        ], 201);
    }

    /** PATCH /api/student/work-logs/{id} */
    public function update(Request $request, int $id): JsonResponse
    {
        $log = StudentWorkLog::where('user_id', $request->user()->id)->findOrFail($id);
        $data = $this->validateData($request);

        DB::transaction(function () use ($log, $data, $request) {
            $log->update([
                'work_date'           => $data['work_date'],
                'time_start'          => $data['time_start'] ?? null,
                'time_end'            => $data['time_end'] ?? null,
                'registered_success'  => $data['registered_success'] ?? 0,
                'registered_fail'     => $data['registered_fail'] ?? 0,
                'supervisor_name'     => $data['supervisor_name'] ?? null,
                'supervisor_position' => $data['supervisor_position'] ?? null,
                'supervisor_date'     => $data['supervisor_date'] ?? null,
            ]);
            // แทนที่ entries + cases ทั้งหมด (ลบของเก่า สร้างใหม่)
            $log->entries()->delete();
            $log->cases()->delete();
            $this->syncChildren($log, $data, $request->user()->id);
        });

        return response()->json([
            'message' => 'แก้ไขบันทึกเรียบร้อย',
            'data'    => $log->fresh()->load(['entries', 'cases']),
        ]);
    }

    /** DELETE /api/student/work-logs/{id} */
    public function destroy(Request $request, int $id): JsonResponse
    {
        $log = StudentWorkLog::where('user_id', $request->user()->id)->findOrFail($id);
        $log->delete();
        return response()->json(['message' => 'ลบบันทึกเรียบร้อย']);
    }

    private function validateData(Request $request): array
    {
        return $request->validate([
            'work_date'           => ['required', 'date'],
            'time_start'          => ['nullable', 'date_format:H:i'],
            'time_end'            => ['nullable', 'date_format:H:i'],
            'registered_success'  => ['nullable', 'integer', 'min:0'],
            'registered_fail'     => ['nullable', 'integer', 'min:0'],
            'supervisor_name'     => ['nullable', 'string', 'max:150'],
            'supervisor_position' => ['nullable', 'string', 'max:100'],
            'supervisor_date'     => ['nullable', 'date'],
            'entries'                   => ['array'],
            'entries.*.period'          => ['required_with:entries', 'string', 'max:20'],
            'entries.*.activity_type'   => ['required_with:entries', 'string', 'max:150'],
            'entries.*.detail'          => ['nullable', 'string'],
            'entries.*.service_count'   => ['nullable', 'integer', 'min:0'],
            'cases'                     => ['array'],
            'cases.*.full_name'         => ['required_with:cases', 'string', 'max:150'],
            'cases.*.phone'             => ['nullable', 'string', 'max:20'],
            'cases.*.village_tambon'    => ['nullable', 'string', 'max:200'],
            'cases.*.problem'           => ['required_with:cases', 'string'],
        ], [
            'work_date.required' => 'กรุณาระบุวันที่ปฏิบัติงาน',
        ]);
    }

    private function syncChildren(StudentWorkLog $log, array $data, int $userId): void
    {
        foreach (($data['entries'] ?? []) as $e) {
            $log->entries()->create([
                'period'        => $e['period'],
                'activity_type' => $e['activity_type'],
                'detail'        => $e['detail'] ?? null,
                'service_count' => $e['service_count'] ?? 0,
            ]);
        }
        foreach (($data['cases'] ?? []) as $c) {
            $log->cases()->create([
                'user_id'        => $userId,
                'full_name'      => $c['full_name'],
                'phone'          => $c['phone'] ?? null,
                'village_tambon' => $c['village_tambon'] ?? null,
                'problem'        => $c['problem'],
            ]);
        }
    }
}
