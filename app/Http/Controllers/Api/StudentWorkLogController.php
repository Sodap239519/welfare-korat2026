<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\StudentWorkFile;
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
            ->withCount(['entries', 'cases', 'files'])
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
            ->with(['entries', 'cases', 'files'])
            ->findOrFail($id);

        return response()->json(['data' => $log]);
    }

    /** POST /api/student/work-logs */
    public function store(Request $request): JsonResponse
    {
        $data = $this->validateData($request);

        $log = DB::transaction(function () use ($data, $request) {
            $log = StudentWorkLog::create($this->logFields($data) + ['user_id' => $request->user()->id]);
            $this->syncChildren($log, $data, $request->user()->id);
            return $log;
        });

        return response()->json([
            'message' => 'บันทึกการปฏิบัติงานเรียบร้อย',
            'data'    => $log->load(['entries', 'cases', 'files']),
        ], 201);
    }

    /** PATCH /api/student/work-logs/{id} */
    public function update(Request $request, int $id): JsonResponse
    {
        $log = StudentWorkLog::where('user_id', $request->user()->id)->findOrFail($id);
        $data = $this->validateData($request);

        DB::transaction(function () use ($log, $data, $request) {
            $log->update($this->logFields($data));
            // แทนที่ entries + cases ทั้งหมด (ลบของเก่า สร้างใหม่)
            $log->entries()->delete();
            $log->cases()->delete();
            $this->syncChildren($log, $data, $request->user()->id);
        });

        return response()->json([
            'message' => 'แก้ไขบันทึกเรียบร้อย',
            'data'    => $log->fresh()->load(['entries', 'cases', 'files']),
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
            'work_unit'           => ['nullable', 'string', 'max:200'],
            'registered_success'  => ['nullable', 'integer', 'min:0'],
            'registered_fail'     => ['nullable', 'integer', 'min:0'],
            'supervisor_name'     => ['nullable', 'string', 'max:150'],
            'supervisor_position' => ['nullable', 'string', 'max:100'],
            'supervisor_date'     => ['nullable', 'date'],
            // GPS — บังคับพิกัด (ยืนยันการลงพื้นที่จริง · ห้ามปักหมุดเอง)
            'lat'                 => ['required', 'numeric', 'between:-90,90'],
            'lng'                 => ['required', 'numeric', 'between:-180,180'],
            'location_accuracy'   => ['nullable', 'numeric', 'min:0'],
            'location_at'         => ['nullable', 'date'],
            'location_status'     => ['nullable', 'string', 'max:20'],
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
            'lat.required'       => 'ต้องอนุญาตการเข้าถึงตำแหน่ง (GPS) เพื่อยืนยันการลงพื้นที่',
            'lng.required'       => 'ต้องอนุญาตการเข้าถึงตำแหน่ง (GPS) เพื่อยืนยันการลงพื้นที่',
        ]);
    }

    /** ฟิลด์หลักของ log (ใช้ร่วม store/update) */
    private function logFields(array $data): array
    {
        $acc = $data['location_accuracy'] ?? null;
        $status = $data['location_status'] ?? (($acc !== null && $acc > 100) ? 'low_accuracy' : 'ok');
        return [
            'work_date'           => $data['work_date'],
            'time_start'          => $data['time_start'] ?? null,
            'time_end'            => $data['time_end'] ?? null,
            'work_unit'           => $data['work_unit'] ?? null,
            'registered_success'  => $data['registered_success'] ?? 0,
            'registered_fail'     => $data['registered_fail'] ?? 0,
            'supervisor_name'     => $data['supervisor_name'] ?? null,
            'supervisor_position' => $data['supervisor_position'] ?? null,
            'supervisor_date'     => $data['supervisor_date'] ?? null,
            'lat'                 => $data['lat'],
            'lng'                 => $data['lng'],
            'location_accuracy'   => $acc,
            'location_at'         => $data['location_at'] ?? now(),
            'location_status'     => $status,
        ];
    }

    /** POST /api/student/work-logs/{id}/files — อัปโหลดไฟล์แนบ (หลายไฟล์/หมวด) */
    public function uploadFiles(Request $request, int $id): JsonResponse
    {
        $log = StudentWorkLog::where('user_id', $request->user()->id)->findOrFail($id);

        $category = $request->input('category');
        abort_unless(in_array($category, ['worklog_doc', 'reimburse_doc', 'photo'], true), 422, 'หมวดไฟล์ไม่ถูกต้อง');

        $rules = $category === 'photo'
            // ใช้ mimes (ไม่ใช่ image) เพื่อรับ HEIC/HEIF ของ iPhone ที่ getimagesize ไม่รองรับ
            ? ['files.*' => ['required', 'file', 'mimes:jpg,jpeg,png,webp,gif,heic,heif', 'max:20480']]
            : ['files.*' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png,webp,heic,heif,doc,docx,xls,xlsx', 'max:20480']];
        $request->validate($rules + ['files' => ['required', 'array', 'max:10']], [
            'files.*.max'   => 'ไฟล์ใหญ่เกินกำหนด',
            'files.*.image' => 'ต้องเป็นไฟล์รูปภาพ',
            'files.*.mimes' => 'ชนิดไฟล์ไม่รองรับ',
        ]);

        // จำกัด ≤10 ไฟล์/หมวด
        $existing = $log->files()->where('category', $category)->count();
        $saved = [];
        foreach ($request->file('files') as $file) {
            if ($existing >= 10) break;
            $path = $file->store("student-files/{$request->user()->id}/{$log->id}", 'local');
            $saved[] = StudentWorkFile::create([
                'work_log_id'   => $log->id,
                'user_id'       => $request->user()->id,
                'category'      => $category,
                'original_name' => $file->getClientOriginalName(),
                'path'          => $path,
                'disk'          => 'local',
                'mime'          => $file->getMimeType(),
                'size'          => $file->getSize(),
                'lat'           => $request->input('lat'),
                'lng'           => $request->input('lng'),
            ]);
            $existing++;
        }

        return response()->json(['message' => 'อัปโหลดไฟล์เรียบร้อย', 'data' => $saved], 201);
    }

    /** DELETE /api/student/work-files/{id} — ลบไฟล์ (เจ้าของเท่านั้น) */
    public function deleteFile(Request $request, int $id): JsonResponse
    {
        $file = StudentWorkFile::where('user_id', $request->user()->id)->findOrFail($id);
        $file->delete();
        return response()->json(['message' => 'ลบไฟล์เรียบร้อย']);
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
