<?php

namespace Database\Seeders;

use App\Models\Amphur;
use App\Models\Channel;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * ตั้งค่าบัญชีจริงสำหรับใช้งานจริง (idempotent — รันซ้ำได้)
 *   - Super Admin × 2
 *   - Admin อำเภอ × 32 (อำเภอละ 1)
 *   - เจ้าหน้าที่ธนาคาร × (32 อำเภอ × 5 ธนาคาร)
 *   - ลบบัญชี demo เดิม (@welfare.korat.local ที่ไม่อยู่ในชุดที่ดูแล)
 *
 * เบอร์โทร (= username) เป็นเลขชั่วคราว — Super Admin แก้เป็นเบอร์จริงได้ภายหลัง
 *   Admin:    093 + รหัสอำเภอ(3) + 0001
 *   ธนาคาร:   095 + รหัสอำเภอ(3) + เลขธนาคาร(1) + 000
 */
class ProductionSetupSeeder extends Seeder
{
    private const BANKS = [
        1 => ['code' => 'ktb',   'name' => 'ธ.กรุงไทย'],
        2 => ['code' => 'gsb',   'name' => 'ธ.ออมสิน'],
        3 => ['code' => 'baac',  'name' => 'ธ.ก.ส.'],
        4 => ['code' => 'ghb',   'name' => 'ธ.อาคารสงเคราะห์'],
        5 => ['code' => 'ibank', 'name' => 'ธ.อิสลามแห่งประเทศไทย'],
    ];

    public function run(): void
    {
        $managedPhones = [];

        // ─── 1. Super Admin × 2 ───
        $supers = [
            ['name' => 'ปิยะรัตน์ โสดา',  'phone' => '0644233656', 'email' => 'Piyarat.s@nrru.ac.th', 'password' => '212224236'],
            ['name' => 'ปนัดดา คำจะโปะ', 'phone' => '0800728804', 'email' => 'Panadda.ka@nrru.ac.th', 'password' => '212224236'],
        ];
        foreach ($supers as $s) {
            $managedPhones[] = $s['phone'];
            $u = User::updateOrCreate(
                ['phone' => $s['phone']],
                ['name' => $s['name'], 'email' => $s['email'], 'password' => Hash::make($s['password']), 'active' => true]
            );
            $u->syncRoles(['super_admin']);
        }

        $amphurs = Amphur::orderBy('id')->get();
        $bankChannelId = Channel::where('code', 'bank')->value('id');

        // ─── 2. Admin อำเภอ × 32 ───
        foreach ($amphurs as $a) {
            $phone = '093' . str_pad((string) $a->id, 3, '0', STR_PAD_LEFT) . '0001';
            $managedPhones[] = $phone;
            $u = User::updateOrCreate(
                ['phone' => $phone],
                [
                    'name'      => 'Admin อำเภอ' . $a->name,
                    'email'     => 'admin.amp' . $a->id . '@welfare.korat.local',
                    'password'  => Hash::make('123456'),
                    'amphur_id' => $a->id,
                    'bank_channel_id' => null, 'bank_sub_channel' => null, 'bank_branch' => null,
                    'active'    => true,
                ]
            );
            $u->syncRoles(['admin']);
        }

        // ─── 3. เจ้าหน้าที่ธนาคาร × (32 × 5) ───
        foreach ($amphurs as $a) {
            foreach (self::BANKS as $no => $b) {
                $phone = '095' . str_pad((string) $a->id, 3, '0', STR_PAD_LEFT) . $no . '000';
                $managedPhones[] = $phone;
                $u = User::updateOrCreate(
                    ['phone' => $phone],
                    [
                        'name'             => 'เจ้าหน้าที่' . $b['name'] . ' อำเภอ' . $a->name,
                        'email'            => 'bank.' . $b['code'] . '.amp' . $a->id . '@welfare.korat.local',
                        'password'         => Hash::make('123456'),
                        'bank_channel_id'  => $bankChannelId,
                        'bank_sub_channel' => $b['code'],
                        'bank_branch'      => null,
                        'amphur_id'        => $a->id,
                        'active'           => true,
                    ]
                );
                $u->syncRoles(['bank_staff']);
            }
        }

        // ─── 4. ลบบัญชี demo เดิม (@welfare.korat.local ที่ไม่อยู่ในชุดที่ดูแล) ───
        $legacy = User::where('email', 'like', '%@welfare.korat.local')
            ->whereNotIn('phone', $managedPhones)
            ->get();
        foreach ($legacy as $u) {
            $u->delete();
        }

        $this->command->info(
            'ProductionSetup: 2 super · ' . $amphurs->count() . ' admin · '
            . ($amphurs->count() * count(self::BANKS)) . ' bank_staff · ลบ demo ' . $legacy->count() . ' บัญชี'
        );
    }
}
