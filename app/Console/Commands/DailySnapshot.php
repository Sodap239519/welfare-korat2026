<?php

namespace App\Console\Commands;

use App\Models\ReportSnapshot;
use App\Models\Target;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Facades\Excel;

/**
 * Daily snapshot — รันทุกวัน 16:30 (ตาม SOP รายงาน)
 *
 * Schedule: routes/console.php
 *   Schedule::command('reports:daily-snapshot')->dailyAt('16:30');
 */
class DailySnapshot extends Command
{
    protected $signature = 'reports:daily-snapshot {--date= : YYYY-MM-DD (default: today)}';
    protected $description = 'สรุปยอดรายหมู่บ้านประจำวัน บันทึก snapshot + สร้างไฟล์ xlsx';

    public function handle(): int
    {
        $date = $this->option('date') ? \Carbon\Carbon::parse($this->option('date')) : now();
        $this->info('Generating daily snapshot for '.$date->toDateString().' ...');

        $rows = DB::table('villages')
            ->join('tambons', 'tambons.id', '=', 'villages.tambon_id')
            ->join('amphurs', 'amphurs.id', '=', 'tambons.amphur_id')
            ->leftJoin('targets', function ($j) {
                $j->on('targets.village_id', '=', 'villages.id')->where('targets.active', '=', true);
            })
            ->leftJoin('target_current_status as tcs', 'tcs.target_id', '=', 'targets.id')
            ->selectRaw('
                villages.id, villages.name as village, villages.moo,
                tambons.name as tambon, amphurs.name as amphur,
                COUNT(DISTINCT targets.id) as total,
                COUNT(DISTINCT CASE WHEN tcs.status_code = "4.7" THEN targets.id END) as kyc_done,
                COUNT(DISTINCT CASE WHEN tcs.status_code = "4.6" THEN targets.id END) as kyc_waiting,
                COUNT(DISTINCT CASE WHEN tcs.status_code IS NOT NULL AND tcs.status_code <> "4.1" THEN targets.id END) as done
            ')
            ->groupBy('villages.id', 'villages.name', 'villages.moo', 'tambons.name', 'amphurs.name')
            ->orderByRaw('COUNT(DISTINCT CASE WHEN tcs.status_code IS NOT NULL AND tcs.status_code <> "4.1" THEN targets.id END) / GREATEST(COUNT(DISTINCT targets.id), 1) DESC')
            ->get();

        $totalTargets = (int) Target::where('active', true)->count();
        $totalDone = (int) $rows->sum('done');
        $pct = $totalTargets > 0 ? round(($totalDone / $totalTargets) * 100, 2) : 0;

        $payload = $rows->map(fn ($r) => [
            'village_id'  => (int) $r->id,
            'village'     => $r->village . ($r->moo ? ' (ม.'.$r->moo.')' : ''),
            'tambon'      => $r->tambon,
            'amphur'      => $r->amphur,
            'total'       => (int) $r->total,
            'kyc_done'    => (int) $r->kyc_done,
            'kyc_waiting' => (int) $r->kyc_waiting,
            'done'        => (int) $r->done,
            'pct'         => $r->total > 0 ? round(($r->done / $r->total) * 100, 1) : 0,
        ])->all();

        // Generate xlsx file
        $headings = ['หมู่บ้าน', 'ตำบล', 'อำเภอ', 'เป้า', '4.7 ใช้สิทธิ', '4.6 รอยืนยัน', 'รวม', '%'];
        $tableRows = array_map(fn ($r) => [
            $r['village'], $r['tambon'], $r['amphur'],
            $r['total'], $r['kyc_done'], $r['kyc_waiting'], $r['done'], $r['pct'].'%',
        ], $payload);

        $filename = 'reports/daily-villages-'.$date->toDateString().'.xlsx';
        Excel::store(new class($tableRows, $headings) implements FromArray, WithHeadings {
            public function __construct(private $rows, private $heads) {}
            public function array(): array { return $this->rows; }
            public function headings(): array { return $this->heads; }
        }, $filename, 'local');

        ReportSnapshot::updateOrCreate(
            ['type' => 'daily_villages', 'snapshot_date' => $date->toDateString()],
            [
                'total_targets'    => $totalTargets,
                'total_registered' => $totalDone,
                'pct_done'         => $pct,
                'payload'          => $payload,
                'file_path'        => $filename,
            ]
        );

        $this->info("Done: {$rows->count()} villages · total registered {$totalDone}/{$totalTargets} ({$pct}%) · file: {$filename}");
        return self::SUCCESS;
    }
}
