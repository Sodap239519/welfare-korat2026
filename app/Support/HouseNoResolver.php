<?php

namespace App\Support;

use PhpOffice\PhpSpreadsheet\Cell\Cell;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;

/**
 * แก้ Thai date trap สำหรับฟิลด์ "บ้านเลขที่"
 *
 * Excel ภาษาไทยตีความ "27/1" เป็นวันที่ 27 มกราคม → เก็บเป็น date serial
 * พร้อม numFmt "d-mmm" → แสดงเป็น "27-ม.ค." ใน UI
 *
 * ดู memory: feedback-xlsx-thai-date-trap
 */
class HouseNoResolver
{
    /** Thai month abbrev → month number */
    private const THAI_MONTHS = [
        'ม.ค.' => 1,  'ก.พ.' => 2,  'มี.ค.' => 3,
        'เม.ย.' => 4, 'พ.ค.' => 5,  'มิ.ย.' => 6,
        'ก.ค.' => 7,  'ส.ค.' => 8,  'ก.ย.' => 9,
        'ต.ค.' => 10, 'พ.ย.' => 11, 'ธ.ค.' => 12,
    ];

    /**
     * @return array{value: string, was_fixed: bool, original: string|null, reason: string|null}
     */
    public static function resolve(Cell $cell): array
    {
        $raw = $cell->getValue();
        $fmt = $cell->getStyle()->getNumberFormat()->getFormatCode();

        if ($raw === null || $raw === '') {
            return ['value' => '', 'was_fixed' => false, 'original' => null, 'reason' => null];
        }

        // Case 1: numeric value + date format code (contains 'd' + 'm', not text format)
        if (is_numeric($raw) && self::looksLikeDateFormat($fmt)) {
            $dt = ExcelDate::excelToDateTimeObject((float) $raw);
            $fixed = (int) $dt->format('j') . '/' . (int) $dt->format('n'); // "27/1"
            $displayOriginal = (int) $dt->format('j') . '-' . self::thaiMonthAbbrev((int) $dt->format('n'));

            return [
                'value'     => $fixed,
                'was_fixed' => true,
                'original'  => $displayOriginal,
                'reason'    => "Excel date serial {$raw} · numFmt={$fmt}",
            ];
        }

        // Case 2: string in form "27-ม.ค." (user typed it as text)
        $str = trim((string) $raw);
        foreach (self::THAI_MONTHS as $abbrev => $month) {
            if (preg_match('/^(\d{1,2})-' . preg_quote($abbrev, '/') . '$/u', $str, $m)) {
                $fixed = ((int) $m[1]) . '/' . $month;
                return [
                    'value'     => $fixed,
                    'was_fixed' => true,
                    'original'  => $str,
                    'reason'    => 'Thai month abbreviation string',
                ];
            }
        }

        return ['value' => $str, 'was_fixed' => false, 'original' => null, 'reason' => null];
    }

    private static function looksLikeDateFormat(?string $fmt): bool
    {
        if (!$fmt || $fmt === 'General') return false;
        // Strip quoted literals "..." and bracketed sections [...]
        $clean = preg_replace(['/"[^"]*"/', '/\[[^\]]*\]/'], '', $fmt);
        return stripos($clean, 'd') !== false && stripos($clean, 'm') !== false;
    }

    private static function thaiMonthAbbrev(int $month): string
    {
        $map = array_flip(self::THAI_MONTHS);
        return $map[$month] ?? '';
    }
}
