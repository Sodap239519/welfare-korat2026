<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\StudentSelfAssessment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * การประเมินตนเองของนักศึกษา — 1 ระเบียน/คน (upsert)
 */
class StudentSelfAssessmentController extends Controller
{
    /** GET /api/student/self-assessment */
    public function show(Request $request): JsonResponse
    {
        $row = StudentSelfAssessment::where('user_id', $request->user()->id)->first();
        return response()->json(['data' => $row]);
    }

    /** PUT /api/student/self-assessment — สร้างหรือแก้ไข */
    public function upsert(Request $request): JsonResponse
    {
        $data = $request->validate([
            'understanding_level' => ['nullable', 'in:มาก,ปานกลาง,น้อย'],
            'skills'              => ['nullable', 'array'],
            'skills.*'            => ['string', 'max:100'],
            'people_groups'       => ['nullable', 'array'],
            'people_groups.*'     => ['string', 'max:100'],
            'lessons'             => ['nullable', 'string'],
            'tech_overall'        => ['nullable', 'string', 'max:20'],
            'dependency'          => ['nullable', 'string', 'max:30'],
            'problems'            => ['nullable', 'array'],
            'problems.*'          => ['string', 'max:100'],
            'problems_other'      => ['nullable', 'string', 'max:200'],
            'top_problem'         => ['nullable', 'string', 'max:255'],
            'student_role'        => ['nullable', 'string', 'max:30'],
            'total_people'        => ['nullable', 'integer', 'min:0'],
            'success_rate'        => ['nullable', 'string', 'max:20'],
            'without_help'        => ['nullable', 'string', 'max:30'],
        ]);

        $row = StudentSelfAssessment::updateOrCreate(
            ['user_id' => $request->user()->id],
            $data
        );

        return response()->json(['message' => 'บันทึกแบบประเมินตนเองเรียบร้อย', 'data' => $row]);
    }
}
