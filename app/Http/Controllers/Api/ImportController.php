<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ImportLog;
use App\Notifications\ImportCompleted;
use App\Services\XlsxImportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class ImportController extends Controller
{
    public function __construct(private XlsxImportService $service) {}

    /**
     * POST /api/import/preview
     * Upload + parse → ส่ง autofix + counts กลับโดยไม่ commit
     */
    public function preview(Request $request): JsonResponse
    {
        $file = $this->validatedFile($request);

        $tmpPath = $file->store('imports/preview', 'local');
        $abs     = Storage::disk('local')->path($tmpPath);

        try {
            $result = $this->service->import($abs, commit: false, userId: $request->user()->id, originalName: $file->getClientOriginalName());
        } finally {
            Storage::disk('local')->delete($tmpPath);
        }

        return response()->json([
            'mode'    => 'preview',
            'file'    => $file->getClientOriginalName(),
            'summary' => [
                'total'   => $result['total'],
                'created' => $result['success'],
                'updated' => $result['updated'],
                'failed'  => $result['failed'],
                'fixes'   => count(array_filter($result['autofix'], fn ($r) => isset($r['fixed']))),
            ],
            'autofix' => array_slice($result['autofix'], 0, 200),
        ]);
    }

    /**
     * POST /api/import/run
     * Upload + parse + commit (Upsert by household + member_seq)
     */
    public function run(Request $request): JsonResponse
    {
        $file = $this->validatedFile($request);

        $storedPath = $file->store('imports', 'local');
        $abs        = Storage::disk('local')->path($storedPath);

        $result = $this->service->import($abs, commit: true, userId: $request->user()->id, originalName: $file->getClientOriginalName());

        // แจ้งเตือนผู้นำเข้า
        if ($result['import_log_id']) {
            $log = ImportLog::find($result['import_log_id']);
            $request->user()->notify(new ImportCompleted($log));
        }

        return response()->json([
            'mode'           => 'run',
            'file'           => $file->getClientOriginalName(),
            'import_log_id'  => $result['import_log_id'],
            'summary'        => [
                'total'   => $result['total'],
                'created' => $result['success'],
                'updated' => $result['updated'],
                'failed'  => $result['failed'],
                'fixes'   => count(array_filter($result['autofix'], fn ($r) => isset($r['fixed']))),
            ],
        ]);
    }

    /** GET /api/import/logs */
    public function logs(): JsonResponse
    {
        $logs = ImportLog::with('user:id,name')
            ->orderByDesc('id')
            ->limit(50)
            ->get()
            ->map(fn ($l) => [
                'id'            => $l->id,
                'filename'      => $l->filename,
                'mode'          => $l->mode,
                'total'         => $l->total,
                'success'       => $l->success,
                'updated'       => $l->updated,
                'failed'        => $l->failed,
                'fixes'         => is_array($l->autofix_log) ? count(array_filter($l->autofix_log, fn ($r) => isset($r['fixed']))) : 0,
                'status'        => $l->status,
                'started_at'    => $l->started_at?->toIso8601String(),
                'finished_at'   => $l->finished_at?->toIso8601String(),
                'user'          => $l->user?->name,
            ]);
        return response()->json(['data' => $logs]);
    }

    /**
     * DELETE /api/import/logs
     * ล้างประวัติการนำเข้าทั้งหมด (เฉพาะ super_admin) — ไม่แตะข้อมูลเป้าหมาย
     */
    public function clearLogs(): JsonResponse
    {
        $deleted = ImportLog::query()->delete();

        return response()->json([
            'message' => "ล้างประวัติการนำเข้าแล้ว {$deleted} รายการ",
            'deleted' => $deleted,
        ]);
    }

    /** GET /api/import/logs/{id} */
    public function showLog(int $id): JsonResponse
    {
        $log = ImportLog::findOrFail($id);
        return response()->json([
            'data' => [
                'id'          => $log->id,
                'filename'    => $log->filename,
                'mode'        => $log->mode,
                'total'       => $log->total,
                'success'     => $log->success,
                'updated'     => $log->updated,
                'failed'      => $log->failed,
                'status'      => $log->status,
                'autofix_log' => $log->autofix_log,
                'errors'      => $log->errors,
                'started_at'  => $log->started_at,
                'finished_at' => $log->finished_at,
            ],
        ]);
    }

    private function validatedFile(Request $request)
    {
        $request->validate([
            'file' => ['required', 'file', 'max:51200', 'mimes:xlsx,xls,csv'],
        ], [
            'file.mimes' => 'รองรับเฉพาะไฟล์ .xlsx / .xls / .csv',
            'file.max'   => 'ขนาดไฟล์เกิน 50 MB',
        ]);
        return $request->file('file');
    }
}
