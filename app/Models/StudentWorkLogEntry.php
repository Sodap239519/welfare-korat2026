<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StudentWorkLogEntry extends Model
{
    protected $fillable = ['work_log_id', 'period', 'activity_type', 'detail', 'service_count'];

    protected $casts = ['service_count' => 'integer'];

    public function workLog(): BelongsTo { return $this->belongsTo(StudentWorkLog::class, 'work_log_id'); }
}
