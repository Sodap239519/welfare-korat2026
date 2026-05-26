<?php

namespace App\Console\Commands;

use App\Models\Target;
use App\Models\Tracker;
use App\Notifications\TargetStatusStuck;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * แจ้งเตือน tracker เมื่อมีเป้าหมายค้างสถานะ 4.4/4.5 เกิน N วัน
 *
 * Schedule: รัน 08:00 ทุกวัน (ผ่าน routes/console.php)
 */
class NotifyStuckStatuses extends Command
{
    protected $signature = 'reports:notify-stuck {--days=7 : ค้างกี่วันถึงแจ้งเตือน}';
    protected $description = 'แจ้งเตือน tracker เมื่อ target ค้างสถานะ 4.4/4.5 เกิน N วัน';

    public function handle(): int
    {
        $days = (int) $this->option('days');
        $this->info("Checking targets stuck > $days days at status 4.4 / 4.5 ...");

        $stuck = DB::table('targets')
            ->join('target_current_status as tcs', 'tcs.target_id', '=', 'targets.id')
            ->whereIn('tcs.status_code', ['4.4', '4.5'])
            ->where('tcs.updated_at', '<=', now()->subDays($days))
            ->where('targets.active', true)
            ->select('targets.id', 'targets.village_id', 'tcs.status_code', 'tcs.updated_at')
            ->get();

        if ($stuck->isEmpty()) {
            $this->info('No stuck targets — all good 🎉');
            return self::SUCCESS;
        }

        // group by village → notify tracker of that village
        $byVillage = $stuck->groupBy('village_id');
        $notified = 0;
        $skipped = 0;

        foreach ($byVillage as $villageId => $rows) {
            $tracker = Tracker::with('user')->where('village_id', $villageId)->where('active', true)->first();
            if (!$tracker?->user) { $skipped += $rows->count(); continue; }

            foreach ($rows as $row) {
                $target = Target::find($row->id);
                if (!$target) continue;
                $daysStuck = (int) now()->diffInDays($row->updated_at);
                $tracker->user->notify(new TargetStatusStuck($target, $row->status_code, $daysStuck));
                $notified++;
            }
        }

        $this->info("Done: notified $notified targets to trackers (skipped $skipped — no tracker linked)");
        return self::SUCCESS;
    }
}
