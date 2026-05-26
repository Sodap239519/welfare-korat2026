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
use PhpOffice\PhpSpreadsheet\IOFactory;

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

        $autofix = [];
        $success = 0;
        $updated = 0;
        $failed  = 0;

        $amphurCache = $tambonCache = $villageCache = [];

        $work = function () use ($sheet, $highestRow, &$autofix, &$success, &$updated, &$failed, &$amphurCache, &$tambonCache, &$villageCache) {
            for ($r = 2; $r <= $highestRow; $r++) {
                try {
                    $year       = trim((string) $sheet->getCell("B$r")->getValue());
                    $houseCode  = trim((string) $sheet->getCell("C$r")->getCalculatedValue());
                    $memberSeq  = (int) $sheet->getCell("D$r")->getValue();
                    $prefix     = trim((string) $sheet->getCell("E$r")->getValue());
                    $firstName  = trim((string) $sheet->getCell("F$r")->getValue());
                    $lastName   = trim((string) $sheet->getCell("G$r")->getValue());

                    $hno = HouseNoResolver::resolve($sheet->getCell("H$r"));
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

                    $moo         = trim((string) $sheet->getCell("I$r")->getValue());
                    $villageName = trim((string) $sheet->getCell("J$r")->getValue());
                    $tambonName  = trim((string) $sheet->getCell("K$r")->getValue());
                    $amphurName  = trim((string) $sheet->getCell("L$r")->getValue());
                    $poverty     = trim((string) $sheet->getCell("N$r")->getValue());
                    $welfareStr  = trim((string) $sheet->getCell("O$r")->getValue());
                    $income      = (int) $sheet->getCell("P$r")->getValue();

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
}
