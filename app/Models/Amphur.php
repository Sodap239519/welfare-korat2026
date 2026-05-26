<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Amphur extends Model
{
    protected $fillable = ['code', 'name', 'lat', 'lng'];
    protected $casts = ['lat' => 'float', 'lng' => 'float'];

    public function tambons(): HasMany
    {
        return $this->hasMany(Tambon::class);
    }
}
