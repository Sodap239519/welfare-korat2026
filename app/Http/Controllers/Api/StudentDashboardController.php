<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\StudentSelfAssessment;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Dashboard งานนักศึกษา
 * - myStats()  → ของนักศึกษาคนนั้นเอง (role:student)
 * - overview() → ภาพรวมทุกนักศึกษา (super_admin/admin)
 * - compute()  → static · ReferenceController เรียกใช้สำหรับ public dashboard (cache)
 */
class StudentDashboardController extends Controller
{
    /** GET /api/student/my-dashboard */
    public function myStats(Request $request): JsonResponse
    {
        return response()->json(self::compute($request->user()->id));
    }

    /** GET /api/student-dashboard (super_admin/admin) */
    public function overview(): JsonResponse
    {
        return response()->json(self::compute(null));
    }

    /**
     * คำนวณสถิติ — $userId = null หมายถึงภาพรวมทุกคน
     */
    public static function compute(?int $userId = null): array
    {
        $isOne = $userId !== null;

        $logsBase = fn () => DB::table('student_work_logs')
            ->when($isOne, fn ($q) => $q->where('user_id', $userId));

        $entriesBase = fn () => DB::table('student_work_log_entries as e')
            ->join('student_work_logs as l', 'l.id', '=', 'e.work_log_id')
            ->when($isOne, fn ($q) => $q->where('l.user_id', $userId));

        $workDays     = (int) $logsBase()->count();
        $success      = (int) $logsBase()->sum('registered_success');
        $fail         = (int) $logsBase()->sum('registered_fail');
        $serviceTotal = (int) $entriesBase()->sum('e.service_count');
        $caseCount    = (int) DB::table('student_case_records')
            ->when($isOne, fn ($q) => $q->where('user_id', $userId))->count();

        // ใช้ whereHas แทน role() — ไม่ throw ถ้ายังไม่ได้ seed role 'student'
        $students = $isOne ? 1 : (int) User::query()
            ->whereHas('roles', fn ($r) => $r->where('name', 'student'))
            ->where('active', true)->count();

        $byActivity = $entriesBase()
            ->groupBy('e.activity_type')
            ->selectRaw('e.activity_type as label, SUM(e.service_count) as n')
            ->orderByDesc('n')->get();

        $trend = $entriesBase()
            ->groupBy('l.work_date')
            ->selectRaw('l.work_date as d, SUM(e.service_count) as n')
            ->orderBy('d')->get();

        // จำนวนนักศึกษาที่ใช้งาน (บันทึกงาน) ในแต่ละวัน — distinct user ต่อวัน
        $usageTrend = $logsBase()
            ->groupBy('work_date')
            ->selectRaw('work_date as d, COUNT(DISTINCT user_id) as n')
            ->orderBy('work_date')->get();

        // สรุปรายวัน (สำหรับออกรายงาน PDF) — แต่ละวัน: นักศึกษา / บันทึก / ผู้รับบริการ / สำเร็จ / ไม่สำเร็จ
        $dailyLogs = $logsBase()
            ->groupBy('work_date')
            ->selectRaw('work_date as d, COUNT(DISTINCT user_id) as students, COUNT(*) as logs, SUM(registered_success) as success, SUM(registered_fail) as fail')
            ->orderBy('work_date')->get();
        $dailyService = $entriesBase()
            ->groupBy('l.work_date')
            ->selectRaw('l.work_date as d, SUM(e.service_count) as service')
            ->get()->keyBy('d');
        $daily = $dailyLogs->map(fn ($r) => [
            'date'     => $r->d,
            'students' => (int) $r->students,
            'logs'     => (int) $r->logs,
            'service'  => (int) ($dailyService[$r->d]->service ?? 0),
            'success'  => (int) $r->success,
            'fail'     => (int) $r->fail,
        ])->values();

        $result = [
            'students'           => $students,
            'work_days'          => $workDays,
            'service_total'      => $serviceTotal,
            'registered_success' => $success,
            'registered_fail'    => $fail,
            'case_count'         => $caseCount,
            'by_activity' => [
                'labels' => $byActivity->pluck('label')->all(),
                'data'   => $byActivity->pluck('n')->map(fn ($x) => (int) $x)->all(),
            ],
            'trend' => [
                'labels' => $trend->pluck('d')->map(fn ($d) => \Carbon\Carbon::parse($d)->format('d/m'))->all(),
                'data'   => $trend->pluck('n')->map(fn ($x) => (int) $x)->all(),
            ],
            'usage_trend' => [
                'labels' => $usageTrend->pluck('d')->map(fn ($d) => \Carbon\Carbon::parse($d)->format('d/m'))->all(),
                'data'   => $usageTrend->pluck('n')->map(fn ($x) => (int) $x)->all(),
            ],
            'daily' => $daily,
            'as_of' => now()->toIso8601String(),
        ];

        if ($isOne) {
            $result['self_assessment_done'] = StudentSelfAssessment::where('user_id', $userId)->exists();
            return $result;
        }

        // ── ภาพรวม (overview/public) เท่านั้น ──
        $byUnit = User::query()
            ->whereHas('roles', fn ($r) => $r->where('name', 'student'))
            ->where('active', true)
            ->selectRaw('work_unit_type, COUNT(*) as n')
            ->groupBy('work_unit_type')->pluck('n', 'work_unit_type');

        $byAmphur = DB::table('student_work_logs as l')
            ->join('users as u', 'u.id', '=', 'l.user_id')
            ->leftJoin('amphurs as a', 'a.id', '=', 'u.amphur_id')
            ->groupBy('a.id', 'a.name')
            ->selectRaw("COALESCE(a.name, 'ธนาคาร/อื่นๆ') as label, SUM(l.registered_success) as n")
            ->orderByDesc('n')->get();

        $top = DB::table('student_work_logs as l')
            ->join('users as u', 'u.id', '=', 'l.user_id')
            ->leftJoin('student_work_log_entries as e', 'e.work_log_id', '=', 'l.id')
            ->groupBy('u.id', 'u.name', 'u.faculty')
            ->selectRaw('u.id, u.name, u.faculty, COALESCE(SUM(e.service_count), 0) as service, SUM(l.registered_success) as success, COUNT(DISTINCT l.id) as days')
            ->orderByDesc('service')->limit(20)->get();

        // นับปัญหาที่พบ จากแบบประเมินตนเอง (JSON array)
        $problemCount = [];
        foreach (StudentSelfAssessment::pluck('problems') as $arr) {
            foreach (($arr ?? []) as $p) {
                $problemCount[$p] = ($problemCount[$p] ?? 0) + 1;
            }
        }
        arsort($problemCount);

        $result['by_unit']   = ['amphur' => (int) ($byUnit['amphur'] ?? 0), 'bank' => (int) ($byUnit['bank'] ?? 0)];
        $result['by_amphur'] = [
            'labels' => $byAmphur->pluck('label')->all(),
            'data'   => $byAmphur->pluck('n')->map(fn ($x) => (int) $x)->all(),
        ];
        $result['top_students']     = $top->all();
        $result['problems']         = ['labels' => array_keys($problemCount), 'data' => array_values($problemCount)];
        $result['assessments_done'] = (int) StudentSelfAssessment::count();

        return $result;
    }
}
