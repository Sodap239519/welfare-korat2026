<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ReportSnapshot extends Model
{
    protected $fillable = [
        'type', 'snapshot_date', 'week_num',
        'total_targets', 'total_registered', 'pct_done',
        'payload', 'file_path',
    ];

    protected $casts = [
        'snapshot_date' => 'date',
        'payload'       => 'array',
        'pct_done'      => 'decimal:2',
    ];
}
