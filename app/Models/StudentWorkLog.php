<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class StudentWorkLog extends Model
{
    use LogsActivity;

    protected $fillable = [
        'user_id', 'work_date', 'time_start', 'time_end',
        'registered_success', 'registered_fail',
        'supervisor_name', 'supervisor_position', 'supervisor_date',
        'lat', 'lng', 'location_accuracy', 'location_at', 'location_status',
    ];

    protected $casts = [
        'work_date'          => 'date',
        'supervisor_date'    => 'date',
        'registered_success' => 'integer',
        'registered_fail'    => 'integer',
        'lat'                => 'float',
        'lng'                => 'float',
        'location_accuracy'  => 'float',
        'location_at'        => 'datetime',
    ];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['work_date', 'registered_success', 'registered_fail'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->setDescriptionForEvent(fn ($e) => match ($e) {
                'created' => 'เพิ่มบันทึกการปฏิบัติงาน',
                'updated' => 'แก้ไขบันทึกการปฏิบัติงาน',
                'deleted' => 'ลบบันทึกการปฏิบัติงาน',
                default   => $e,
            });
    }

    /** ลบโฟลเดอร์ไฟล์แนบจริงเมื่อลบบันทึก (DB cascade ลบ row ไฟล์ให้ แต่ไม่ลบไฟล์จริง) */
    protected static function booted(): void
    {
        static::deleting(function (StudentWorkLog $log) {
            try {
                Storage::disk('local')->deleteDirectory("student-files/{$log->user_id}/{$log->id}");
            } catch (\Throwable $e) { /* ignore */ }
        });
    }

    public function user(): BelongsTo    { return $this->belongsTo(User::class); }
    public function entries(): HasMany   { return $this->hasMany(StudentWorkLogEntry::class, 'work_log_id'); }
    public function cases(): HasMany     { return $this->hasMany(StudentCaseRecord::class, 'work_log_id'); }
    public function files(): HasMany     { return $this->hasMany(StudentWorkFile::class, 'work_log_id'); }
}
