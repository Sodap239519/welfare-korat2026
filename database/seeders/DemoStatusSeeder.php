<?php

namespace Database\Seeders;

use App\Models\Channel;
use App\Models\Target;
use App\Models\TargetCurrentStatus;
use App\Models\TargetStatusLog;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DemoStatusSeeder extends Seeder
{
    /** Distribute statuses across targets so the Dashboard shows realistic numbers. */
    public function run(): void
    {
        $userId = User::whereHas('roles', fn ($q) => $q->where('name', 'tracker'))->value('id')
                 ?? User::value('id');

        $channels = Channel::pluck('id')->all();
        $targets = Target::where('active', true)->pluck('id')->shuffle();

        // Approximate distribution (sums to 100)
        $distribution = [
            ['code' => '4.1', 'pct' => 3,  'note' => 'ไม่ประสงค์รับบัตร'],
            ['code' => '4.2', 'pct' => 10, 'requires_channel' => true],
            ['code' => '4.3', 'pct' => 13, 'requires_channel' => true],
            ['code' => '4.4', 'pct' => 2,  'note' => 'รอเอกสารเพิ่ม',  'requires_channel' => true],
            ['code' => '4.5', 'pct' => 1,  'note' => 'รออุทธรณ์'],
            ['code' => '4.6', 'pct' => 14, 'requires_channel' => true],
            ['code' => '4.7', 'pct' => 8,  'requires_channel' => true],
            // remaining ~49% stay null (ยังไม่อัปเดต) — keeps demo realistic
        ];

        $total = $targets->count();
        $idx = 0;
        $now = now();
        $logsBatch = [];
        $curBatch = [];

        foreach ($distribution as $d) {
            $count = (int) round($total * $d['pct'] / 100);
            for ($i = 0; $i < $count && $idx < $total; $i++, $idx++) {
                $targetId = $targets[$idx];
                $channelId = !empty($d['requires_channel']) ? $channels[array_rand($channels)] : null;
                $when = $now->copy()->subDays(random_int(0, 13))->subHours(random_int(0, 23));

                $logsBatch[] = [
                    'target_id'   => $targetId,
                    'status_code' => $d['code'],
                    'channel_id'  => $channelId,
                    'note'        => $d['note'] ?? null,
                    'user_id'     => $userId,
                    'changed_at'  => $when,
                    'created_at'  => $when,
                    'updated_at'  => $when,
                ];
                $curBatch[] = [
                    'target_id'   => $targetId,
                    'status_code' => $d['code'],
                    'channel_id'  => $channelId,
                    'note'        => $d['note'] ?? null,
                    'updated_by'  => $userId,
                    'updated_at'  => $when,
                ];
            }
        }

        DB::table('target_status_logs')->insert($logsBatch);
        DB::table('target_current_status')->insert($curBatch);

        $this->command->info(sprintf(
            'Seeded statuses for %d / %d targets across %d levels',
            count($curBatch), $total, count($distribution)
        ));
    }
}
