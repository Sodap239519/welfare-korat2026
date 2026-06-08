<?php

namespace App\Services;

use App\Models\Amphur;
use App\Models\Household;
use App\Models\ImportLog;
use App\Models\Tambon;
use App\Models\Target;
use App\Models\Village;
use App\Support\HouseNoResolver;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class XlsxImportService
{
    /**
     * Import a target xlsx file.
     *
     * @param  string  $path    absolute path to .xlsx
     * @param  bool    $commit  true = persist to DB (Upsert);
     *                          false = preview only (rollback at the end)
     * @param  int|null $userId
     * @param  string  $originalName
     * @return array{import_log_id:?int, total:int, success:int, updated:int, failed:int, autofix:array}
     */
    public function import(string $path, bool $commit, ?int $userId, string $originalName): array
    {
        $log = null;
        if ($commit) {
            $log = ImportLog::create([
                'user_id'    => $userId,
                'filename'   => $originalName,
                'mode'       => 'upsert',
                'status'     => 'processing',
                'started_at' => now(),
            ]);
        }

        $reader = IOFactory::createReader('Xlsx');
        $reader->setReadDataOnly(false);
        $spreadsheet = $reader->load($path);
        $sheet = $spreadsheet->getActiveSheet();
        $highestRow = $sheet->getHighestDataRow();

        // ── หา mapping คอลัมน์จาก "หัวตาราง" (แถวที่ 1) อัตโนมัติ ──
        // ไฟล์ DSS มีหลายฟอร์แมต (บางไฟล์มีคอลัมน์ วันเกิด/อายุ บางไฟล์ไม่มี)
        // การอ่านหัวตารางทำให้ไม่ต้อง hardcode ตัวอักษรคอลัมน์ → กัน amphur/poverty สลับกัน
        $map = $this->resolveColumns($sheet);
        foreach (['houseCode', 'firstName', 'amphurName', 'tambonName'] as $required) {
            if (empty($map[$required])) {
                $human = [
                    'houseCode'  => 'รหัสบ้าน',
                    'firstName'  => 'ชื่อ',
                    'amphurName' => 'อำเภอ',
                    'tambonName' => 'ตำบล',
                ][$required];
                throw new \RuntimeException(
                    "ไม่พบคอลัมน์ \"{$human}\" ในหัวตารางของไฟล์ — ".
                    'กรุณาตรวจสอบว่าแถวแรกของไฟล์เป็นหัวตาราง (ปี / รหัสบ้าน / ชื่อ / สกุล / ตำบล / อำเภอ / สถานะ ...)'
                );
            }
        }

        $autofix = [];
        $success = 0;
        $updated = 0;
        $failed  = 0;

        $amphurCache = $tambonCache = $villageCache = [];

        $cell = fn (string $field, int $r, bool $calc = false): string
            => isset($map[$field])
                ? trim((string) ($calc
                    ? $sheet->getCell($map[$field].$r)->getCalculatedValue()
                    : $sheet->getCell($map[$field].$r)->getValue()))
                : '';

        $work = function () use ($sheet, $highestRow, $map, $cell, &$autofix, &$success, &$updated, &$failed, &$amphurCache, &$tambonCache, &$villageCache) {
            for ($r = 2; $r <= $highestRow; $r++) {
                try {
                    $year       = $cell('year', $r);
                    $houseCode  = $cell('houseCode', $r, true);
                    $memberSeq  = (int) ($map['memberSeq'] ? $sheet->getCell($map['memberSeq'].$r)->getValue() : 0);
                    $prefix     = $cell('prefix', $r);
                    $firstName  = $cell('firstName', $r);
                    $lastName   = $cell('lastName', $r);

                    // บ้านเลขที่ — ผ่านตัวแก้ค่าที่ Excel เพี้ยน (เช่น "27/1" → "27-ม.ค.")
                    if (!empty($map['addressNo'])) {
                        $hno = HouseNoResolver::resolve($sheet->getCell($map['addressNo'].$r));
                        if ($hno['was_fixed']) {
                            $autofix[] = [
                                'row'      => $r,
                                'name'     => trim("$prefix $firstName $lastName"),
                                'original' => $hno['original'],
                                'fixed'    => $hno['value'],
                                'reason'   => $hno['reason'],
                            ];
                        }
                        $addressNo = $hno['value'];
                    } else {
                        $addressNo = '';
                    }

                    $moo         = $cell('moo', $r);
                    $villageName = $cell('villageName', $r);
                    $tambonName  = $cell('tambonName', $r);
                    $amphurName  = $cell('amphurName', $r);
                    $poverty     = $cell('poverty', $r);
                    $welfareStr  = $cell('welfareStr', $r);
                    $income      = (int) ($map['income'] ? $sheet->getCell($map['income'].$r)->getValue() : 0);

                    if ($houseCode === '' || $firstName === '' || $amphurName === '') {
                        $failed++;
                        continue;
                    }

                    $amphur = $amphurCache[$amphurName]
                        ??= Amphur::firstOrCreate(['name' => $amphurName]);

                    $tambonKey = $amphur->id.':'.$tambonName;
                    $tambon = $tambonCache[$tambonKey]
                        ??= Tambon::firstOrCreate(['amphur_id' => $amphur->id, 'name' => $tambonName]);

                    $villageKey = $tambon->id.':'.$moo.':'.$villageName;
                    $village = $villageCache[$villageKey]
                        ??= Village::firstOrCreate([
                            'tambon_id' => $tambon->id,
                            'moo'       => $moo,
                            'name'      => $villageName ?: '(ไม่ระบุ)',
                        ]);
                    // ใส่พิกัด fallback ถ้าหมู่บ้านใหม่ — กันหายจาก map
                    $village->ensureCoords();

                    $hash = Household::hashFor($houseCode);
                    $household = Household::where('house_code_hash', $hash)->first();
                    if (!$household) {
                        $household = new Household();
                        $household->village_id = $village->id;
                        $household->address_no = $addressNo;
                        $household->setHouseCode($houseCode);
                        $household->save();
                    } else {
                        if ($addressNo && $household->address_no !== $addressNo) {
                            $household->address_no = $addressNo;
                            $household->save();
                        }
                    }

                    $hasOldWelfare = str_contains($welfareStr, 'ได้รับ') && !str_contains($welfareStr, 'ไม่ได้รับ');

                    $target = Target::updateOrCreate(
                        ['household_id' => $household->id, 'member_seq' => $memberSeq],
                        [
                            'village_id'      => $village->id,
                            'tambon_id'       => $tambon->id,
                            'amphur_id'       => $amphur->id,
                            'year'            => is_numeric($year) ? (int) $year : null,
                            'prefix'          => $prefix,
                            'first_name'      => $firstName,
                            'last_name'       => $lastName,
                            'poverty_level'   => $poverty,
                            'has_old_welfare' => $hasOldWelfare,
                            'annual_income'   => $income,
                            'active'          => true,
                        ]
                    );

                    if ($target->wasRecentlyCreated) $success++;
                    else $updated++;
                } catch (\Throwable $e) {
                    $failed++;
                    $autofix[] = ['row' => $r, 'error' => $e->getMessage()];
                }
            }
        };

        if ($commit) {
            DB::transaction($work);
        } else {
            // Preview mode: do everything in a transaction then rollback
            DB::beginTransaction();
            try {
                $work();
            } finally {
                DB::rollBack();
            }
        }

        if ($log) {
            $log->update([
                'total'        => $highestRow - 1,
                'success'      => $success,
                'updated'      => $updated,
                'failed'       => $failed,
                'autofix_log'  => $autofix,
                'status'       => 'done',
                'finished_at'  => now(),
            ]);
        }

        return [
            'import_log_id' => $log?->id,
            'total'         => $highestRow - 1,
            'success'       => $success,
            'updated'       => $updated,
            'failed'        => $failed,
            'autofix'       => $autofix,
        ];
    }

    /**
     * อ่านหัวตาราง (แถว 1) แล้วจับคู่ field → ตัวอักษรคอลัมน์ (เช่น 'amphurName' => 'L')
     * ทำให้รองรับไฟล์ DSS ได้ทุกฟอร์แมต ไม่ว่าจะมีคอลัมน์ วันเกิด/อายุ หรือไม่
     *
     * @return array<string,string>
     */
    private function resolveColumns(Worksheet $sheet): array
    {
        $highestCol  = $sheet->getHighestDataColumn(1);
        $highestIdx  = Coordinate::columnIndexFromString($highestCol);

        $headers = []; // letter => normalized header text
        for ($i = 1; $i <= $highestIdx; $i++) {
            $letter = Coordinate::stringFromColumnIndex($i);
            $text   = trim(preg_replace('/\s+/u', ' ', (string) $sheet->getCell($letter.'1')->getValue()));
            if ($text !== '') {
                $headers[$letter] = $text;
            }
        }

        // กฎจับคู่ field → header (เรียงตามความเฉพาะเจาะจง เพื่อกันชนกันของคำว่า "บ้าน")
        $rules = [
            'year'        => fn ($h) => $h === 'ปี',
            'houseCode'   => fn ($h) => str_contains($h, 'รหัสบ้าน') || (str_contains($h, 'รหัส') && str_contains($h, 'บ้าน')),
            'memberSeq'   => fn ($h) => str_contains($h, 'ลำดับ') || str_contains($h, 'สมาชิก'),
            'prefix'      => fn ($h) => str_contains($h, 'คำนำหน้า'),
            'firstName'   => fn ($h) => $h === 'ชื่อ',
            'lastName'    => fn ($h) => str_contains($h, 'สกุล') || str_contains($h, 'นามสกุล'),
            'addressNo'   => fn ($h) => str_contains($h, 'บ้านเลขที่') || str_contains($h, 'เลขที่'),
            'moo'         => fn ($h) => str_contains($h, 'หมู่ที่') || $h === 'หมู่',
            'villageName' => fn ($h) => $h === 'บ้าน' || str_contains($h, 'หมู่บ้าน') || str_contains($h, 'ชุมชน'),
            'tambonName'  => fn ($h) => str_contains($h, 'ตำบล'),
            'amphurName'  => fn ($h) => str_contains($h, 'อำเภอ'),
            'poverty'     => fn ($h) => str_contains($h, 'สถานะ') || str_contains($h, 'ความเป็นอยู่'),
            'welfareStr'  => fn ($h) => str_contains($h, 'สวัสดิการ') || str_contains($h, 'บัตร'),
            'income'      => fn ($h) => str_contains($h, 'รายได้'),
        ];

        $map     = [];
        $claimed = [];
        foreach ($rules as $field => $pred) {
            foreach ($headers as $letter => $h) {
                if (isset($claimed[$letter])) {
                    continue;
                }
                if ($pred($h)) {
                    $map[$field]      = $letter;
                    $claimed[$letter] = true;
                    break;
                }
            }
        }

        return $map;
    }
}
