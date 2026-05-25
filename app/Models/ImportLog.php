<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ImportLog extends Model
{
    protected $fillable = [
        'user_id', 'filename', 'mode',
        'total', 'success', 'updated', 'failed',
        'errors', 'autofix_log', 'status',
        'started_at', 'finished_at',
    ];

    protected $casts = [
        'errors'       => 'array',
        'autofix_log'  => 'array',
        'started_at'   => 'datetime',
        'finished_at'  => 'datetime',
    ];

    public function user(): BelongsTo { return $this->belongsTo(User::class); }
}
