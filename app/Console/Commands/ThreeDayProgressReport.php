<?php

namespace App\Console\Commands;

use App\Notifications\ProgressReport;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

/**
 * รายงานความคืบหน้าราย 3 วัน → ส่งเข้า LINE
 *
 * - สรุปยอดรวมทั้งจังหวัด
 * - TOP 5 อำเภอเรียงตาม % ลงทะเบียน
 * - สำหรับแต่ละ TOP 5 อำเภอ → list ตำบลในนั้น (เรียง %)
 * - ข้อความสะอาด เห็นภาพเข้าใจง่ายในหน้า LINE
 *
 * รัน:    php artisan report:three-day
 * ทดสอบ: php artisan report:three-day --dry-run  (พิมพ์ออก console ไม่ส่ง LINE)
 */
class ThreeDayProgressReport extends Command
{
    protected $signature = 'report:three-day {--dry-run : พิมพ์ออก console ไม่ส่ง LINE}';
    protected $description = 'ส่งรายงานความคืบหน้าราย 3 วัน เข้า LINE (TOP 5 อำเภอ + ตำบลย่อย)';

    public function handle(): int
    {
        $this->info('กำลังคำนวณรายงานราย 3 วัน...');

        // ───────────────────────────────────────────────────────────
        // 1) สรุปยอดรวมทั้งจังหวัด
        // ───────────────────────────────────────────────────────────
        $totalTargets = (int) DB::table('targets')->where('active', true)->count();
        $totalDone    = (int) DB::table('target_current_status')
            ->where('status_code', '4.7')->count();
        $totalInProgress = (int) DB::table('target_current_status')
            ->whereIn('status_code', ['4.2', '4.3', '4.4', '4.5', '4.6'])->count();
        $overallPct   = $totalTargets > 0 ? round(($totalDone / $totalTargets) * 100, 1) : 0;

        // เปลี่ยนแปลงใน 3 วัน
        $changeIn3Days = (int) DB::table('target_status_logs')
            ->where('changed_at', '>=', now()->subDays(3))
            ->where('status_code', '4.7')
            ->count();

        // ───────────────────────────────────────────────────────────
        // 2) TOP 5 อำเภอ (เรียง % ลงทะเบียน)
        // ───────────────────────────────────────────────────────────
        $topAmphurs = DB::table('amphurs as a')
            ->join('tambons as t', 't.amphur_id', '=', 'a.id')
            ->join('villages as v', 'v.tambon_id', '=', 't.id')
            ->leftJoin('targets as tg', function ($j) {
                $j->on('tg.village_id', '=', 'v.id')->where('tg.active', '=', true);
            })
            ->leftJoin('target_current_status as tcs', 'tcs.target_id', '=', 'tg.id')
            ->groupBy('a.id', 'a.name')
            ->selectRaw('
                a.id,
                a.name,
                COUNT(DISTINCT tg.id) AS total,
                COUNT(DISTINCT CASE WHEN tcs.status_code = "4.7" THEN tg.id END) AS done,
                COUNT(DISTINCT CASE WHEN tcs.status_code IN ("4.2","4.3","4.4","4.5","4.6") THEN tg.id END) AS in_progress
            ')
            ->havingRaw('COUNT(DISTINCT tg.id) > 0')
            ->orderByRaw('(COUNT(DISTINCT CASE WHEN tcs.status_code = "4.7" THEN tg.id END) / GREATEST(COUNT(DISTINCT tg.id), 1)) DESC')
            ->limit(5)
            ->get();

        // ───────────────────────────────────────────────────────────
        // 3) สำหรับแต่ละ TOP 5 อำเภอ → top 3 ตำบลย่อยที่ดีที่สุด
        // ───────────────────────────────────────────────────────────
        $amphurDetails = [];
        foreach ($topAmphurs as $amphur) {
            $topTambons = DB::table('tambons as t')
                ->join('villages as v', 'v.tambon_id', '=', 't.id')
                ->leftJoin('targets as tg', function ($j) {
                    $j->on('tg.village_id', '=', 'v.id')->where('tg.active', '=', true);
                })
                ->leftJoin('target_current_status as tcs', 'tcs.target_id', '=', 'tg.id')
                ->where('t.amphur_id', $amphur->id)
                ->groupBy('t.id', 't.name')
                ->selectRaw('
                    t.name,
                    COUNT(DISTINCT tg.id) AS total,
                    COUNT(DISTINCT CASE WHEN tcs.status_code = "4.7" THEN tg.id END) AS done
                ')
                ->havingRaw('COUNT(DISTINCT tg.id) > 0')
                ->orderByRaw('(COUNT(DISTINCT CASE WHEN tcs.status_code = "4.7" THEN tg.id END) / GREATEST(COUNT(DISTINCT tg.id), 1)) DESC')
                ->limit(3)
                ->get();

            $amphurDetails[$amphur->id] = $topTambons;
        }

        // ───────────────────────────────────────────────────────────
        // 4) Build LINE message
        // ───────────────────────────────────────────────────────────
        $msg = $this->buildMessage($totalTargets, $totalDone, $totalInProgress, $overallPct, $changeIn3Days, $topAmphurs, $amphurDetails);

        if ($this->option('dry-run')) {
            $this->info('--- DRY RUN · ไม่ส่ง LINE ---');
            $this->line($msg);
            return self::SUCCESS;
        }

        // ───────────────────────────────────────────────────────────
        // 5) ส่งเข้า LINE
        // ───────────────────────────────────────────────────────────
        $hasToken = config('services.line.notify_token') || config('services.line.messaging_token');
        if (!$hasToken) {
            $this->warn('ยังไม่ได้ตั้งค่า LINE token — ข้ามการส่ง · ใช้ --dry-run เพื่อดู preview');
            return self::SUCCESS;
        }

        Notification::route('line', true)->notify(new ProgressReport($msg));
        $this->info('✅ ส่งรายงานเข้า LINE เรียบร้อย');
        return self::SUCCESS;
    }

    /**
     * Build รายงานเป็น text สำหรับ LINE
     */
    private function buildMessage($total, $done, $inProgress, $pct, $change, $topAmphurs, $amphurDetails): string
    {
        $date  = now()->locale('th')->translatedFormat('j F Y');
        $time  = now()->format('H:i');
        $arrow = $change > 0 ? "↗ +{$change}" : "→ {$change}";

        $lines = [
            "📊 รายงานความคืบหน้า · 3 วันล่าสุด",
            "━━━━━━━━━━━━━━━━━━━━",
            "จ.นครราชสีมา · {$date} {$time}",
            "",
            "📌 รวม: " . number_format($done) . " / " . number_format($total) . " ({$pct}%)",
            "   {$arrow} ราย ใน 3 วันที่ผ่านมา",
            "   ⏳ กำลังดำเนินการ: " . number_format($inProgress) . " ราย",
            "",
            "🏆 TOP 5 อำเภอ — ความคืบหน้าสูงสุด",
        ];

        $rank = 0;
        $medals = ['🥇', '🥈', '🥉', '4️⃣', '5️⃣'];
        foreach ($topAmphurs as $amphur) {
            $rank++;
            $aPct = $amphur->total > 0 ? round(($amphur->done / $amphur->total) * 100, 1) : 0;
            $medal = $medals[$rank - 1] ?? "{$rank}.";
            $lines[] = "";
            $lines[] = "{$medal} {$amphur->name} — {$aPct}%";
            $lines[] = "   " . number_format($amphur->done) . " / " . number_format($amphur->total) . " ราย";

            // tambons ย่อย
            $tambons = $amphurDetails[$amphur->id] ?? collect();
            foreach ($tambons as $t) {
                $tPct = $t->total > 0 ? round(($t->done / $t->total) * 100, 1) : 0;
                $lines[] = "   • {$t->name} ({$tPct}%)";
            }
        }

        $lines[] = "";
        $lines[] = "━━━━━━━━━━━━━━━━━━━━";
        $appUrl = rtrim(config('app.url'), '/');
        $lines[] = "🔗 เข้าระบบ: {$appUrl}";

        return implode("\n", $lines);
    }
}
