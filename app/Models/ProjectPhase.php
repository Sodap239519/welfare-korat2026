<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProjectPhase extends Model
{
    protected $fillable = ['name', 'sop_level', 'icon', 'description', 'details', 'is_current', 'sort_order'];
    protected $casts = ['is_current' => 'boolean', 'details' => 'array'];
}
