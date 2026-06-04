<?php

namespace App\Exports;

use App\Models\User;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use Maatwebsite\Excel\Concerns\WithTitle;

/** ส่งออกรายชื่อผู้ใช้ระบบ — แยกชีตตามประเภท (Super Admin / Admin อำเภอ / เจ้าหน้าที่ธนาคาร / ผู้กำกับติดตาม) */
class UsersByRoleExport implements WithMultipleSheets
{
    public function sheets(): array
    {
        return [
            new RoleSheet('super_admin', 'Super Admin'),
            new RoleSheet('admin',       'Admin อำเภอ'),
            new RoleSheet('bank_staff',  'เจ้าหน้าที่ธนาคาร'),
            new RoleSheet('tracker',     'ผู้กำกับติดตาม'),
        ];
    }
}

class RoleSheet implements FromCollection, WithHeadings, WithMapping, WithTitle
{
    public function __construct(private string $role, private string $title) {}

    public function collection()
    {
        return User::role($this->role)->with('amphur')->orderBy('phone')->get();
    }

    public function title(): string
    {
        return $this->title;
    }

    public function headings(): array
    {
        return ['ชื่อ-สกุล', 'เบอร์โทร (login)', 'อีเมล', 'อำเภอ', 'ธนาคาร', 'สาขา', 'สถานะ', 'สร้างเมื่อ'];
    }

    public function map($u): array
    {
        return [
            $u->name,
            $u->phone,
            $u->email ?: '—',
            $u->amphur?->name ?: '—',
            $u->bank_sub_channel ? strtoupper($u->bank_sub_channel) : '—',
            $u->bank_branch ?: '—',
            $u->active ? 'เปิดใช้งาน' : 'ระงับ',
            $u->created_at?->format('d/m/Y H:i') ?? '—',
        ];
    }
}
