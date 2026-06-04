<?php

namespace App\Services;

use App\Models\MissedTargetImport;
use App\Models\MissedTargetStat;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\IOFactory;

/**
 * นำเข้าไฟล์ Excel "กลุ่มเป้าหมายผู้ตกหล่น"
 *
 * รูปแบบที่รองรับ (positional):
 *   [ลำดับ], อำเภอ, [ตำบล], ผู้ตกเกณฑ์ จปฐ.+ไม่มีบัตร, กลุ่มเปราะบาง+ไม่มีบัตร, ทั้ง 3 กลุ่ม+ไม่มีบัตร, รวมทั้ง 3 กลุ่ม
 *   - ตรวจ level อัตโนมัติจากหัวตาราง (มีคอลัมน์ "ตำบล" → tambon, ไม่มี → amphur)
 *   - ข้ามแถวหัวเรื่อง + แถว "รวมทั้งจังหวัด"
 *   - อัปโหลดใหม่ = แทนข้อมูล level เดิมทั้งหมด (snapshot ล่าสุด) + เก็บประวัติ
 */
class MissedTargetImportService
{
    public function import(string $absolutePath, string $filename, $user = null, ?string $note = null): array
    {
        $spreadsheet = IOFactory::load($absolutePath);
        $sheet = $spreadsheet->getSheet(0);
        $rows = $sheet->toArray(null, true, true, false);

        // หาแถวหัวตาราง (แถวที่มีคำว่า "อำเภอ")
        $headerIdx = null;
        foreach ($rows as $i => $row) {
            foreach ($row as $cell) {
                if (is_string($cell) && mb_strpos($cell, 'อำเภอ') !== false) { $headerIdx = $i; break 2; }
            }
        }
        if ($headerIdx === null) {
            throw new \RuntimeException('ไม่พบหัวตาราง (คอลัมน์ "อำเภอ") ในไฟล์');
        }

        $header = $rows[$headerIdx];
        $amphurCol = null; $tambonCol = null;
        foreach ($header as $c => $label) {
            if (!is_string($label)) continue;
            if ($amphurCol === null && mb_strpos($label, 'อำเภอ') !== false) $amphurCol = $c;
            if ($tambonCol === null && mb_strpos($label, 'ตำบล') !== false) $tambonCol = $c;
        }
        $hasTambon = $tambonCol !== null;
        $level = $hasTambon ? 'tambon' : 'amphur';
        $locCol = max($amphurCol ?? 0, $tambonCol ?? 0);   // คอลัมน์สุดท้ายของชื่อพื้นที่
        $rankCol = ($amphurCol !== null && $amphurCol > 0) ? 0 : null;  // ลำดับอยู่คอลัมน์แรก ถ้ามี

        $parsed = [];
        for ($i = $headerIdx + 1; $i < count($rows); $i++) {
            $row = $rows[$i];
            $amphur = trim((string) ($row[$amphurCol] ?? ''));
            // หยุดที่แถวรวม หรือ แถวว่าง
            if ($amphur === '' && trim((string) ($row[$tambonCol ?? $amphurCol] ?? '')) === '') continue;
            if (mb_strpos($amphur, 'รวม') !== false) continue;

            $jpt   = $this->num($row[$locCol + 1] ?? 0);
            $vuln  = $this->num($row[$locCol + 2] ?? 0);
            $both  = $this->num($row[$locCol + 3] ?? 0);
            $total = $this->num($row[$locCol + 4] ?? 0);

            // ถ้า total ไม่มี ให้คำนวณจาก 3 กลุ่ม
            if ($total === 0 && ($jpt + $vuln + $both) > 0) $total = $jpt + $vuln + $both;

            $parsed[] = [
                'level'          => $level,
                'national_rank'  => $rankCol !== null ? $this->numOrNull($row[$rankCol] ?? null) : null,
                'amphur_name'    => $amphur,
                'tambon_name'    => $hasTambon ? (trim((string) ($row[$tambonCol] ?? '')) ?: null) : null,
                'cnt_jpt'        => $jpt,
                'cnt_vulnerable' => $vuln,
                'cnt_both'       => $both,
                'cnt_total'      => $total,
            ];
        }

        if (empty($parsed)) {
            throw new \RuntimeException('ไม่พบข้อมูลในไฟล์');
        }

        $totalSum = array_sum(array_column($parsed, 'cnt_total'));

        return DB::transaction(function () use ($parsed, $level, $filename, $user, $note, $totalSum) {
            // แทนข้อมูล level เดิมทั้งหมด (snapshot ล่าสุด)
            MissedTargetStat::where('level', $level)->delete();

            $import = MissedTargetImport::create([
                'filename'         => $filename,
                'level'            => $level,
                'row_count'        => count($parsed),
                'total_count'      => $totalSum,
                'uploaded_by'      => $user?->id,
                'uploaded_by_name' => $user?->name,
                'note'             => $note,
            ]);

            $now = now();
            $batch = array_map(fn ($r) => $r + [
                'import_id'  => $import->id,
                'created_at' => $now,
                'updated_at' => $now,
            ], $parsed);
            foreach (array_chunk($batch, 200) as $chunk) {
                MissedTargetStat::insert($chunk);
            }

            return [
                'import_id'  => $import->id,
                'level'      => $level,
                'row_count'  => count($parsed),
                'total'      => $totalSum,
            ];
        });
    }

    private function num($v): int
    {
        if ($v === null || $v === '') return 0;
        $s = preg_replace('/[^\d\-]/', '', (string) $v);  // ตัด comma/ช่องว่าง
        return $s === '' || $s === '-' ? 0 : (int) $s;
    }

    private function numOrNull($v): ?int
    {
        if ($v === null || $v === '') return null;
        $s = preg_replace('/[^\d]/', '', (string) $v);
        return $s === '' ? null : (int) $s;
    }
}
