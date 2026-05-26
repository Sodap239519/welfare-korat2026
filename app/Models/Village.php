<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Village extends Model
{
    protected $fillable = ['tambon_id', 'moo', 'name', 'lat', 'lng'];

    protected $casts = [
        'lat' => 'float',
        'lng' => 'float',
    ];

    public function tambon(): BelongsTo { return $this->belongsTo(Tambon::class); }
    public function households(): HasMany { return $this->hasMany(Household::class); }
    public function trackers(): HasMany { return $this->hasMany(Tracker::class); }
}
