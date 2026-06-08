<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    use HasFactory, Notifiable, HasApiTokens, HasRoles, LogsActivity;

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['name', 'phone', 'email', 'position_type', 'position_other', 'active'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->setDescriptionForEvent(fn ($e) => match ($e) {
                'created' => 'สร้างผู้ใช้',
                'updated' => 'แก้ไขข้อมูลผู้ใช้',
                'deleted' => 'ลบผู้ใช้',
                default   => $e,
            });
    }

    protected $fillable = [
        'name', 'email', 'password',
        'phone', 'position_type', 'position_other',
        'bank_channel_id', 'bank_sub_channel', 'bank_branch',
        'amphur_id',
        // โปรไฟล์นักศึกษา (role 'student')
        'student_id', 'faculty', 'major', 'line_id', 'work_unit_type',
        'active', 'last_login_at',
    ];

    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password'          => 'hashed',
            'active'            => 'boolean',
            'last_login_at'     => 'datetime',
        ];
    }

    public function scopes(): HasMany   { return $this->hasMany(UserScope::class); }
    public function tracker()           { return $this->hasOne(Tracker::class); }

    // โมดูลนักศึกษา
    public function workLogs(): HasMany       { return $this->hasMany(StudentWorkLog::class); }
    public function caseRecords(): HasMany    { return $this->hasMany(StudentCaseRecord::class); }
    public function selfAssessment()          { return $this->hasOne(StudentSelfAssessment::class); }

    public function bankChannel()
    {
        return $this->belongsTo(\App\Models\Channel::class, 'bank_channel_id');
    }

    /** Admin ระดับอำเภอ — ผูกกับ 1 อำเภอ */
    public function amphur()
    {
        return $this->belongsTo(\App\Models\Amphur::class, 'amphur_id');
    }

    /**
     * ป้ายชื่อ "หน่วยบริการที่ปฏิบัติงาน" ของนักศึกษา (จากข้อมูลตอนสมัคร)
     * - amphur → "ที่ว่าการอำเภอ{ชื่ออำเภอ}"
     * - bank   → "{ชื่อธนาคาร} สาขา{ชื่อสาขา}"
     */
    public function getWorkUnitLabelAttribute(): ?string
    {
        if ($this->work_unit_type === 'amphur') {
            $name = $this->amphur?->name;
            return $name ? "ที่ว่าการอำเภอ{$name}" : null;
        }
        if ($this->work_unit_type === 'bank') {
            $bank = $this->bankChannel?->name;
            if (!$bank) {
                return null;
            }
            return $this->bank_branch ? "{$bank} สาขา{$this->bank_branch}" : $bank;
        }
        return null;
    }
}
