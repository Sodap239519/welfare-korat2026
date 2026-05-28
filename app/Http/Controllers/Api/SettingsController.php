<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Bank;
use App\Models\Channel;
use App\Models\RegistrationStatus;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Super Admin · CRUD ทุก reference data ที่ใช้ในระบบ
 * - Channels (ช่องทางการลงทะเบียน)
 * - RegistrationStatuses (สถานะการลงทะเบียน 4.x)
 * - Banks (ธนาคาร — ใน DB หลังย้ายจาก config)
 */
class SettingsController extends Controller
{
    // ────────────────────────────────────────
    //   Channels — ช่องทางการลงทะเบียน
    // ────────────────────────────────────────

    public function listChannels(): JsonResponse
    {
        return response()->json([
            'data' => Channel::orderBy('sort_order')->orderBy('id')->get(),
        ]);
    }

    public function storeChannel(Request $request): JsonResponse
    {
        $data = $request->validate([
            'code'       => ['required', 'string', 'max:40', 'unique:channels,code'],
            'name'       => ['required', 'string', 'max:120'],
            'icon'       => ['nullable', 'string', 'max:80'],
            'sort_order' => ['nullable', 'integer'],
        ]);
        $data['sort_order'] ??= ((int) Channel::max('sort_order')) + 1;
        $row = Channel::create($data);
        activity('settings')->causedBy(auth()->user())->performedOn($row)->log("เพิ่มช่องทาง: {$row->name}");
        return response()->json(['data' => $row], 201);
    }

    public function updateChannel(Request $request, int $id): JsonResponse
    {
        $row = Channel::findOrFail($id);
        $data = $request->validate([
            'code'       => ['sometimes', 'string', 'max:40', 'unique:channels,code,'.$id],
            'name'       => ['sometimes', 'string', 'max:120'],
            'icon'       => ['sometimes', 'nullable', 'string', 'max:80'],
            'sort_order' => ['sometimes', 'integer'],
        ]);
        $row->update($data);
        activity('settings')->causedBy(auth()->user())->performedOn($row)->log("แก้ไขช่องทาง: {$row->name}");
        return response()->json(['data' => $row->fresh()]);
    }

    public function destroyChannel(int $id): JsonResponse
    {
        $row = Channel::findOrFail($id);
        $name = $row->name;
        $row->delete();
        activity('settings')->causedBy(auth()->user())->log("ลบช่องทาง: $name");
        return response()->json(['message' => "ลบช่องทาง '$name' แล้ว"]);
    }

    // ────────────────────────────────────────
    //   Registration Statuses — สถานะ 4.x
    // ────────────────────────────────────────

    public function listStatuses(): JsonResponse
    {
        return response()->json([
            'data' => RegistrationStatus::orderBy('sort_order')->orderBy('id')->get(),
        ]);
    }

    public function storeStatus(Request $request): JsonResponse
    {
        $data = $request->validate([
            'code'             => ['required', 'string', 'max:10', 'unique:registration_statuses,code'],
            'label'            => ['required', 'string', 'max:120'],
            'color'            => ['nullable', 'string', 'max:30'],
            'requires_note'    => ['sometimes', 'boolean'],
            'requires_channel' => ['sometimes', 'boolean'],
            'sort_order'       => ['nullable', 'integer'],
        ]);
        $data['sort_order'] ??= ((int) RegistrationStatus::max('sort_order')) + 1;
        $row = RegistrationStatus::create($data);
        activity('settings')->causedBy(auth()->user())->performedOn($row)->log("เพิ่มสถานะ: {$row->code} {$row->label}");
        return response()->json(['data' => $row], 201);
    }

    public function updateStatus(Request $request, int $id): JsonResponse
    {
        $row = RegistrationStatus::findOrFail($id);
        $data = $request->validate([
            'code'             => ['sometimes', 'string', 'max:10', 'unique:registration_statuses,code,'.$id],
            'label'            => ['sometimes', 'string', 'max:120'],
            'color'            => ['sometimes', 'nullable', 'string', 'max:30'],
            'requires_note'    => ['sometimes', 'boolean'],
            'requires_channel' => ['sometimes', 'boolean'],
            'sort_order'       => ['sometimes', 'integer'],
        ]);
        $row->update($data);
        activity('settings')->causedBy(auth()->user())->performedOn($row)->log("แก้ไขสถานะ: {$row->code} {$row->label}");
        return response()->json(['data' => $row->fresh()]);
    }

    public function destroyStatus(int $id): JsonResponse
    {
        $row = RegistrationStatus::findOrFail($id);
        $info = "{$row->code} {$row->label}";
        $row->delete();
        activity('settings')->causedBy(auth()->user())->log("ลบสถานะ: $info");
        return response()->json(['message' => "ลบสถานะ '$info' แล้ว"]);
    }

    // ────────────────────────────────────────
    //   Banks — ธนาคารย่อย
    // ────────────────────────────────────────

    public function listBanks(): JsonResponse
    {
        return response()->json([
            'data' => Bank::orderBy('sort_order')->orderBy('id')->get(),
        ]);
    }

    public function storeBank(Request $request): JsonResponse
    {
        $data = $request->validate([
            'code'       => ['required', 'string', 'max:20', 'unique:banks,code'],
            'name'       => ['required', 'string', 'max:120'],
            'sort_order' => ['nullable', 'integer'],
            'logo'       => ['nullable', 'image', 'mimes:png,jpg,jpeg,webp,svg', 'max:1024'], // ≤1MB
        ]);
        $data['sort_order'] ??= ((int) Bank::max('sort_order')) + 1;

        $logoFile = $request->file('logo');
        unset($data['logo']);

        $row = Bank::create($data);
        if ($logoFile) {
            $row->logo_path = $this->saveBankLogo($logoFile, $row->code);
            $row->save();
        }
        activity('settings')->causedBy(auth()->user())->performedOn($row)->log("เพิ่มธนาคาร: {$row->name}");
        return response()->json(['data' => $row->fresh()], 201);
    }

    public function updateBank(Request $request, int $id): JsonResponse
    {
        $row = Bank::findOrFail($id);
        $data = $request->validate([
            'code'        => ['sometimes', 'string', 'max:20', 'unique:banks,code,'.$id],
            'name'        => ['sometimes', 'string', 'max:120'],
            'sort_order'  => ['sometimes', 'integer'],
            'logo'        => ['nullable', 'image', 'mimes:png,jpg,jpeg,webp,svg', 'max:1024'],
            'remove_logo' => ['sometimes', 'boolean'],
        ]);

        $logoFile   = $request->file('logo');
        $removeLogo = (bool) ($data['remove_logo'] ?? false);
        unset($data['logo'], $data['remove_logo']);

        $row->update($data);

        if ($logoFile) {
            // ลบไฟล์เก่าทิ้งถ้าเก็บอยู่ใน public/img/banks/
            $this->removeBankLogoFile($row->logo_path);
            $row->logo_path = $this->saveBankLogo($logoFile, $row->code);
            $row->save();
        } elseif ($removeLogo && $row->logo_path) {
            $this->removeBankLogoFile($row->logo_path);
            $row->logo_path = null;
            $row->save();
        }

        activity('settings')->causedBy(auth()->user())->performedOn($row)->log("แก้ไขธนาคาร: {$row->name}");
        return response()->json(['data' => $row->fresh()]);
    }

    public function destroyBank(int $id): JsonResponse
    {
        $row = Bank::findOrFail($id);
        $name = $row->name;
        $this->removeBankLogoFile($row->logo_path);
        $row->delete();
        activity('settings')->causedBy(auth()->user())->log("ลบธนาคาร: $name");
        return response()->json(['message' => "ลบธนาคาร '$name' แล้ว"]);
    }

    /**
     * บันทึกไฟล์โลโก้ลง public/img/banks/ · คืน URL path เริ่มด้วย "/"
     * ตั้งชื่อตาม code ของธนาคาร + timestamp กัน cache (เช่น "ktb-1748275811.png")
     */
    private function saveBankLogo(\Illuminate\Http\UploadedFile $file, string $code): string
    {
        $dir = public_path('img/banks');
        if (!is_dir($dir)) @mkdir($dir, 0755, true);
        $ext = strtolower($file->getClientOriginalExtension() ?: $file->extension() ?: 'png');
        $filename = strtolower($code) . '-' . time() . '.' . $ext;
        $file->move($dir, $filename);
        return '/img/banks/' . $filename;
    }

    /** ลบไฟล์โลโก้เดิม (เฉพาะที่ path เริ่มด้วย /img/banks/ และไฟล์อยู่ใน public_path) */
    private function removeBankLogoFile(?string $path): void
    {
        if (!$path || !str_starts_with($path, '/img/banks/')) return;
        $full = public_path(ltrim($path, '/'));
        if (is_file($full)) @unlink($full);
    }
}
