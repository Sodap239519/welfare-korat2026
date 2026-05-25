<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Tambon extends Model
{
    protected $fillable = ['amphur_id', 'code', 'name'];

    public function amphur(): BelongsTo { return $this->belongsTo(Amphur::class); }
    public function villages(): HasMany { return $this->hasMany(Village::class); }
}
