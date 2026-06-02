<?php

namespace Database\Seeders;

use App\Models\Amphur;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * สร้าง admin ระดับอำเภอ 2 คน/อำเภอ × 32 อำเภอ = 64 บัญชี
 *
 * Phone pattern:  093 + 3-digit amphur_id (zero-padded) + 4-digit serial
 *   เช่น  อำเภอ id=1 → 0930010001, 0930010002
 *         อำเภอ id=32 → 0930320001, 0930320002
 *
 * Password: 123456 (เปลี่ยนหลัง login ครั้งแรก)
 */
class DistrictAdminUserSeeder extends Seeder
{
    public function run(): void
    {
        $amphurs = Amphur::orderBy('id')->get();
        $this->command->info("กำลังสร้าง admin อำเภอ {$amphurs->count()} อำเภอ × 2 คน...");

        $created = 0;
        foreach ($amphurs as $a) {
            $idStr = str_pad((string) $a->id, 3, '0', STR_PAD_LEFT);
            for ($i = 1; $i <= 2; $i++) {
                $phone = '093' . $idStr . str_pad((string) $i, 4, '0', STR_PAD_LEFT);
                $user = User::updateOrCreate(
                    ['phone' => $phone],
                    [
                        'name'      => "Admin อ.{$a->name} #{$i}",
                        'email'     => "admin.amp{$a->id}.{$i}@welfare.korat.local",
                        'password'  => Hash::make('123456'),
                        'amphur_id' => $a->id,
                        'active'    => true,
                    ]
                );
                $user->syncRoles(['admin']);
                $created++;
            }
        }

        $this->command->line("  สร้างเสร็จ {$created} บัญชี · password ทุกคน: 123456");
        $this->command->line("  ตัวอย่าง: phone 0930010001 = Admin อ.{$amphurs->first()->name} #1");
    }
}
