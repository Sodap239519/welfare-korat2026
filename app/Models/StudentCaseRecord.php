<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StudentCaseRecord extends Model
{
    protected $fillable = ['user_id', 'work_log_id', 'full_name', 'phone', 'village_tambon', 'problem'];

    public function user(): BelongsTo    { return $this->belongsTo(User::class); }
    public function workLog(): BelongsTo { return $this->belongsTo(StudentWorkLog::class, 'work_log_id'); }
}
