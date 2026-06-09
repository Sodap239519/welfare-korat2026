<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\StudentWorkFile;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class FileController extends Controller
{
    /**
     * GET /api/files/work/{id}
     * เสิร์ฟไฟล์แนบของนักศึกษา (private) — เจ้าของ หรือ super_admin/admin/executive
     * ?download=1 บังคับดาวน์โหลด · ?thumb=1 ย่อรูป (เร็ว ประหยัดเน็ต)
     */
    public function workFile(Request $request, int $id): StreamedResponse
    {
        $file = StudentWorkFile::findOrFail($id);
        $user = $request->user();

        $allowed = $user->id === $file->user_id || $user->hasAnyRole(['super_admin', 'admin', 'executive']);
        abort_unless($allowed, 403, 'ไม่มีสิทธิ์เข้าถึงไฟล์นี้');

        $disk = Storage::disk($file->disk ?: 'local');
        abort_unless($disk->exists($file->path), 404, 'ไม่พบไฟล์');

        // ขอ thumbnail (เฉพาะรูปที่ GD อ่านได้) — ลดขนาดดาวน์โหลดมหาศาล
        if ($request->boolean('thumb') && str_starts_with((string) $file->mime, 'image/')) {
            $thumb = $this->thumbnail($file, $disk);
            if ($thumb) return $thumb;
        }

        $disposition = $request->boolean('download') ? 'attachment' : 'inline';

        return $disk->response($file->path, $file->original_name, [
            'Content-Type'        => $file->mime ?: 'application/octet-stream',
            'Content-Disposition' => $disposition . '; filename="' . $file->original_name . '"',
        ]);
    }

    /**
     * สร้าง/อ่าน thumbnail (max 600px) ด้วย GD + cache บน disk local
     * คืน null ถ้าสร้างไม่ได้ (เช่น HEIC / ไม่มี GD) → ให้ caller fallback เป็นไฟล์เต็ม
     */
    private function thumbnail(StudentWorkFile $file, $disk): ?StreamedResponse
    {
        if (!function_exists('imagecreatefromstring')) return null;

        $maxW = 600;
        $cacheRel = 'thumbs/' . $file->id . '_' . $maxW . '.jpg';
        $local = Storage::disk('local');

        if (!$local->exists($cacheRel)) {
            $src = @imagecreatefromstring($disk->get($file->path));
            if (!$src) return null; // GD อ่านไม่ได้ (HEIC ฯลฯ)

            // แก้การหมุนภาพจากกล้องมือถือ (EXIF orientation)
            if ($file->mime === 'image/jpeg' && function_exists('exif_read_data')) {
                try {
                    $abs = $disk->path($file->path);
                    $exif = @exif_read_data($abs);
                    $o = $exif['Orientation'] ?? 0;
                    if ($o === 3) $src = imagerotate($src, 180, 0);
                    elseif ($o === 6) $src = imagerotate($src, -90, 0);
                    elseif ($o === 8) $src = imagerotate($src, 90, 0);
                } catch (\Throwable $e) { /* ignore */ }
            }

            $w = imagesx($src); $h = imagesy($src);
            $scale = min(1, $maxW / max($w, $h));
            $nw = max(1, (int) ($w * $scale));
            $nh = max(1, (int) ($h * $scale));
            $dst = imagecreatetruecolor($nw, $nh);
            imagecopyresampled($dst, $src, 0, 0, 0, 0, $nw, $nh, $w, $h);
            ob_start();
            imagejpeg($dst, null, 72);
            $data = ob_get_clean();
            imagedestroy($src);
            imagedestroy($dst);
            $local->put($cacheRel, $data);
        }

        return $local->response($cacheRel, 'thumb.jpg', [
            'Content-Type'  => 'image/jpeg',
            'Cache-Control' => 'public, max-age=604800',
        ]);
    }
}
