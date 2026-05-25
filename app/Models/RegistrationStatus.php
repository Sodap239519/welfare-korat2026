<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RegistrationStatus extends Model
{
    protected $fillable = ['code', 'label', 'color', 'requires_note', 'requires_channel', 'sort_order'];

    protected $casts = [
        'requires_note'    => 'boolean',
        'requires_channel' => 'boolean',
    ];
}
