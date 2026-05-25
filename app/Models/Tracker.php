<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Tracker extends Model
{
    use LogsActivity;

    protected $fillable = ['user_id', 'village_id', 'full_name', 'position', 'position_other', 'phone', 'active'];
    protected $casts = ['active' => 'boolean'];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['full_name', 'position', 'phone', 'active'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->setDescriptionForEvent(fn ($e) => match ($e) {
                'created' => 'เพิ่มผู้กำกับติดตาม',
                'updated' => 'แก้ไขผู้กำกับติดตาม',
                'deleted' => 'ลบผู้กำกับติดตาม',
                default   => $e,
            });
    }

    public function user(): BelongsTo    { return $this->belongsTo(User::class); }
    public function village(): BelongsTo { return $this->belongsTo(Village::class); }
}
