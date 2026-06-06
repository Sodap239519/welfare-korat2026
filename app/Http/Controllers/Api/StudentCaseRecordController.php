<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\StudentCaseRecord;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * ปัญหาการลงทะเบียนรายกรณี — เจ้าของเท่านั้น
 * ใช้เก็บกรณีปัญหาแบบสะสม (ไม่ผูกกับวันใดวันหนึ่งก็ได้)
 */
class StudentCaseRecordController extends Controller
{
    /** GET /api/student/case-records */
    public function index(Request $request): JsonResponse
    {
        $rows = StudentCaseRecord::where('user_id', $request->user()->id)
            ->orderByDesc('id')
            ->get();

        return response()->json(['data' => $rows]);
    }

    /** POST /api/student/case-records */
    public function store(Request $request): JsonResponse
    {
        $data = $this->validateData($request);
        $data['user_id'] = $request->user()->id;
        $row = StudentCaseRecord::create($data);

        return response()->json(['message' => 'บันทึกกรณีปัญหาเรียบร้อย', 'data' => $row], 201);
    }

    /** PATCH /api/student/case-records/{id} */
    public function update(Request $request, int $id): JsonResponse
    {
        $row = StudentCaseRecord::where('user_id', $request->user()->id)->findOrFail($id);
        $row->update($this->validateData($request));

        return response()->json(['message' => 'แก้ไขเรียบร้อย', 'data' => $row]);
    }

    /** DELETE /api/student/case-records/{id} */
    public function destroy(Request $request, int $id): JsonResponse
    {
        $row = StudentCaseRecord::where('user_id', $request->user()->id)->findOrFail($id);
        $row->delete();
        return response()->json(['message' => 'ลบเรียบร้อย']);
    }

    private function validateData(Request $request): array
    {
        return $request->validate([
            'work_log_id'    => ['nullable', 'integer', 'exists:student_work_logs,id'],
            'full_name'      => ['required', 'string', 'max:150'],
            'phone'          => ['nullable', 'string', 'max:20'],
            'village_tambon' => ['nullable', 'string', 'max:200'],
            'problem'        => ['required', 'string'],
        ], [
            'full_name.required' => 'กรุณากรอกชื่อ-สกุล',
            'problem.required'   => 'กรุณาระบุปัญหาที่เกิดขึ้น',
        ]);
    }
}
