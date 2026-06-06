<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class StudentWorkLog extends Model
{
    use LogsActivity;

    protected $fillable = [
        'user_id', 'work_date', 'time_start', 'time_end',
        'registered_success', 'registered_fail',
        'supervisor_name', 'supervisor_position', 'supervisor_date',
    ];

    protected $casts = [
        'work_date'          => 'date',
        'supervisor_date'    => 'date',
        'registered_success' => 'integer',
        'registered_fail'    => 'integer',
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

    public function user(): BelongsTo    { return $this->belongsTo(User::class); }
    public function entries(): HasMany   { return $this->hasMany(StudentWorkLogEntry::class, 'work_log_id'); }
    public function cases(): HasMany     { return $this->hasMany(StudentCaseRecord::class, 'work_log_id'); }
}
