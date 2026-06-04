<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Target extends Model
{
    use LogsActivity;

    protected $fillable = [
        'household_id', 'village_id', 'tambon_id', 'amphur_id',
        'member_seq', 'year', 'prefix', 'first_name', 'last_name',
        'poverty_level', 'has_old_welfare', 'annual_income', 'active', 'source',
    ];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['first_name', 'last_name', 'poverty_level', 'active'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->setDescriptionForEvent(fn (string $event) => match ($event) {
                'created' => 'เพิ่มเป้าหมาย',
                'updated' => 'แก้ไขข้อมูลเป้าหมาย',
                'deleted' => 'ลบเป้าหมาย',
                default   => $event,
            });
    }

    protected $casts = [
        'has_old_welfare' => 'boolean',
        'active'          => 'boolean',
        'annual_income'   => 'integer',
        'year'            => 'integer',
        'member_seq'      => 'integer',
    ];

    public function household(): BelongsTo { return $this->belongsTo(Household::class); }
    public function village(): BelongsTo { return $this->belongsTo(Village::class); }
    public function tambon(): BelongsTo { return $this->belongsTo(Tambon::class); }
    public function amphur(): BelongsTo { return $this->belongsTo(Amphur::class); }

    public function statusLogs(): HasMany { return $this->hasMany(TargetStatusLog::class); }
    public function currentStatus(): HasOne { return $this->hasOne(TargetCurrentStatus::class); }

    /** Document batches ที่ target นี้อยู่ใน (ปกติ active 1 batch — เพราะเข้าได้ทีละรอบ) */
    public function documentBatches(): BelongsToMany
    {
        return $this->belongsToMany(DocumentBatch::class, 'document_batch_targets', 'target_id', 'batch_id')
            ->withPivot('joined_at')
            ->withTimestamps();
    }

    public function fullName(): string
    {
        return trim(($this->prefix ?? '').' '.$this->first_name.' '.($this->last_name ?? ''));
    }
}
