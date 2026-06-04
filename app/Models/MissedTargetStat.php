<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MissedTargetStat extends Model
{
    protected $fillable = [
        'import_id', 'level', 'national_rank',
        'amphur_name', 'tambon_name',
        'cnt_jpt', 'cnt_vulnerable', 'cnt_both', 'cnt_total',
    ];

    protected $casts = [
        'national_rank'  => 'integer',
        'cnt_jpt'        => 'integer',
        'cnt_vulnerable' => 'integer',
        'cnt_both'       => 'integer',
        'cnt_total'      => 'integer',
    ];
}
