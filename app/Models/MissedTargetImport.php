<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MissedTargetImport extends Model
{
    protected $fillable = [
        'filename', 'level', 'row_count', 'total_count',
        'uploaded_by', 'uploaded_by_name', 'note',
    ];

    protected $casts = [
        'row_count'   => 'integer',
        'total_count' => 'integer',
    ];

    public function stats(): HasMany
    {
        return $this->hasMany(MissedTargetStat::class, 'import_id');
    }
}
