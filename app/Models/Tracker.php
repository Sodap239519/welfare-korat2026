<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Tracker extends Model
{
    protected $fillable = ['user_id', 'village_id', 'full_name', 'position', 'position_other', 'phone', 'active'];
    protected $casts = ['active' => 'boolean'];

    public function user(): BelongsTo    { return $this->belongsTo(User::class); }
    public function village(): BelongsTo { return $this->belongsTo(Village::class); }
}
