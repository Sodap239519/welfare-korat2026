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
     * เสิร์ฟไฟล์แนบของนักศึกษา (private) — เจ้าของ หรือ super_admin/admin เท่านั้น
     * ?download=1 เพื่อบังคับดาวน์โหลด, ไม่งั้นแสดง inline (ดูรูป/PDF ในเบราว์เซอร์ได้)
     */
    public function workFile(Request $request, int $id): StreamedResponse
    {
        $file = StudentWorkFile::findOrFail($id);
        $user = $request->user();

        $allowed = $user->id === $file->user_id || $user->hasAnyRole(['super_admin', 'admin', 'executive']);
        abort_unless($allowed, 403, 'ไม่มีสิทธิ์เข้าถึงไฟล์นี้');

        $disk = Storage::disk($file->disk ?: 'local');
        abort_unless($disk->exists($file->path), 404, 'ไม่พบไฟล์');

        $disposition = $request->boolean('download') ? 'attachment' : 'inline';

        return $disk->response($file->path, $file->original_name, [
            'Content-Type'        => $file->mime ?: 'application/octet-stream',
            'Content-Disposition' => $disposition . '; filename="' . $file->original_name . '"',
        ]);
    }
}
