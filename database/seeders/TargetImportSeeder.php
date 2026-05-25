<?php

namespace Database\Seeders;

use App\Models\Amphur;
use App\Models\Household;
use App\Models\ImportLog;
use App\Models\Tambon;
use App\Models\Target;
use App\Models\Village;
use App\Support\HouseNoResolver;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\IOFactory;

class TargetImportSeeder extends Seeder
{
    public function run(): void
    {
        $file = base_path('Data/ต.ปากช่อง.xlsx');
        if (!file_exists($file)) {
            $this->command->warn("Data file not found: $file — skipping target import");
            return;
        }

        $this->command->info("Importing $file ...");

        $log = ImportLog::create([
            'filename'   => basename($file),
            'mode'       => 'seed-initial',
            'status'     => 'processing',
            'started_at' => now(),
        ]);

        $reader = IOFactory::createReader('Xlsx');
        $reader->setReadDataOnly(false); // keep number-format info for HouseNoResolver
        $spreadsheet = $reader->load($file);
        $sheet = $spreadsheet->getActiveSheet();

        $highestRow = $sheet->getHighestDataRow();
        $autofix = [];
        $success = 0;
        $updated = 0;
        $failed  = 0;

        // tiny lookup caches
        $amphurCache = [];
        $tambonCache = [];
        $villageCache = [];

        DB::transaction(function () use ($sheet, $highestRow, $log, &$autofix, &$success, &$updated, &$failed, &$amphurCache, &$tambonCache, &$villageCache) {
            for ($r = 2; $r <= $highestRow; $r++) {
                try {
                    $year       = trim((string) $sheet->getCell("B$r")->getValue());
                    $houseCode  = trim((string) $sheet->getCell("C$r")->getCalculatedValue());
                    $memberSeq  = (int) $sheet->getCell("D$r")->getValue();
                    $prefix     = trim((string) $sheet->getCell("E$r")->getValue());
                    $firstName  = trim((string) $sheet->getCell("F$r")->getValue());
                    $lastName   = trim((string) $sheet->getCell("G$r")->getValue());

                    // บ้านเลขที่ — กันโดน Excel ตีเป็นวันที่
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
                    $addressNo  = $hno['value'];

                    $moo        = trim((string) $sheet->getCell("I$r")->getValue());
                    $villageName= trim((string) $sheet->getCell("J$r")->getValue());
                    $tambonName = trim((string) $sheet->getCell("K$r")->getValue());
                    $amphurName = trim((string) $sheet->getCell("L$r")->getValue());
                    $poverty    = trim((string) $sheet->getCell("N$r")->getValue());
                    $welfareStr = trim((string) $sheet->getCell("O$r")->getValue());
                    $income     = (int) $sheet->getCell("P$r")->getValue();

                    if ($houseCode === '' || $firstName === '' || $amphurName === '') {
                        $failed++;
                        continue;
                    }

                    // Geo (cached)
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

                    // Household (by hashed house code)
                    $hash = Household::hashFor($houseCode);
                    $household = Household::where('house_code_hash', $hash)->first();
                    if (!$household) {
                        $household = new Household();
                        $household->village_id = $village->id;
                        $household->address_no = $addressNo;
                        $household->setHouseCode($houseCode);
                        $household->save();
                    } else {
                        // update address_no if changed
                        if ($addressNo && $household->address_no !== $addressNo) {
                            $household->address_no = $addressNo;
                            $household->save();
                        }
                    }

                    // Upsert target (household_id + member_seq)
                    $hasOldWelfare = str_contains($welfareStr, 'ได้รับ') && !str_contains($welfareStr, 'ไม่ได้รับ');

                    $target = Target::updateOrCreate(
                        [
                            'household_id' => $household->id,
                            'member_seq'   => $memberSeq,
                        ],
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
        });

        $log->update([
            'total'        => $highestRow - 1,
            'success'      => $success,
            'updated'      => $updated,
            'failed'       => $failed,
            'autofix_log'  => $autofix,
            'status'       => 'done',
            'finished_at'  => now(),
        ]);

        $this->command->info(sprintf(
            'Done: %d created, %d updated, %d failed · %d auto-fixed house numbers',
            $success, $updated, $failed,
            count(array_filter($autofix, fn ($r) => isset($r['fixed'])))
        ));
    }
}
