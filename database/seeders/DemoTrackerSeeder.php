<?php

namespace Database\Seeders;

use App\Models\Tracker;
use App\Models\Village;
use Illuminate\Database\Seeder;

class DemoTrackerSeeder extends Seeder
{
    public function run(): void
    {
        $villages = Village::with('tambon')->get();

        $samples = [
            ['ผู้ใหญ่บ้าน', 'นายสมชาย ใจดี',     '0810000001'],
            ['กำนัน',       'นายมานพ พิทักษ์',   '0820000002'],
            ['ผู้ช่วยผู้ใหญ่บ้าน', 'นายอดิสรณ์ สุข', '0840000003'],
            ['อสม.',         'นางสายฝน ตั้งใจ',  '0830000004'],
            ['ส.อบต.',      'นางขันทอง ทองคำ',   '0850000005'],
            ['ผู้ใหญ่บ้าน', 'นางสาวพรรณี ดวงดี', '0860000006'],
        ];

        $created = 0;
        foreach ($villages as $i => $v) {
            // 1 active tracker per village (round-robin through sample positions)
            $s = $samples[$i % count($samples)];
            // unique full_name by appending village name
            $name = $s[1] . ' (' . $v->name . ')';
            // unique phone by appending village id
            $phone = substr($s[2], 0, 6) . str_pad((string) $v->id, 4, '0', STR_PAD_LEFT);

            $exists = Tracker::where('village_id', $v->id)->where('full_name', $name)->exists();
            if (!$exists) {
                Tracker::create([
                    'village_id' => $v->id,
                    'full_name'  => $name,
                    'position'   => $s[0],
                    'phone'      => $phone,
                    'active'     => true,
                ]);
                $created++;
            }
        }

        $this->command->info("Seeded $created trackers across {$villages->count()} villages");
    }
}
