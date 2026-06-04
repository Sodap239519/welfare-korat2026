<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\MissedTargetImport;
use App\Models\MissedTargetStat;
use App\Services\MissedTargetImportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class MissedTargetController extends Controller
{
    public function __construct(private MissedTargetImportService $service) {}

    /** GET /api/missed-targets/export?level=amphur|tambon — ส่งออก Excel */
    public function export(Request $request): BinaryFileResponse
    {
        $level = $request->input('level', 'amphur') === 'tambon' ? 'tambon' : 'amphur';
        $hasTambon = $level === 'tambon';
        $rows = MissedTargetStat::where('level', $level)->orderByDesc('cnt_total')->get();

        $headings = $hasTambon
            ? ['ลำดับ', 'อำเภอ', 'ตำบล', 'กลุ่มเปราะบาง + ไม่มีบัตร', 'ตกเกณฑ์ จปฐ. + ไม่มีบัตร', 'ทั้ง 3 กลุ่ม + ไม่มีบัตร', 'รวมทั้ง 3 กลุ่ม']
            : ['ลำดับ', 'อำเภอ', 'กลุ่มเปราะบาง + ไม่มีบัตร', 'ตกเกณฑ์ จปฐ. + ไม่มีบัตร', 'ทั้ง 3 กลุ่ม + ไม่มีบัตร', 'รวมทั้ง 3 กลุ่ม'];

        $filename = 'กลุ่มเป้าหมายผู้ตกหล่น_'.($hasTambon ? 'ตำบล' : 'อำเภอ').'_'.now()->format('Y-m-d').'.xlsx';

        // สร้างแถวข้อมูล + แถวรวมท้ายสุด
        $data = [];
        $i = 0;
        foreach ($rows as $r) {
            $line = [++$i, $r->amphur_name];
            if ($hasTambon) $line[] = $r->tambon_name;
            $data[] = array_merge($line, [$r->cnt_vulnerable, $r->cnt_jpt, $r->cnt_both, $r->cnt_total]);
        }
        // แถวรวมทั้งหมด
        $totalRow = ['', 'รวมทั้งหมด'];
        if ($hasTambon) $totalRow[] = '';
        $data[] = array_merge($totalRow, [
            (int) $rows->sum('cnt_vulnerable'),
            (int) $rows->sum('cnt_jpt'),
            (int) $rows->sum('cnt_both'),
            (int) $rows->sum('cnt_total'),
        ]);

        return Excel::download(new class($data, $headings) implements FromArray, WithHeadings {
            public function __construct(private $data, private $heads) {}
            public function array(): array { return $this->data; }
            public function headings(): array { return $this->heads; }
        }, $filename);
    }

    /** GET /api/missed-targets?level=amphur|tambon — รายการสถิติ (เรียงมาก→น้อย) */
    public function index(Request $request): JsonResponse
    {
        $level = $request->input('level', 'amphur') === 'tambon' ? 'tambon' : 'amphur';
        $rows = MissedTargetStat::where('level', $level)
            ->orderByDesc('cnt_total')
            ->get(['national_rank', 'amphur_name', 'tambon_name', 'cnt_jpt', 'cnt_vulnerable', 'cnt_both', 'cnt_total']);

        return response()->json(['level' => $level, 'data' => $rows]);
    }

    /** GET /api/missed-targets/summary — ข้อมูลสำหรับ chart + KPI */
    public function summary(): JsonResponse
    {
        $amphur = MissedTargetStat::where('level', 'amphur')->get();
        $tambonCount = MissedTargetStat::where('level', 'tambon')->count();

        $provinceTotal = [
            'jpt'        => (int) $amphur->sum('cnt_jpt'),
            'vulnerable' => (int) $amphur->sum('cnt_vulnerable'),
            'both'       => (int) $amphur->sum('cnt_both'),
            'total'      => (int) $amphur->sum('cnt_total'),
        ];

        $byAmphur = $amphur->sortByDesc('cnt_total')->values()->map(fn ($r) => [
            'amphur_name'    => $r->amphur_name,
            'cnt_jpt'        => $r->cnt_jpt,
            'cnt_vulnerable' => $r->cnt_vulnerable,
            'cnt_both'       => $r->cnt_both,
            'cnt_total'      => $r->cnt_total,
        ]);

        $lastImport = MissedTargetImport::latest()->first();

        return response()->json([
            'province_total' => $provinceTotal,
            'amphur_count'   => $amphur->count(),
            'tambon_count'   => $tambonCount,
            'by_amphur'      => $byAmphur,
            'last_import'    => $lastImport ? [
                'filename'         => $lastImport->filename,
                'level'            => $lastImport->level,
                'uploaded_by_name' => $lastImport->uploaded_by_name,
                'created_at'       => $lastImport->created_at?->toIso8601String(),
            ] : null,
        ]);
    }

    /** GET /api/missed-targets/imports — ประวัติการนำเข้า */
    public function imports(): JsonResponse
    {
        $rows = MissedTargetImport::latest()->take(50)->get()->map(fn ($im) => [
            'id'               => $im->id,
            'filename'         => $im->filename,
            'level'            => $im->level,
            'row_count'        => $im->row_count,
            'total_count'      => $im->total_count,
            'uploaded_by_name' => $im->uploaded_by_name,
            'note'             => $im->note,
            'created_at'       => $im->created_at?->toIso8601String(),
        ]);

        return response()->json(['data' => $rows]);
    }

    /** POST /api/missed-targets/upload — อัปโหลด Excel (super_admin) */
    public function upload(Request $request): JsonResponse
    {
        $request->validate([
            'file' => ['required', 'file', 'mimes:xlsx,xls', 'max:10240'],
            'note' => ['nullable', 'string', 'max:500'],
        ], [
            'file.required' => 'กรุณาเลือกไฟล์ Excel',
            'file.mimes'    => 'รองรับเฉพาะไฟล์ .xlsx / .xls',
        ]);

        $file = $request->file('file');
        $stored = $file->store('missed-targets', 'local');
        $absolute = storage_path('app/'.$stored);

        try {
            $result = $this->service->import(
                $absolute,
                $file->getClientOriginalName(),
                $request->user(),
                $request->input('note')
            );
        } catch (\Throwable $e) {
            return response()->json(['message' => 'นำเข้าไม่สำเร็จ: '.$e->getMessage()], 422);
        }

        return response()->json([
            'message' => "นำเข้าสำเร็จ {$result['row_count']} แถว ({$result['level']}) · รวม ".number_format($result['total']).' คน',
            'result'  => $result,
        ], 201);
    }
}
